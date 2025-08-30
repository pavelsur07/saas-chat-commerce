<?php

declare(strict_types=1);

namespace App\Tests\Integration\AI;

use App\Entity\Company\Company;
use App\Service\AI\KnowledgeImportService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('db')]
final class KnowledgeImportServiceTest extends KernelTestCase
{
    private Connection $db;
    private KnowledgeImportService $svc;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = self::getContainer()->get(Connection::class);
        $this->svc = self::getContainer()->get(KnowledgeImportService::class);
    }

    public function testParseMdAndPublish(): void
    {
        $md = <<<MD
## Доставка
Сроки 2–5 дней.

## Оплата
Карта, СБП.

## Возврат
14 дней.

## Поддержка
Пн–Пт 9–18 МСК.
MD;

        $items = $this->svc->parse('text/markdown', $md);
        self::assertCount(4, $items);

        $company = $this->ensureCompany();
        $this->db->executeStatement('DELETE FROM company_knowledge WHERE company_id = :cid', ['cid' => $company->getId()]);
        $n = $this->svc->publish($company, $items, true);
        self::assertSame(4, $n);
    }

    private function ensureCompany(): Company
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $id = $this->db->fetchOne('SELECT id FROM companies LIMIT 1');
        if ($id) {
            return $em->getRepository(Company::class)->find($id);
        }

        $owner = Uuid::uuid4()->toString();
        $this->db->executeStatement('INSERT INTO "user" (id,email,roles,password) VALUES (:i,:e,:r,:p)', [
            'i' => $owner, 'e' => 'imp+'.bin2hex(random_bytes(3)).'@test.local', 'r' => json_encode(['ROLE_USER']), 'p' => 'test',
        ]);
        $cid = Uuid::uuid4()->toString();
        $this->db->insert('companies', [
            'id' => $cid, 'owner_id' => $owner, 'name' => 'Import Co', 'slug' => 'imp-'.bin2hex(random_bytes(2)),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $em->getRepository(Company::class)->find($cid);
    }
}
