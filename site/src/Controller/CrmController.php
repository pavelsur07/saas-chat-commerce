<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CrmController extends AbstractController
{
    #[Route('/crm', name: 'crm.index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('crm/index.html.twig');
    }
}
