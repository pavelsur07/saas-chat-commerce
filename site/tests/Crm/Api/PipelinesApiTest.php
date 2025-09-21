<?php

declare(strict_types=1);

namespace App\Tests\Crm\Api;

use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use App\Tests\Traits\CompanySessionHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PipelinesApiTest extends WebTestCase
{
    use CompanySessionHelperTrait;

    public function testCreatePipelineSeedsDefaultStagesAndShow(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $owner = CompanyUserBuild::make()
            ->withEmail('u_'.bin2hex(random_bytes(4)).'@test.io')
            ->withPassword('Passw0rd!')
            ->build();
        $em->persist($owner);

        $company = CompanyBuild::make()
            ->withOwner($owner)
            ->withSlug('c_'.bin2hex(random_bytes(4)))
            ->build();
        $em->persist($company);
        $em->flush();

        $this->loginAndActivateCompany($browser, $owner, $company, $em);

        // create
        $browser->jsonRequest('POST', '/api/crm/pipelines', ['name' => 'P1']);
        self::assertResponseStatusCodeSame(201);
        $created = json_decode($browser->getResponse()->getContent() ?: '[]', true);
        self::assertIsArray($created);
        self::assertArrayHasKey('id', $created);
        $id = $created['id'];

        // show (должно быть 5 дефолтных этапов от PipelineSeeder)
        $browser->jsonRequest('GET', "/api/crm/pipelines/{$id}");
        self::assertResponseIsSuccessful();
        $detailed = json_decode($browser->getResponse()->getContent() ?: '[]', true);
        self::assertIsArray($detailed);
        self::assertArrayHasKey('stages', $detailed);
        self::assertCount(5, $detailed['stages']);
    }
}
