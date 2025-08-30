<?php

declare(strict_types=1);

namespace App\Tests\Integration\AI;

use App\Entity\AI\CompanyKnowledge;
use App\Entity\AI\Enum\KnowledgeType;
use App\Entity\Company\Company;
use App\Service\AI\KnowledgeImportService;
use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('db')]
final class KnowledgeImportServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private KnowledgeImportService $svc;

    /** @var ObjectRepository<CompanyKnowledge> */
    private ObjectRepository $ckRepo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->svc = self::getContainer()->get(KnowledgeImportService::class);
        /** @var ObjectRepository<CompanyKnowledge> $r */
        $r = $this->em->getRepository(CompanyKnowledge::class);
        $this->ckRepo = $r;
    }

    public function testParseMdPublishAndUpdate(): void
    {
        $company = $this->makeCompany();
        $this->clearCompanyKnowledge($company);

        $md = <<<MD
## Доставка
Сроки 2–5 дней. Курьер/ПВЗ.

## Оплата
Карта, СБП.

## Возврат
14 дней.

## Поддержка
Пн–Пт 9–18 МСК.
MD;

        // parse + publish (FAQ по умолчанию)
        $items = $this->svc->parse('text/markdown', $md);
        self::assertCount(4, $items);

        $n = $this->svc->publish($company, $items, true);
        self::assertSame(4, $n);

        $all = $this->ckRepo->findBy(['company' => $company]);
        self::assertCount(4, $all);

        /** @var CompanyKnowledge|null $delivery */
        $delivery = $this->ckRepo->findOneBy(['company' => $company, 'title' => 'Доставка']);
        self::assertNotNull($delivery);
        self::assertSame('published', $delivery->getTags());
        self::assertSame(KnowledgeType::FAQ, $delivery->getType());
        self::assertStringContainsString('2–5', $delivery->getContent());

        // повторный импорт — обновление по title
        $md2 = <<<MD
## Доставка
Сроки 1–3 дня. Экспресс-доставка.

## Оплата
Карта, СБП.

## Возврат
14 дней.

## Поддержка
Пн–Пт 9–18 МСК.
MD;

        $items2 = $this->svc->parse('text/markdown', $md2);
        $n2 = $this->svc->publish($company, $items2, true);
        self::assertSame(4, $n2);

        $all2 = $this->ckRepo->findBy(['company' => $company]);
        self::assertCount(4, $all2);

        /** @var CompanyKnowledge $delivery2 */
        $delivery2 = $this->ckRepo->findOneBy(['company' => $company, 'title' => 'Доставка']);
        self::assertNotNull($delivery2);
        self::assertStringContainsString('1–3', $delivery2->getContent());
        self::assertSame('published', $delivery2->getTags());
    }

    public function testParseCsvTypesAndPublish(): void
    {
        $company = $this->makeCompany();
        $this->clearCompanyKnowledge($company);

        $csv = <<<CSV
title,content,type
Доставка,Сроки 2–5 дней,delivery
Политика,Возврат 14 дней,policy
Товар,Описание товара,product
FAQ,Общие вопросы,faq
CSV;

        $items = $this->svc->parse('text/csv', $csv);
        self::assertCount(4, $items);

        $n = $this->svc->publish($company, $items, true);
        self::assertSame(4, $n);

        /** @var CompanyKnowledge $k1 */
        $k1 = $this->ckRepo->findOneBy(['company' => $company, 'title' => 'Доставка']);
        self::assertNotNull($k1);
        self::assertSame(KnowledgeType::DELIVERY, $k1->getType());

        /** @var CompanyKnowledge $k2 */
        $k2 = $this->ckRepo->findOneBy(['company' => $company, 'title' => 'Политика']);
        self::assertNotNull($k2);
        self::assertSame(KnowledgeType::POLICY, $k2->getType());

        /** @var CompanyKnowledge $k3 */
        $k3 = $this->ckRepo->findOneBy(['company' => $company, 'title' => 'Товар']);
        self::assertNotNull($k3);
        self::assertSame(KnowledgeType::PRODUCT, $k3->getType());

        /** @var CompanyKnowledge $k4 */
        $k4 = $this->ckRepo->findOneBy(['company' => $company, 'title' => 'FAQ']);
        self::assertNotNull($k4);
        self::assertSame(KnowledgeType::FAQ, $k4->getType());

        foreach ([$k1, $k2, $k3, $k4] as $k) {
            self::assertSame('published', $k->getTags());
            self::assertNotSame('', trim($k->getContent()));
        }
    }

    // ——— helpers ———

    private function clearCompanyKnowledge(Company $company): void
    {
        /** @var CompanyKnowledge[] $rows */
        $rows = $this->ckRepo->findBy(['company' => $company]);
        foreach ($rows as $row) {
            $this->em->remove($row);
        }
        $this->em->flush();
    }

    private function makeCompany(): Company
    {
        // Сборка ТОЛЬКО вашими билдерами (без DBAL):
        $owner = CompanyUserBuild::make()
            ->withEmail('imp+'.bin2hex(random_bytes(3)).'@test.local')
            ->withRoles(['ROLE_USER'])
            ->withPassword('test')
            ->withVerified(true)
            ->build();

        $this->em->persist($owner);

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withName('Import Co')
            ->withSlug('imp-'.bin2hex(random_bytes(2)))
            ->build();

        $this->em->persist($company);
        $this->em->flush();

        self::assertInstanceOf(Company::class, $company);

        return $company;
    }
}
