<?php

namespace App\Controller;

use App\Entity\Company\Company;
use App\Repository\Company\UserCompanyRepository;
use App\Service\Company\CompanyContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class CompanySwitchController extends AbstractController
{
    #[Route('/companies', name: 'company_switch.list')]
    public function index(UserCompanyRepository $repo)
    {
        $companies = $repo->findBy(['user' => $this->getUser()]);

        return $this->render('company_switch/index.html.twig', ['companies' => $companies]);
    }

    #[Route('/companies/switch/{id}', name: 'company.switch')]
    public function switch(Company $company, CompanyContextService $context, UserCompanyRepository $repo): RedirectResponse
    {
        $userCompany = $repo->findOneBy([
            'user' => $this->getUser(),
            'company' => $company,
        ]);

        if (!$userCompany) {
            throw $this->createAccessDeniedException();
        }

        $context->setCompany($company);

        return $this->redirectToRoute('dashboard');
    }
}
