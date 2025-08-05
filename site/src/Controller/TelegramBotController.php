<?php

namespace App\Controller;

use App\Entity\TelegramBot;
use App\Form\TelegramBotType;
use App\Service\CompanyContextService;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/telegram/bots')]
class TelegramBotController extends AbstractController
{
    public function __construct(
        private TelegramService $telegramService,
        private CompanyContextService $companyContext,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/', name: 'telegram_bot.index')]
    public function index(): Response
    {
        $bots = $this->em->getRepository(TelegramBot::class)->findBy([
            'company' => $this->companyContext->getCompany(),
        ]);

        return $this->render('telegram_bot/index.html.twig', [
            'bots' => $bots,
        ]);
    }

    #[Route('/create', name: 'telegram_bot.create', methods: ['GET', 'POST'])]
    public function test(Request $request): Response
    {
        $company = $this->companyContext->getCompany();
        $bot = new TelegramBot(Uuid::uuid4()->toString(), $company);

        $form = $this->createForm(TelegramBotType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $token = $form->getData()['token'];
            $bot->setToken($token);
            try {
                if (!$this->telegramService->validateToken($bot->getToken())) {
                    $form->get('token')->addError(new FormError('Неверный токен Telegram'));
                }

                $webhookUrl = $this->generateUrl('telegram.webhook', [
                    'token' => $bot->getToken(),
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                // Принудительно заменить http на https
                $webhookUrl = preg_replace('#^http://#', 'https://', $webhookUrl);

                $this->telegramService->setWebhook($bot->getToken(), $webhookUrl);
                $bot->setWebhookUrl($webhookUrl);

                $this->em->persist($bot);
                $this->em->flush();

                $this->addFlash('success', 'Бот успешно создан');
            } catch (\Throwable$e) {
                $this->addFlash('danger', 'Ошибка при создании бота: '.$e->getMessage().' '.$token.' '.$webhookUrl);
            }

            return $this->redirectToRoute('telegram_bot.index');
        }

        return $this->render('telegram_bot/form.html.twig', [
            'form' => $form->createView(),
            'isEdit' => false,
        ]);
    }

    /*    #[Route('/create', name: 'telegram_bot.create', methods: ['GET', 'POST'])]
        public function create(Request $request): Response
        {
            $company = $this->companyContext->getCompany();
            $bot = new TelegramBot(Uuid::uuid4()->toString(), $company);

            $form = $this->createForm(TelegramBotType::class, $bot);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    if (!$this->telegramService->validateToken($bot->getToken())) {
                        $form->get('token')->addError(new FormError('Неверный токен Telegram'));
                    } else {
                        $webhookUrl = $this->generateUrl('telegram.webhook', [
                            'token' => $bot->getToken(),
                        ], UrlGeneratorInterface::ABSOLUTE_URL);

                        $this->telegramService->setWebhook($bot->getToken(), $webhookUrl);
                        $bot->setWebhookUrl($webhookUrl);

                        $this->em->persist($bot);
                        $this->em->flush();

                        $this->addFlash('success', 'Бот успешно создан');

                        return $this->redirectToRoute('telegram_bot.index');
                    }
                } catch (\Throwable $e) {
                    $this->addFlash('danger', 'Ошибка при создании бота: ' . $e->getMessage());
                }
            }

            return $this->render('telegram_bot/form.html.twig', [
                'form' => $form->createView(),
                'isEdit' => false,
            ]);
        }*/

    #[Route('/{id}/delete', name: 'telegram_bot.delete', methods: ['POST'])]
    public function delete(TelegramBot $bot, Request $request): Response
    {
        if ($bot->getCompany() !== $this->companyContext->getCompany()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_bot_'.$bot->getId(), $request->request->get('_token'))) {
            try {
                $this->telegramService->deleteWebhook($bot->getToken());
            } catch (\Throwable $e) {
                // Игнорируем ошибки удаления вебхука
            }

            $this->em->remove($bot);
            $this->em->flush();

            $this->addFlash('success', 'Бот удалён');
        }

        return $this->redirectToRoute('telegram_bot.index');
    }
}
