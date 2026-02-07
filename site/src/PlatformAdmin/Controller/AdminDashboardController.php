<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminDashboardController extends AbstractController
{
    #[Route('/admin', name: 'platform_admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('platform_admin/dashboard.html.twig');
    }
}
