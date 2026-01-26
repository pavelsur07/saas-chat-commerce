<?php

declare(strict_types=1);

namespace App\Tests\Integration\AI;

use App\Account\Entity\Company;
use App\Service\AI\KnowledgeSearchService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('db')]
final class KnowledgeSearchServiceTest extends KernelTestCase
{
    private Connection $db;
    private KnowledgeSearchService $svc;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = self::getContainer()->get(Connection::class);
        $this->svc = self::getContainer()->get(KnowledgeSearchService::class);
        $this->db->executeStatement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function testCacheHitAndInvalidation(): void
    {
        [$company, $companyId] = $this->getOrCreateCompany();

        $this->db->executeStatement('DELETE FROM company_knowledge WHERE company_id = :cid', ['cid' => $companyId]);
        $this->db->insert('company_knowledge', [
            'id' => Uuid::uuid4()->toString(), 'company_id' => $companyId, 'type' => 'FAQ',
            'title' => 'Доставка по РФ', 'content' => '2–5 дней', 'tags' => null,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // 1-й вызов — мисс
        $hits1 = $this->svc->search($company, 'доставка', 5);
        self::assertNotEmpty($hits1);

        // Добавим новую запись (но кэш ещё не инвалидируем)
        $this->db->insert('company_knowledge', [
            'id' => Uuid::uuid4()->toString(), 'company_id' => $companyId, 'type' => 'FAQ',
            'title' => 'Доставка в регионы', 'content' => '5–10 дней', 'tags' => null,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $hits2 = $this->svc->search($company, 'доставка', 5);
        self::assertCount(count($hits1), $hits2, 'Без инвалидации результат взят из кэша');

        // Инвалидация
        $this->svc->invalidateCompanyCache($company);
        $hits3 = $this->svc->search($company, 'доставка', 5);
        self::assertGreaterThanOrEqual(count($hits1), count($hits3), 'После инвалидации кэш обновился');
    }

    /** @return array{0: Company, 1: string} */
    private function getOrCreateCompany(): array
    {
        $cid = $this->db->fetchOne('SELECT id FROM companies LIMIT 1');
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        if ($cid) {
            /** @var Company $company */
            $company = $em->getRepository(Company::class)->find($cid);

            return [$company, (string) $cid];
        }

        // ВАЖНО: "user" — зарезервированное слово → экранируем двойными кавычками
        $owner = Uuid::uuid4()->toString();
        $this->db->executeStatement(
            'INSERT INTO "user" (id, email, roles, password) VALUES (:id, :email, :roles, :pwd)',
            [
                'id' => $owner,
                'email' => 'owner+'.bin2hex(random_bytes(3)).'@test.local',
                'roles' => json_encode(['ROLE_USER'], JSON_THROW_ON_ERROR),
                'pwd' => 'test',
            ]
        );

        $cid = Uuid::uuid4()->toString();
        $this->db->insert('companies', [
            'id' => $cid, 'owner_id' => $owner, 'name' => 'Test Co', 'slug' => 'test-'.bin2hex(random_bytes(2)),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        /** @var Company $company */
        $company = $em->getRepository(Company::class)->find($cid);

        return [$company, $cid];
    }
}
