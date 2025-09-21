<?php

declare(strict_types=1);

namespace App\Tests\Crm\Api;

use App\Tests\Build\CompanyBuild;
use App\Tests\Build\CompanyUserBuild;
use App\Tests\Traits\CompanySessionHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DealsApiTest extends WebTestCase
{
    use CompanySessionHelperTrait;

    public function testMoveDeal(): void
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

        // pipeline (будут посеяны 5 этапов)
        $browser->jsonRequest('POST', '/api/crm/pipelines', ['name' => 'P']);
        self::assertResponseStatusCodeSame(201);
        $pipeline = json_decode($browser->getResponse()->getContent() ?: '[]', true);
        $pid = $pipeline['id'];

        // create deal (минимум)
        $browser->jsonRequest('POST', '/api/crm/deals', [
            'pipelineId' => $pid,
            'title' => 'D1',
        ]);
        self::assertResponseStatusCodeSame(201);
        $deal = json_decode($browser->getResponse()->getContent() ?: '[]', true);
        $did = $deal['id'];
        $fromStageId = $deal['stageId'];

        // возьмём любой другой stage
        $browser->jsonRequest('GET', "/api/crm/pipelines/{$pid}");
        $detailed = json_decode($browser->getResponse()->getContent() ?: '[]', true);
        $toStageId = null;
        foreach ($detailed['stages'] as $s) {
            if ($s['id'] !== $fromStageId) { $toStageId = $s['id']; break; }
        }
        self::assertNotNull($toStageId);

        // move
        $browser->jsonRequest('POST', "/api/crm/deals/{$did}/move", ['toStageId' => $toStageId]);
        self::assertResponseIsSuccessful();
        $moved = json_decode($browser->getResponse()->getContent() ?: '[]', true);
        self::assertSame($toStageId, $moved['stageId']);
    }
}
