<?php

namespace App\Channel\Controller\Webhook;

use App\Channel\Repository\ChannelRepository;
use App\Channel\Service\GatewayRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AvitoController extends AbstractController
{
    #[Route(
        '/channel/webhook/avito/{token}',
        name: 'channel.webhook.avito',
        requirements: ['token' => '.+'],
        methods: ['POST']
    )]
    public function handle(Request $request, string $token, ChannelRepository $channels, GatewayRegistry $gateways): JsonResponse
    {
        $channel = $channels->findOneByToken($token);
        if (null === $channel) {
            return new JsonResponse(['error' => 'Invalid channel'], 403);
        }

        $provider = $gateways->getProvider($channel->getType());
        if (null === $provider) {
            return new JsonResponse(['error' => 'Provider not configured'], 400);
        }

        $provider->handleWebhook($channel, $request);

        return new JsonResponse(['ok' => true]);
    }
}
