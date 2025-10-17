<?php

namespace App\Controller;

use App\Entity\Company\Company;
use App\Entity\WebChat\WebChatSite;
use App\Form\WebChat\WebChatSiteType;
use App\Security\CompanyAccess;
use App\Service\WebChat\WebChatSiteKeyGenerator;
use Doctrine\ORM\EntityManagerInterface as EM;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/web-chat/sites')]
class WebChatSiteController extends AbstractController
{
    #[Route('', name: 'web_chat_site_index', methods: ['GET'])]
    public function index(EM $em, CompanyAccess $guard): Response
    {
        $company = $em->getRepository(Company::class)->find($guard->getActiveCompanyId());
        if (!$company) {
            throw $this->createAccessDeniedException();
        }

        $sites = $em->getRepository(WebChatSite::class)
            ->createQueryBuilder('site')
            ->andWhere('site.company = :company')->setParameter('company', $company)
            ->orderBy('site.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('web_chat_site/index.html.twig', [
            'sites' => $sites,
        ]);
    }

    #[Route('/create', name: 'web_chat_site_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EM $em, CompanyAccess $guard, WebChatSiteKeyGenerator $keyGenerator): Response
    {
        $company = $em->getRepository(Company::class)->find($guard->getActiveCompanyId());
        if (!$company) {
            throw $this->createAccessDeniedException();
        }

        $site = new WebChatSite(
            Uuid::uuid4()->toString(),
            $company,
            '',
            $keyGenerator->generate(),
        );

        $form = $this->createForm(WebChatSiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($site);
            $em->flush();

            $this->addFlash('success', 'Сайт создан');

            return $this->redirectToRoute('web_chat_site_index');
        }

        return $this->render('web_chat_site/form.html.twig', [
            'form' => $form->createView(),
            'site' => $site,
            'isEdit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'web_chat_site_edit', methods: ['GET', 'POST'])]
    public function edit(WebChatSite $site, Request $request, EM $em, CompanyAccess $guard): Response
    {
        $guard->assertSame($site->getCompany());

        $form = $this->createForm(WebChatSiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Сайт обновлён');

            return $this->redirectToRoute('web_chat_site_index');
        }

        return $this->render('web_chat_site/form.html.twig', [
            'form' => $form->createView(),
            'site' => $site,
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'web_chat_site_delete', methods: ['POST'])]
    public function delete(WebChatSite $site, Request $request, EM $em, CompanyAccess $guard): Response
    {
        $guard->assertSame($site->getCompany());

        if ($this->isCsrfTokenValid('delete_web_chat_site_'.$site->getId(), $request->request->get('_token'))) {
            $em->remove($site);
            $em->flush();
            $this->addFlash('success', 'Сайт удалён');
        }

        return $this->redirectToRoute('web_chat_site_index');
    }

    #[Route('/{id}/toggle', name: 'web_chat_site_toggle', methods: ['POST'])]
    public function toggle(WebChatSite $site, Request $request, EM $em, CompanyAccess $guard): Response
    {
        $guard->assertSame($site->getCompany());

        if ($this->isCsrfTokenValid('toggle_web_chat_site_'.$site->getId(), $request->request->get('_token'))) {
            $site->setIsActive(!$site->isActive());
            $em->flush();

            $this->addFlash('success', $site->isActive() ? 'Сайт включён' : 'Сайт отключён');
        }

        return $this->redirectToRoute('web_chat_site_index');
    }
}
