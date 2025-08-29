<?php

declare(strict_types=1);

namespace App\Tests\Integration\DB\Knowledge;

use App\Entity\Company\User;
use App\Service\CompanyManager;
use App\Tests\Integration\DB\Knowledge\Traits\RunsMigrationsAndSeedsTestDB;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('db')]
final class CompanyKnowledgeIndexesTest extends KernelTestCase
{
    use RunsMigrationsAndSeedsTestDB;

    private Connection $db;

    public static function setUpBeforeClass(): void
    {
        self::runMigrationsOnce();
    }

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var Connection $db */
        $db = self::getContainer()->get(Connection::class);
        $this->db = $db;
        $this->db->executeStatement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function testSchemaHasTsVectorAndIndexes(): void
    {
        $cols = $this->db->fetchAllAssociative("
            SELECT column_name, data_type, is_generated
            FROM information_schema.columns
            WHERE table_name = 'company_knowledge'
        ");
        $tsRu = array_values(array_filter($cols, fn ($r) => 'ts_ru' === $r['column_name']));
        $this->assertNotEmpty($tsRu, 'ts_ru column missing');
        $this->assertSame('tsvector', $tsRu[0]['data_type'], 'ts_ru must be tsvector');
        $this->assertSame('ALWAYS', $tsRu[0]['is_generated'], 'ts_ru must be GENERATED ALWAYS');

        $idx = $this->db->fetchAllAssociative("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'company_knowledge'
        ");
        $names = array_column($idx, 'indexname');
        $this->assertContains('idx_ck_ts', $names, 'GIN(ts_ru) index missing');
        $this->assertContains('idx_ck_title_trgm', $names, 'GIN(title gin_trgm_ops) index missing');
        $this->assertContains('idx_ck_content_trgm', $names, 'GIN(content gin_trgm_ops) index missing');
    }

    public function testExplainUsesGinIndexForFts(): void
    {
        $companyId = $this->getOrCreateCompanyId();
        $this->ensureKnowledgeSeed($companyId);
        $this->db->executeStatement('ANALYZE company_knowledge');

        // Чтобы план был стабильным на маленькой таблице — отключаем seqscan
        $this->db->executeStatement('SET enable_seqscan = off');
        try {
            $planRows = $this->explain("
                SELECT id, title
                FROM company_knowledge
                WHERE company_id = :cid
                  AND ts_ru @@ plainto_tsquery('russian','доставка')
            ", ['cid' => $companyId]);

            $planText = implode("\n", array_column($planRows, 'QUERY PLAN'));
            $this->assertMatchesRegularExpression('/idx_ck_ts/i', $planText, "FTS plan must use idx_ck_ts.\n$planText");
        } finally {
            $this->db->executeStatement('RESET enable_seqscan');
        }
    }

    public function testExplainUsesTrigramIndexForIlikeWithSeqScanOff(): void
    {
        $companyId = $this->getOrCreateCompanyId();
        $this->ensureKnowledgeSeed($companyId);
        $this->db->executeStatement('ANALYZE company_knowledge');

        $this->db->executeStatement('SET enable_seqscan = off');
        try {
            // Без company_id — демонстрация чистого trigram на title
            $planRows = $this->explain("
                SELECT id, title
                FROM company_knowledge
                WHERE title ILIKE '%доставка%'
            ");
            $planText = implode("\n", array_column($planRows, 'QUERY PLAN'));
            $this->assertMatchesRegularExpression('/idx_ck_title_trgm/i', $planText, "ILIKE plan should use trigram index on title.\n$planText");
        } finally {
            $this->db->executeStatement('RESET enable_seqscan');
        }
    }

    // ---------------- helpers ----------------

    private function explain(string $sql, array $params = []): array
    {
        $stmt = $this->db->executeQuery("EXPLAIN (ANALYZE, BUFFERS) $sql", $params);

        return $stmt->fetchAllAssociative();
    }

    private function getOrCreateCompanyId(): string
    {
        $row = $this->db->fetchAssociative('SELECT id FROM companies ORDER BY created_at DESC LIMIT 1');
        if ($row && !empty($row['id'])) {
            return (string) $row['id'];
        }

        // owner-пользователь
        $ownerId = Uuid::uuid4()->toString();
        $this->db->insert('user', [
            'id' => $ownerId,
            'email' => 'owner+'.bin2hex(random_bytes(4)).'@test.local',
            'roles' => json_encode(['ROLE_USER']),
            'password' => 'test',
        ]);

        /** @var CompanyManager $cm */
        $cm = self::getContainer()->get(CompanyManager::class);
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRef = $em->getRepository(User::class)->find($ownerId) ?? (function () use ($em, $ownerId) {
            $em->clear();

            return $em->getRepository(User::class)->find($ownerId);
        })();

        $slug = 'company-'.bin2hex(random_bytes(3));
        $company = $cm->createCompany('Test Company', $slug, $userRef);

        return $company->getId();
    }

    private function ensureKnowledgeSeed(string $companyId): void
    {
        $exists = $this->db->fetchOne("
            SELECT 1 FROM company_knowledge
            WHERE company_id = :cid AND (title ILIKE '%доставка%' OR content ILIKE '%доставка%')
            LIMIT 1
        ", ['cid' => $companyId]);

        if (!$exists) {
            $this->insertKnowledge($companyId, 'Доставка', 'Доставка по РФ 2–5 дней, удалённые регионы 5–10 дней.');
        }

        $cnt = (int) $this->db->fetchOne('SELECT COUNT(*) FROM company_knowledge WHERE company_id = :cid', ['cid' => $companyId]);
        if ($cnt < 400) { // чуть больше, чтобы индекс был явно выгоднее
            for ($i = $cnt; $i < 400; ++$i) {
                $this->insertKnowledge($companyId, 'Заголовок '.$i, str_repeat('lorem ipsum ', 10));
            }
        }
    }

    private function insertKnowledge(string $companyId, string $title, string $content): void
    {
        $this->db->insert('company_knowledge', [
            'id' => Uuid::uuid4()->toString(),
            'company_id' => $companyId,
            'type' => 'FAQ',
            'title' => $title,
            'content' => $content,
            'tags' => null,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
