<?php

declare(strict_types=1);

namespace App\Tests\Integration\DB\Knowledge;

use App\Entity\Company\Company;
use App\Repository\AI\CompanyKnowledgeRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('db')]
final class CompanyKnowledgeRankingTest extends KernelTestCase
{
    private Connection $db;
    private CompanyKnowledgeRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = self::getContainer()->get(Connection::class);
        $this->repo = self::getContainer()->get(CompanyKnowledgeRepository::class);
        $this->db->executeStatement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function testRankingPrefersFresherAndMoreRelevant(): void
    {
        [$company, $companyId] = $this->getOrCreateCompany();

        $this->db->executeStatement('DELETE FROM company_knowledge WHERE company_id = :cid', ['cid' => $companyId]);

        $freshId = Uuid::uuid4()->toString();
        $oldId = Uuid::uuid4()->toString();
        $now = new \DateTimeImmutable();
        $old = $now->modify('-45 days');

        $this->db->insert('company_knowledge', [
            'id' => $freshId, 'company_id' => $companyId, 'type' => 'FAQ',
            'title' => 'Доставка по РФ', 'content' => 'Сроки доставки 2–5 дней.', 'tags' => null,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);

        $this->db->insert('company_knowledge', [
            'id' => $oldId, 'company_id' => $companyId, 'type' => 'FAQ',
            'title' => 'Про доставку (старое)', 'content' => 'Старая информация о доставке.', 'tags' => null,
            'created_at' => $old->format('Y-m-d H:i:s'),
        ]);

        for ($i = 0; $i < 30; ++$i) {
            $this->db->insert('company_knowledge', [
                'id' => Uuid::uuid4()->toString(), 'company_id' => $companyId, 'type' => 'FAQ',
                'title' => 'Прочее '.$i, 'content' => 'lorem ipsum', 'tags' => null,
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
        }

        $this->db->executeStatement('ANALYZE company_knowledge');

        $hits = $this->repo->findTopByQuery($company, 'доставка', 5);
        self::assertNotEmpty($hits);
        self::assertSame($freshId, $hits[0]->id, 'Свежая запись о доставке должна быть первой');
    }

    /** @return array{0: Company, 1: string} */
    private function getOrCreateCompany(): array
    {
        $row = $this->db->fetchAssociative('SELECT id FROM companies LIMIT 1');
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        if ($row && !empty($row['id'])) {
            /** @var Company $company */
            $company = $em->getRepository(Company::class)->find($row['id']);

            return [$company, (string) $row['id']];
        }

        $owner = Uuid::uuid4()->toString();
        $this->db->insert('user', [
            'id' => $owner, 'email' => 'owner+'.bin2hex(random_bytes(3)).'@test.local',
            'roles' => json_encode(['ROLE_USER']), 'password' => 'test',
        ]);
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
