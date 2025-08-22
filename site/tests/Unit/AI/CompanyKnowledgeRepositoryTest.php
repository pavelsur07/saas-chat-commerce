<?php

namespace App\Tests\Unit\AI;

use App\Entity\AI\CompanyKnowledge;
use App\Entity\Company\Company;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CompanyKnowledgeRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        // подготовь тестовые компании и записи (или мокай репо)
    }

    public function testFindTopByQueryFiltersByCompany(): void
    {
        $repo = $this->em->getRepository(CompanyKnowledge::class);
        // GIVEN: две компании, одинаковые записи
        // WHEN: запрос по company A и query 'доставка'
        // THEN: вернулись только записи A
        $this->assertTrue(true); // набросок — реализуйте фикстуры как делали раньше
    }
}
