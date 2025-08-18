<?php

namespace App\Service\Company;

use App\Entity\Company\Company;
use App\Entity\Company\User;
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
        if ($this->currentCompany) {
            return $this->currentCompany;
        }

        $session = $this->requestStack->getSession();
        $companyId = $session->get('active_company_id');
        if (!$companyId) {
            return null;
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $userCompany = $this->userCompanyRepository->findOneBy([
            'user' => $user,
            'company' => $companyId,
        ]);

        return $this->currentCompany = $userCompany?->getCompany();
    }

    public function setCompany(Company $company): void
    {
        $session = $this->requestStack->getSession();
        $session->set('active_company_id', $company->getId());
        $this->currentCompany = $company;
    }

    public function getCurrentCompanyIdOrThrow()
    {
    }
}
