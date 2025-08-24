<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AI\AiCompanyProfile;
use App\Form\AI\AiCompanyProfileType;
use App\Service\Company\CompanyContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AiProfileController extends AbstractController
{
    #[Route('/admin/ai/profile', name: 'admin_ai_profile', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        CompanyContextService $companyContext,
    ): Response {
        $company = $companyContext->getCompany();
        if (!$company) {
            // Соответствует общей логике проекта: 403 при отсутствии активной компании
            throw new AccessDeniedHttpException('Active company not selected');
        }

        // Профиль один-к-одному с компанией
        $repo = $em->getRepository(AiCompanyProfile::class);
        $profile = $repo->findOneBy(['company' => $company]);

        if (!$profile) {
            // ВАЖНО: создаём через конструктор, setCompany() в сущности нет
            $profile = new AiCompanyProfile($company);
            $em->persist($profile);
        }

        // Работаем только через форму — без ручного чтения $_POST
        $form = $this->createForm(AiCompanyProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Профиль AI сохранён');

            // PRG-паттерн
            return $this->redirectToRoute('admin_ai_profile');
        }

        return $this->render('admin/ai/profile_edit.html.twig', [
            'form' => $form->createView(),
            'profile' => $profile,
        ]);
    }
}
