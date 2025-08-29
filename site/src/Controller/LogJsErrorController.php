<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LogJsErrorController
{
    #[Route('/log-js-error', name: 'log_js_error', methods: ['POST'])]
    public function __invoke(Request $request, LoggerInterface $logger): JsonResponse
    {
        $data = json_decode($request->getContent() ?? '{}', true) ?: [];

        $fields = [
            'message' => substr((string) ($data['message'] ?? 'JS Error'), 0, 500),
            'source' => substr((string) ($data['source'] ?? ''), 0, 300),
            'lineno' => (int) ($data['lineno'] ?? 0),
            'colno' => (int) ($data['colno'] ?? 0),
            'stack' => substr((string) ($data['stack'] ?? ($data['reason'] ?? '')), 0, 4000),
            'ua' => substr((string) ($data['userAgent'] ?? $request->headers->get('User-Agent', '')), 0, 400),
            'ip' => $request->getClientIp(),
            'url' => $request->headers->get('Referer', ''),
        ];

        // Логируем как error — улетит в Telegram за счёт конфига Monolog
        $logger->error('[JS] {message}', $fields);

        return new JsonResponse(['ok' => true]);
    }
}
