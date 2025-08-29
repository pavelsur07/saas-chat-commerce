<?php

declare(strict_types=1);

namespace App\Tests\Integration\DB\Knowledge;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

#[Group('db')]
final class CompanyKnowledgeIndexesTest extends KernelTestCase
{
    private static bool $migrated = false;

    private Connection $db;

    public static function setUpBeforeClass(): void
    {
        // Один раз на класс — гоняем миграции и (если есть) фикстуры в тестовом окружении
        if (self::$migrated) {
            return;
        }

        $migrate = new Process(['php', 'bin/console', 'doctrine:migrations:migrate', '-n', '--env=test']);
        $migrate->setTimeout(300);
        $migrate->run();
        if (!$migrate->isSuccessful()) {
            throw new \RuntimeException("Migrations failed:\n".$migrate->getErrorOutput().$migrate->getOutput());
        }

        $fixtures = new Process(['php', 'bin/console', 'doctrine:fixtures:load', '-n', '--env=test', '--append']);
        $fixtures->setTimeout(300);
        $fixtures->run(); // если нет пакета фикстур — просто игнорируем результат

        self::$migrated = true;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var Connection $db */
        $db = self::getContainer()->get(Connection::class);
        $this->db = $db;

        // На всякий случай: включим trigram
        $this->db->executeStatement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function testSchemaHasTsVectorAndIndexes(): void
    {
        // ts_ru есть и это GENERATED ALWAYS tsvector?
        $cols = $this->db->fetchAllAssociative("
            SELECT column_name, data_type, is_generated
            FROM information_schema.columns
            WHERE table_name = 'company_knowledge'
            ORDER BY ordinal_position
        ");

        $tsRu = array_values(array_filter($cols, fn ($r) => 'ts_ru' === $r['column_name']));
        self::assertNotEmpty($tsRu, 'ts_ru column missing');
        self::assertSame('tsvector', $tsRu[0]['data_type'], 'ts_ru must be tsvector');
        self::assertSame('ALWAYS', $tsRu[0]['is_generated'], 'ts_ru must be GENERATED ALWAYS');

        // Индексы существуют?
        $idx = $this->db->fetchAllAssociative("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'company_knowledge'
            ORDER BY indexname
        ");
        $names = array_column($idx, 'indexname');
        self::assertContains('idx_ck_ts', $names, 'GIN(ts_ru) index missing');
        self::assertContains('idx_ck_title_trgm', $names, 'GIN(title gin_trgm_ops) index missing');
        self::assertContains('idx_ck_content_trgm', $names, 'GIN(content gin_trgm_ops) index missing');
    }

    public function testExplainUsesGinIndexForFts(): void
    {
        $companyId = $this->getOrCreateCompanyId();
        $this->ensureKnowledgeSeed($companyId);
        $this->db->executeStatement('ANALYZE company_knowledge');

        // Для детерминизма (на маленьких данных) заставим планировщик выбрать индекс
        $this->db->executeStatement('SET enable_seqscan = off');
        try {
            // Важно: FTS по СТОЛБЦУ ts_ru (не по выражению), без фильтра по company_id — чтобы не сработал btree
            $planRows = $this->explain("
                SELECT id, title
                FROM company_knowledge
                WHERE ts_ru @@ plainto_tsquery('russian','доставка')
            ");
            $planText = implode("\n", array_column($planRows, 'QUERY PLAN'));

            self::assertMatchesRegularExpression('/idx_ck_ts/i', $planText, "FTS must use GIN(ts_ru).\n$planText");
        } finally {
            $this->db->executeStatement('RESET enable_seqscan');
        }
    }

    public function testExplainUsesTrigramIndexForIlikeWithSeqScanOff(): void
    {
        $this->getOrCreateCompanyId(); // гарантируем наличие строк
        $this->db->executeStatement('ANALYZE company_knowledge');

        $this->db->executeStatement('SET enable_seqscan = off');
        try {
            // Демонстрация trigram: без фильтра по company_id, чтобы план выбрал именно GIN(trgm)
            $planRows = $this->explain("
                SELECT id, title
                FROM company_knowledge
                WHERE title ILIKE '%доставка%'
            ");
            $planText = implode("\n", array_column($planRows, 'QUERY PLAN'));

            self::assertMatchesRegularExpression('/idx_ck_title_trgm/i', $planText, "ILIKE(title) should use trigram index.\n$planText");
        } finally {
            $this->db->executeStatement('RESET enable_seqscan');
        }
    }

    // ---------------- helpers ----------------

    /** Выполняет EXPLAIN (ANALYZE, BUFFERS) и возвращает строки плана */
    private function explain(string $sql, array $params = []): array
    {
        $stmt = $this->db->executeQuery("EXPLAIN (ANALYZE, BUFFERS) $sql", $params);

        return $stmt->fetchAllAssociative();
    }

    /** Возвращает id существующей компании или создаёт новую (с пользователем) */
    private function getOrCreateCompanyId(): string
    {
        $row = $this->db->fetchAssociative('SELECT id FROM companies ORDER BY created_at DESC LIMIT 1');
        if ($row && !empty($row['id'])) {
            return (string) $row['id'];
        }

        // Создаём owner-пользователя
        $ownerId = Uuid::uuid4()->toString();
        $this->db->insert('user', [
            'id' => $ownerId,
            'email' => 'owner+'.bin2hex(random_bytes(6)).'@test.local',
            'roles' => json_encode(['ROLE_USER']),
            'password' => 'test', // для тестов
        ]);

        // Создаём компанию
        $companyId = Uuid::uuid4()->toString();
        $this->db->insert('companies', [
            'id' => $companyId,
            'owner_id' => $ownerId,
            'name' => 'Test Co',
            'slug' => 'test-'.bin2hex(random_bytes(3)),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $companyId;
    }

    /** Досеивает company_knowledge под нужный запрос */
    private function ensureKnowledgeSeed(string $companyId): void
    {
        // хотя бы одна запись с "доставка"
        $exists = $this->db->fetchOne("
            SELECT 1 FROM company_knowledge
            WHERE company_id = :cid
              AND (title ILIKE '%доставка%' OR content ILIKE '%доставка%')
            LIMIT 1
        ", ['cid' => $companyId]);

        if (!$exists) {
            $this->db->insert('company_knowledge', [
                'id' => Uuid::uuid4()->toString(),
                'company_id' => $companyId,
                'type' => 'FAQ',
                'title' => 'Доставка',
                'content' => 'Доставка по РФ 2–5 дней, удалённые регионы 5–10 дней.',
                'tags' => null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        // Добавим «шум», чтобы индексы были выгоднее (и план стабилен)
        $cnt = (int) $this->db->fetchOne('SELECT COUNT(*) FROM company_knowledge WHERE company_id = :cid', ['cid' => $companyId]);
        if ($cnt < 400) {
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            for ($i = $cnt; $i < 400; ++$i) {
                $this->db->insert('company_knowledge', [
                    'id' => Uuid::uuid4()->toString(),
                    'company_id' => $companyId,
                    'type' => 'FAQ',
                    'title' => 'Прочее '.$i,
                    'content' => 'lorem ipsum lorem ipsum lorem ipsum',
                    'tags' => null,
                    'created_at' => $now,
                ]);
            }
        }
    }
}
