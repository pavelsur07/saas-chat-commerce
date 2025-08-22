<?php

// src/Controller/Admin/AiProfileController.php

namespace App\Controller\Admin;

use App\Entity\AI\AiCompanyProfile;
use App\Entity\Company\Company;
use App\Form\AI\AiCompanyProfileType;
use App\Security\CompanyAccess;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/* #[IsGranted('ROLE_OWNER')] */
class AiProfileController extends AbstractController
{
    #[Route('/admin/ai/profile', name: 'admin_ai_profile', methods: ['GET', 'POST'])]
    public function edit(Request $req, EM $em, CompanyAccess $guard): Response
    {
        $companyId = $guard->getActiveCompanyId();
        $company = $em->getRepository(Company::class)->find($companyId);
        if (!$company) {
            throw $this->createAccessDeniedException();
        }

        $profile = $em->getRepository(AiCompanyProfile::class)
            ->findOneBy(['company' => $company])
            ?? (function () use ($em, $company) {
                $p = new AiCompanyProfile($company);
                $em->persist($p);

                return $p;
            })();

        $form = $this->createForm(AiCompanyProfileType::class, $profile);
        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Профиль AI сохранён');

            return $this->redirectToRoute('admin_ai_profile');
        }

        return $this->render('admin/ai/profile_edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
