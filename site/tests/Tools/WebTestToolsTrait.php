<?php

declare(strict_types=1);

namespace App\Tests\Tools;

use App\Entity\Company\Company;
use App\Service\Company\CompanyContextService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait WebTestToolsTrait
{
    /** Устанавливаем активную компанию в сессию/контекст */
    protected function activateCompany(ContainerInterface $container, Company $company): void
    {
        /** @var CompanyContextService $ctx */
        $ctx = $container->get(CompanyContextService::class);
        $ctx->setCompany($company);
    }

    /** Хелпер установки JSON-заголовков */
    protected function jsonHeaders(): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    }

    /** Быстрое POST JSON */
    protected function postJson(KernelBrowser $client, string $uri, array $data): void
    {
        $client->request('POST', $uri, server: $this->jsonHeaders(), content: json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
