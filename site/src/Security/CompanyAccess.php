<?php

// src/Security/CompanyAccess.php (хелпер)

namespace App\Security;

use App\Account\Entity\Company;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CompanyAccess
{
    public function __construct(private RequestStack $rs)
    {
    }

    public function getActiveCompanyId(): ?string
    {
        return $this->rs->getSession()?->get('active_company_id');
    }

    public function assertSame(Company $c): void
    {
        if ($c->getId() !== $this->getActiveCompanyId()) {
            throw new AccessDeniedHttpException('Wrong company');
        }
    }
}
