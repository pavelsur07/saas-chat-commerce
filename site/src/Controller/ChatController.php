<?php

namespace App\Controller;

// src/Controller/ChatController.php

namespace App\Controller;

use App\Command\TelegramPollUpdatesCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ChatController extends AbstractController
{
    #[Route('/chat', name: 'chat_center')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('chat/index.html.twig');
    }

    #[Route('/chat/telegram_poll', name: 'telegram_poll.test')]
    public function telegramPollTest(TelegramPollUpdatesCommand $command): Response
    {
        $command->run(
            new ArrayInput([]),
            new NullOutput()
        );
        return $this->redirectToRoute('chat_center');
    }
}
