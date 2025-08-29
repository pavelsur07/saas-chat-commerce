<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(): Response
    {
        $this->addFlash('info', 'Welcome to your dashboard');
        throw new \RuntimeException('Boom test error');

        return $this->render('home/index.html.twig',
            [
                'controller_name' => 'DashboardController',
            ]);
    }
}
