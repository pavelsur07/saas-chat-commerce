<?php

namespace App\Controller;

use App\Entity\Company\Company;
use App\Form\CompanyType;
use App\Repository\Company\CompanyRepository;
use App\Service\CompanyManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/companies')]
class CompanyCrudController extends AbstractController
{
    #[Route('/', name: 'company_index')]
    public function index(CompanyRepository $companyRepository): Response
    {
        $companies = $companyRepository->findBy(['owner' => $this->getUser()]);

        return $this->render('company_crud/index.html.twig', [
            'companies' => $companies,
        ]);
    }

    #[Route('/create', name: 'company_create')]
    public function create(Request $request, CompanyManager $companyManager, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CompanyType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $companyManager->createCompany(
                    $data['name'],
                    $data['slug'],
                    $this->getUser()
                );
            } catch (\Exception $e) {
                $this->addFlash('danger', $e->getMessage());

                return $this->redirectToRoute('company_create');
            }

            return $this->redirectToRoute('company_index');
        }

        return $this->render('company_crud/form.html.twig', [
            'form' => $form->createView(),
            'isEdit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'company_edit')]
    public function edit(Company $company, Request $request, EntityManagerInterface $em): Response
    {
        if ($company->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('company_index');
        }

        return $this->render('company_crud/form.html.twig', [
            'form' => $form->createView(),
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'company_delete', methods: ['POST'])]
    public function delete(Company $company, EntityManagerInterface $em, Request $request): Response
    {
        if ($company->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_company_'.$company->getId(), $request->request->get('_token'))) {
            $em->remove($company);
            $em->flush();
        }

        return $this->redirectToRoute('company_index');
    }
}
