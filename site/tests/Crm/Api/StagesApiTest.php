<?php

declare(strict_types=1);

namespace App\Tests\Crm\Api;

use App\Tests\Build\CompanyBuild;
use App\Tests\Builders\Company\CompanyUserBuilder;
use App\Tests\Traits\CompanySessionHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StagesApiTest extends WebTestCase
{
    use CompanySessionHelperTrait;

    public function testCreateUpdateDeleteAndReorder(): void
    {
        $browser = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $owner = CompanyUserBuilder::aCompanyUser()
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

        // pipeline
        $browser->jsonRequest('POST', '/api/crm/pipelines', ['name' => 'PX']);
        self::assertResponseStatusCodeSame(201);
        $pipeline = json_decode($browser->getResponse()->getContent() ?: '[]', true);
        $pid = $pipeline['id'];

        // create stage (ВАЖНО: передаём position)
        $browser->jsonRequest('POST', "/api/crm/pipelines/{$pid}/stages", [
            'name' => 'Extra',
            'position' => 6,
            'probability' => 10,
            'color' => '#999',
            'isStart' => false,
            'isWon' => false,
            'isLost' => false,
            'slaHours' => 24,
        ]);
        self::assertResponseStatusCodeSame(201);
        $stage = json_decode($browser->getResponse()->getContent() ?: '[]', true);
        $sid = $stage['id'];

        // update
        $browser->jsonRequest('PATCH', "/api/crm/stages/{$sid}", ['name' => 'Extra+']);
        self::assertResponseIsSuccessful();

        // reorder (переставим созданный этап на позицию 1)
        $browser->jsonRequest('GET', "/api/crm/pipelines/{$pid}");
        $detailed = json_decode($browser->getResponse()->getContent() ?: '[]', true);
        $order = array_map(fn(array $s) => ['stageId' => $s['id'], 'position' => $s['position']], $detailed['stages']);
        foreach ($order as &$pair) if ($pair['stageId'] === $sid) { $pair['position'] = 1; }
        $browser->jsonRequest('POST', "/api/crm/pipelines/{$pid}/stages/reorder", ['order' => $order]);
        self::assertResponseIsSuccessful();

        // delete (без активных сделок)
        $browser->jsonRequest('DELETE', "/api/crm/stages/{$sid}");
        self::assertResponseStatusCodeSame(204);
    }
}
