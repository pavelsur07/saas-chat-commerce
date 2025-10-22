<?php

namespace App\Service\Company;

use App\Entity\Company\Company;
use App\Entity\Company\User;
use App\Entity\Company\UserCompany;
use App\Repository\Company\UserCompanyRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/* use Symfony\Component\Security\Core\Security; */

class CompanyContextService
{
    private ?Company $currentCompany = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly UserCompanyRepository $userCompanyRepository,
    ) {
    }

    public function getCompany(): ?Company
    {
        if ($this->currentCompany instanceof Company) {
            return $this->currentCompany;
        }

        $session = $this->requestStack->getSession();
        $companyId = $session?->get('active_company_id');

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        if (is_string($companyId)) {
            $userCompany = $this->userCompanyRepository->findOneActiveByUserAndCompanyId($user, $companyId);
            if ($userCompany instanceof UserCompany && $userCompany->getStatus() !== UserCompany::STATUS_ACTIVE) {
                $userCompany = null;
            }

            if (!$userCompany instanceof UserCompany) {
                $userCompany = $this->userCompanyRepository->findOneBy([
                    'user' => $user,
                    'company' => $companyId,
                ]);
            }

            if ($userCompany instanceof UserCompany && $userCompany->getStatus() !== UserCompany::STATUS_ACTIVE) {
                $userCompany = null;
            }

            if ($userCompany instanceof UserCompany) {
                return $this->currentCompany = $userCompany->getCompany();
            }
        }

        $userCompany = $this->userCompanyRepository->findOneActiveByUser($user);
        if ($userCompany instanceof UserCompany && $userCompany->getStatus() !== UserCompany::STATUS_ACTIVE) {
            $userCompany = null;
        }

        if (!$userCompany instanceof UserCompany) {
            $userCompany = $this->userCompanyRepository->findOneBy([
                'user' => $user,
            ]);
        }

        if ($userCompany instanceof UserCompany && $userCompany->getStatus() !== UserCompany::STATUS_ACTIVE) {
            return null;
        }

        if (!$userCompany instanceof UserCompany) {
            return null;
        }

        $this->setCompany($userCompany->getCompany());

        return $this->currentCompany;
    }

    public function getCurrentCompany(): ?Company
    {
        return $this->getCompany();
    }

    public function getCurrentCompanyOrThrow(): Company
    {
        $company = $this->getCompany();
        if (!$company instanceof Company) {
            throw new \RuntimeException('Active company is required.');
        }

        return $company;
    }

    public function setCompany(Company $company): void
    {
        $session = $this->requestStack->getSession();
        $session?->set('active_company_id', $company->getId());
        $this->currentCompany = $company;
    }

    public function getCurrentCompanyIdOrThrow(): string
    {
        return $this->getCurrentCompanyOrThrow()->getId();
    }
}
