<?php

declare(strict_types=1);

namespace App\Tests\Integration\Registration;

use App\Entity\Company\User;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private KernelBrowser $http;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->http = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->truncateTables(['user']);
    }

    public function testNewUserHasOwnerRole(): void
    {
        $crawler = $this->http->request('GET', '/register');

        $form = $crawler->filter('form')->form([
            'registration_form[email]' => 'owner@example.com',
            'registration_form[plainPassword]' => 'secret12',
            'registration_form[agreeTerms]' => true,
        ]);
        $this->http->submit($form);

        self::assertResponseIsSuccessful();

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'owner@example.com']);
        self::assertNotNull($user);
        self::assertContains('ROLE_OWNER', $user->getRoles());
    }

    /**
     * @param list<string> $tables
     *
     * @throws Exception
     */
    private function truncateTables(array $tables): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('BEGIN');
        $conn->executeStatement('SET CONSTRAINTS ALL DEFERRED');
        foreach ($tables as $table) {
            $conn->executeStatement('TRUNCATE TABLE "'.$table.'" RESTART IDENTITY CASCADE');
        }
        $conn->executeStatement('COMMIT');
    }
}
