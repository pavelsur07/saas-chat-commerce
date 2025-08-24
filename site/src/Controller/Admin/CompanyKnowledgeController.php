<?php

// src/Controller/Admin/CompanyKnowledgeController.php

namespace App\Controller\Admin;

use App\Entity\AI\CompanyKnowledge;
use App\Entity\AI\Enum\KnowledgeType;
use App\Entity\Company\Company;
use App\Form\AI\CompanyKnowledgeType;
use App\Security\CompanyAccess;
use Doctrine\ORM\EntityManagerInterface as EM;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/* #[IsGranted('ROLE_OWNER')] */
#[Route('/admin/ai/knowledge')]
class CompanyKnowledgeController extends AbstractController
{
    #[Route('', name: 'admin_ai_knowledge_index', methods: ['GET'])]
    public function index(EM $em, CompanyAccess $guard): Response
    {
        $company = $em->getRepository(Company::class)->find($guard->getActiveCompanyId());
        if (!$company) {
            throw $this->createAccessDeniedException();
        }

        $items = $em->getRepository(CompanyKnowledge::class)
            ->createQueryBuilder('k')
            ->andWhere('k.company = :c')->setParameter('c', $company)
            ->orderBy('k.createdAt', 'DESC')
            ->getQuery()->getResult();

        return $this->render('admin/ai/knowledge_index.html.twig', [
            'items' => $items,
        ]);
    }

    #[Route('/create', name: 'admin_ai_knowledge_create', methods: ['GET', 'POST'])]
    public function create(Request $req, EM $em, CompanyAccess $guard): Response
    {
        $company = $em->getRepository(Company::class)->find($guard->getActiveCompanyId());
        if (!$company) {
            throw $this->createAccessDeniedException();
        }

        $item = new CompanyKnowledge(Uuid::uuid4()->toString(), $company, KnowledgeType::FAQ, '', '');
        $form = $this->createForm(CompanyKnowledgeType::class, $item);
        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Запись добавлена');

            return $this->redirectToRoute('admin_ai_knowledge_index');
        }

        return $this->render('admin/ai/knowledge_form.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/edit', name: 'admin_ai_knowledge_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $req, EM $em, CompanyAccess $guard): Response
    {
        $item = $em->getRepository(CompanyKnowledge::class)->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }
        $guard->assertSame($item->getCompany());

        $form = $this->createForm(CompanyKnowledgeType::class, $item);
        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Запись обновлена');

            return $this->redirectToRoute('admin_ai_knowledge_index');
        }

        return $this->render('admin/ai/knowledge_form.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/delete', name: 'admin_ai_knowledge_delete', methods: ['POST'])]
    public function delete(string $id, Request $req, EM $em, CompanyAccess $guard): Response
    {
        $item = $em->getRepository(CompanyKnowledge::class)->find($id);
        if ($item) {
            $guard->assertSame($item->getCompany());
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Удалено');
        }

        return $this->redirectToRoute('admin_ai_knowledge_index');
    }
}
