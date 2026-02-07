<?php

namespace App\Controller;

use App\Account\Entity\Company;
use App\Account\Entity\User;
use App\Account\Repository\UserCompanyRepository;
use App\Service\Company\CompanyContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CompanySwitchController extends AbstractController
{
    #[Route('/companies', name: 'company_switch.list')]
    public function index(UserCompanyRepository $repo)
    {
        $user = $this->requireUser();
        $companies = $repo->findActiveByUser($user);

        return $this->render('company_switch/index.html.twig', ['companies' => $companies]);
    }

    #[Route('/companies/switch/{id}', name: 'company.switch')]
    public function switch(
        Company $company,
        CompanyContextService $context,
        UserCompanyRepository $repo,
        RequestStack $requestStack,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $user = $this->requireUser();
        $userCompany = $repo->findOneActiveByUserAndCompanyId($user, $company->getId());

        if (!$userCompany) {
            throw $this->createAccessDeniedException();
        }

        $repo->setDefault($userCompany);
        $entityManager->flush();

        $context->setCompany($company);

        $referer = $requestStack->getCurrentRequest()?->headers->get('referer');

        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('dashboard');
    }

    public function widget(UserCompanyRepository $repo, CompanyContextService $context): Response
    {
        $user = $this->requireUser();
        $companies = $repo->findActiveByUser($user);

        return $this->render('company_switch/widget.html.twig', [
            'companies' => $companies,
            'activeCompany' => $context->getCompany(),
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
