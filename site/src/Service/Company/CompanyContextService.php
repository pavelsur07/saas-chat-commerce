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

        if (is_string($companyId) && $companyId !== '') {
            $link = $this->userCompanyRepository->findOneByUserAndCompanyId($user, $companyId);
            if ($link instanceof UserCompany && $link->getStatus() === UserCompany::STATUS_ACTIVE) {
                return $this->currentCompany = $link->getCompany();
            }

            $session?->remove('active_company_id');
        }

        $ownerLink = $this->pickOwnerLink($user);
        if ($ownerLink instanceof UserCompany) {
            $this->setCompany($ownerLink->getCompany());

            return $this->currentCompany;
        }

        $membership = $this->pickGeneralLink($user);
        if ($membership instanceof UserCompany) {
            $this->setCompany($membership->getCompany());

            return $this->currentCompany;
        }

        return null;
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

    private function pickOwnerLink(User $user): ?UserCompany
    {
        $links = $this->userCompanyRepository->findActiveOwnerLinksByUser($user);
        if (count($links) === 1) {
            return $links[0];
        }

        $default = array_values(array_filter($links, static fn (UserCompany $link): bool => $link->isDefault()));
        if (count($default) === 1) {
            return $default[0];
        }

        return null;
    }

    private function pickGeneralLink(User $user): ?UserCompany
    {
        $links = $this->userCompanyRepository->findActiveByUser($user);
        if (count($links) === 1) {
            return $links[0];
        }

        $default = array_values(array_filter($links, static fn (UserCompany $link): bool => $link->isDefault()));
        if (count($default) === 1) {
            return $default[0];
        }

        return null;
    }
}
