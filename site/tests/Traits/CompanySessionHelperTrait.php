<?php

declare(strict_types=1);

namespace App\Tests\Traits;

use App\Account\Entity\Company;
use App\Account\Entity\User;
use App\Service\Company\CompanyContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

/**
 * Общая настройка сессии для интеграционных тестов:
 * - создаёт единую сессию через session.factory
 * - прокидывает cookie в тестовый браузер
 * - кладёт Request с этой сессией в RequestStack
 * - логинит пользователя под firewall "main"
 * - выставляет активную компанию через CompanyContextService
 */
trait CompanySessionHelperTrait
{
    protected function loginAndActivateCompany(
        KernelBrowser $browser,
        User $user,
        Company $company,
        EntityManagerInterface $em,
    ): void {
        $container = static::getContainer();

        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = $container->get('session.factory');
        $session = $sessionFactory->createSession();
        $session->start();

        // Прокидываем сессию в браузер и стек запросов
        $browser->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        $request = new Request();
        $request->setSession($session);
        $container->get('request_stack')->push($request);

        // Логинимся под нужным файрволом
        $browser->loginUser($user, 'main');

        /** @var CompanyContextService $ctx */
        $ctx = $container->get(CompanyContextService::class);
        $ctx->setCompany($company);

        $session->save();
    }
}
