<?php

namespace App\Controller;

use App\Entity\TelegramBot;
use App\Form\TelegramBotType;
use App\Service\CompanyContextService;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('telegram/bots')]
class TelegramBotController extends AbstractController
{
    public function __construct(
        private TelegramService $telegramService,
        private CompanyContextService $companyContext,
        private EntityManagerInterface $em
    ) {}

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

    #[Route('/create', name: 'telegram_bot.create')]
    public function create(Request $request): Response
    {
        $company = $this->companyContext->getCompany();
        $bot = new TelegramBot(Uuid::uuid4()->toString(), $company);

        $form = $this->createForm(TelegramBotType::class, $bot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->telegramService->validateToken($bot->getToken())) {
                $this->addFlash('danger', 'Неверный токен Telegram');
            } else {
                $webhookUrl = $this->generateUrl('telegram.webhook', [
                    'token' => $bot->getToken()
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $this->telegramService->setWebhook($bot->getToken(), $webhookUrl);
                $bot->setWebhookUrl($webhookUrl);

                $this->em->persist($bot);
                $this->em->flush();

                return $this->redirectToRoute('telegram_bot.index');
            }
        }

        return $this->render('telegram_bot/form.html.twig', [
            'form' => $form->createView(),
            'isEdit' => false,
        ]);
    }

    // + edit() и delete() при необходимости
}
