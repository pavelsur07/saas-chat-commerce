<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CrmController extends AbstractController
{
    #[Route('/crm', name: 'crm_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('crm/index.html.twig');
    }

    #[Route('/crm/pipelines/{id}/stages', name: 'crm_stages', methods: ['GET'])]
    public function stages(string $id): Response
    {
        return $this->render('crm/stages.html.twig', ['pipelineId' => $id]);
    }
}
