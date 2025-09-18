<?php

declare(strict_types=1);

namespace App\Tests\Integration\AI;

use App\Entity\AI\CompanyKnowledge;
use App\Entity\AI\Enum\KnowledgeType;
use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Repository\AI\CompanyKnowledgeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('db')]
final class CompanyKnowledgeSearchTest extends KernelTestCase
{
    private const COMPANY_ID = '2adce003-b4ac-45e0-9110-6ee2defb4060';

    private Connection $db;
    private EntityManagerInterface $em;
    private CompanyKnowledgeRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->db = $container->get(Connection::class);
        $this->db->executeStatement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $this->em = $em;
        $this->repo = $container->get(CompanyKnowledgeRepository::class);

        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->isTransactionActive()) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }

    public function testFindsKnowledgeByReturnQueryForCompany(): void
    {
        $this->db->executeStatement('DELETE FROM company_knowledge WHERE company_id = :cid', ['cid' => self::COMPANY_ID]);
        $this->db->executeStatement('DELETE FROM ai_company_profile WHERE company_id = :cid', ['cid' => self::COMPANY_ID]);
        $this->db->executeStatement('DELETE FROM companies WHERE id = :cid', ['cid' => self::COMPANY_ID]);

        $company = $this->createCompanyWithKnowledge();

        $this->refreshTsVectorIfNeeded(self::COMPANY_ID);

        $hits = $this->repo->findTopByQuery($company, 'возврат товара', 5);
        self::assertGreaterThanOrEqual(1, count($hits), 'FTS query should return at least one result.');

        $withReturnInTitle = array_filter(
            $hits,
            fn (mixed $hit): bool => mb_stripos($this->extractTitle($hit), 'Возврат') !== false
        );
        self::assertNotEmpty($withReturnInTitle, 'Expected at least one knowledge record with "Возврат" in the title.');

        $allHits = $this->repo->findTopByQuery($company, '', 5);
        self::assertGreaterThanOrEqual(1, count($allHits), 'Empty query should fall back to latest records.');
    }

    private function createCompanyWithKnowledge(): Company
    {
        $owner = new User(Uuid::uuid4()->toString());
        $owner->setEmail('owner+'.bin2hex(random_bytes(4)).'@test.local');
        $owner->setRoles(['ROLE_USER']);
        $owner->setPassword('test-hash');
        $this->em->persist($owner);

        $company = new Company(self::COMPANY_ID, $owner);
        $company->setName('Интеграционный тест возврата');
        $company->setSlug('return-check-'.bin2hex(random_bytes(4)));
        $this->em->persist($company);

        for ($i = 0; $i < 2; ++$i) {
            $knowledge = new CompanyKnowledge(
                Uuid::uuid4()->toString(),
                $company,
                KnowledgeType::FAQ,
                'Возврат товара - Ответ/Содержание',
                'Возврат в течение 14 дней...'
            );

            $this->em->persist($knowledge);
        }

        $this->em->flush();

        return $company;
    }

    private function refreshTsVectorIfNeeded(string $companyId): void
    {
        $row = $this->db->fetchAssociative(<<<'SQL'
            SELECT is_generated
            FROM information_schema.columns
            WHERE table_name = 'company_knowledge'
              AND column_name = 'ts_ru'
            LIMIT 1
        SQL);

        if (!$row) {
            return;
        }

        if ('NEVER' === ($row['is_generated'] ?? null)) {
            $this->db->executeStatement(
                "UPDATE company_knowledge
                 SET ts_ru = to_tsvector('russian', coalesce(title,'') || ' ' || coalesce(content,''))
                 WHERE company_id = :company",
                ['company' => $companyId]
            );
        }
    }

    /**
     * @param array<string, mixed>|object $hit
     */
    private function extractTitle(mixed $hit): string
    {
        if (is_array($hit)) {
            return (string) ($hit['title'] ?? '');
        }

        if (is_object($hit)) {
            if (method_exists($hit, 'getTitle')) {
                return (string) $hit->getTitle();
            }

            if (property_exists($hit, 'title')) {
                return (string) $hit->title;
            }
        }

        throw new \InvalidArgumentException('Unable to extract title from knowledge hit.');
    }
}
