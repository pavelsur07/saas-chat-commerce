<?php

namespace App\Channel\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WidgetController extends AbstractController
{
    #[Route('/channel/widget', name: 'channel.widget', methods: ['GET'])]
    public function index(): Response
    {
        return new Response('Widget placeholder');
    }
}
