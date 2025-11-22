<?php

namespace App\Controller;

use App\Entity\Company\Company;
use App\Entity\Crm\CrmPipeline;
use App\Entity\Crm\CrmStage;
use App\Entity\Crm\CrmWebForm;
use App\Form\Crm\CrmWebFormType;
use App\Security\CompanyAccess;
use App\Service\Company\CompanyContextService;
use Doctrine\ORM\EntityManagerInterface as EM;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/crm/forms')]
class CrmWebFormController extends AbstractController
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly CompanyAccess $companyAccess,
    ) {
    }

    #[Route('', name: 'crm_forms_index', methods: ['GET'])]
    public function index(Request $request, EM $em): Response
    {
        $company = $this->companyContext->getCurrentCompanyOrThrow();
        $forms = $em->getRepository(CrmWebForm::class)->findForCompany($company);

        return $this->render('crm/forms/index.html.twig', [
            'forms' => $forms,
            'widgetHost' => $request->getSchemeAndHttpHost(),
        ]);
    }

    #[Route('/new', name: 'crm_forms_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EM $em): Response
    {
        $company = $this->companyContext->getCurrentCompanyOrThrow();

        $pipeline = $em->getRepository(CrmPipeline::class)
            ->createQueryBuilder('pipeline')
            ->andWhere('pipeline.company = :company')
            ->setParameter('company', $company)
            ->orderBy('pipeline.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$pipeline instanceof CrmPipeline) {
            throw $this->createNotFoundException('Для создания формы нужна хотя бы одна воронка.');
        }

        $stage = $em->getRepository(CrmStage::class)
            ->createQueryBuilder('stage')
            ->andWhere('stage.pipeline = :pipeline')
            ->setParameter('pipeline', $pipeline)
            ->orderBy('stage.position', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$stage instanceof CrmStage) {
            throw $this->createNotFoundException('В выбранной воронке нет этапов.');
        }

        $formEntity = new CrmWebForm(
            Uuid::uuid4()->toString(),
            $company,
            $pipeline,
            $stage,
            '',
            $this->generateSlug($em, $company),
            $this->generatePublicKey($em),
        );

        $form = $this->createForm(CrmWebFormType::class, $formEntity, ['company' => $company]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($formEntity);
            $em->flush();

            $this->addFlash('success', 'Форма создана');

            return $this->redirectToRoute('crm_forms_index');
        }

        return $this->render('crm/forms/form.html.twig', [
            'form' => $form->createView(),
            'crmForm' => $formEntity,
            'isEdit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'crm_forms_edit', methods: ['GET', 'POST'])]
    public function edit(CrmWebForm $crmForm, Request $request, EM $em): Response
    {
        $this->companyAccess->assertSame($crmForm->getCompany());

        $form = $this->createForm(CrmWebFormType::class, $crmForm, ['company' => $crmForm->getCompany()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Форма обновлена');

            return $this->redirectToRoute('crm_forms_index');
        }

        return $this->render('crm/forms/form.html.twig', [
            'form' => $form->createView(),
            'crmForm' => $crmForm,
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/toggle', name: 'crm_forms_toggle', methods: ['POST'])]
    public function toggle(CrmWebForm $crmForm, Request $request, EM $em): Response
    {
        $this->companyAccess->assertSame($crmForm->getCompany());

        if ($this->isCsrfTokenValid('toggle_crm_form_'.$crmForm->getId(), $request->request->get('_token'))) {
            $crmForm->setIsActive(!$crmForm->isActive());
            $em->flush();

            $this->addFlash('success', $crmForm->isActive() ? 'Форма включена' : 'Форма отключена');
        }

        return $this->redirectToRoute('crm_forms_index');
    }

    private function generatePublicKey(EM $em): string
    {
        $repository = $em->getRepository(CrmWebForm::class);

        do {
            $key = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        } while ($repository->findOneBy(['publicKey' => $key]));

        return $key;
    }

    private function generateSlug(EM $em, Company $company): string
    {
        $repository = $em->getRepository(CrmWebForm::class);

        do {
            $slug = 'form-'.rtrim(strtr(base64_encode(random_bytes(6)), '+/', '-_'), '=');
        } while ($repository->findOneBy(['company' => $company, 'slug' => $slug]));

        return $slug;
    }
}
