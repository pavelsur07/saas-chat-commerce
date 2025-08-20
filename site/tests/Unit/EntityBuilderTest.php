<?php

namespace App\Tests\Unit;

use App\Entity\Messaging\Client;
use App\Tests\Build\ClientBuild;
use App\Tests\Build\CompanyBuild;
use PHPUnit\Framework\TestCase;

class EntityBuilderTest extends TestCase
{
    public function testCompanyClientBuilder()
    {
        $company = CompanyBuild::make()->build();
        $client = ClientBuild::make()->withCompany($company)->build();

        self::assertInstanceOf(Client::class, $client);
        self::assertEquals($company, $client->getCompany());
    }
}
