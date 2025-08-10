<?php

namespace App\Tests\Controller\Api;

use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Entity\Messaging\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ClientControllerTest extends WebTestCase
{
    private function loginWithCompany(WebTestCase $client, string $email, string $companyName): Company
    {
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $company = $em->getRepository(Company::class)->findOneBy(['name' => $companyName]);

        $this->assertNotNull($user, 'Пользователь не найден');
        $this->assertNotNull($company, 'Компания не найдена');

        $client->loginUser($user);

        $session = self::getContainer()->get('session.factory')->createSession();
        $session->set('active_company_id', $company->getId());
        $session->save();

        $client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId()));

        return $company;
    }

    public function testClientListReturnsExpectedData(): void
    {
        $client = static::createClient();

        $company = $this->loginWithCompany($client, 'admin@example.com', 'Тестовая компания');

        $client->request('GET', '/api/clients');
        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        // Проверим, что хотя бы один клиент в компании
        $this->assertGreaterThan(0, count($data), 'Должен быть хотя бы один клиент');

        // Проверим поля конкретного клиента
        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('channel', $first);
    }

    public function testMissingActiveCompanyReturnsError(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        $client->loginUser($user);

        // Не устанавливаем active_company_id
        $client->request('GET', '/api/clients');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testEmptyClientListReturnsEmptyArray(): void
    {
        $client = static::createClient();

        // Предположим, у нас есть "Пустая компания"
        $company = $this->loginWithCompany($client, 'admin@example.com', 'Пустая компания');

        // Убедимся, что у неё нет клиентов
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $clients = $em->getRepository(Client::class)->findBy(['company' => $company]);
        $this->assertCount(0, $clients);

        // Теперь делаем запрос
        $client->request('GET', '/api/clients');
        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(0, $data, 'Ожидается пустой список клиентов');
    }
}
