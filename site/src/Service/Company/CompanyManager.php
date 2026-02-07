<?php

namespace App\Service;

namespace App\Service\Company;

use App\Account\Entity\Company;
use App\Account\Entity\User;
use App\Account\Entity\UserCompany;
use App\Event\CompanyCreatedEvent;
use App\Account\Repository\UserCompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CompanyManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $dispatcher,
        private UserCompanyRepository $userCompanies,
        private CompanyContextService $companyContext,
    ) {
    }

    public function createCompany(string $name, string $slug, User $owner): Company
    {
        // Проверка уникальности slug TODO перенести в CompanyRepository
        $existing = $this->em->getRepository(Company::class)->findOneBy(['slug' => $slug]);
        if ($existing) {
            throw new BadRequestHttpException('Компания с таким slug уже существует.');
        }

        // Создаём компанию
        $uuid = Uuid::uuid4()->toString();
        $company = new Company($uuid, $owner);
        $company->setName($name);
        $company->setSlug($slug);

        $this->em->persist($company);

        // Связываем владельца
        $userCompany = new UserCompany(Uuid::uuid4()->toString(), $owner, $company);
        $userCompany->setRole(UserCompany::ROLE_OWNER);
        $userCompany->setStatus(UserCompany::STATUS_ACTIVE);
        $this->userCompanies->setDefault($userCompany);
        $this->em->persist($userCompany);

        $this->em->flush();

        $this->companyContext->setCompany($company);

        // Диспатчим событие
        $this->dispatcher->dispatch(new CompanyCreatedEvent($company));

        return $company;
    }
}
