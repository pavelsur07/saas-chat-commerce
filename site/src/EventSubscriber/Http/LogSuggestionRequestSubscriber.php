<?php

namespace App\EventSubscriber\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LogSuggestionRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 1000],
            KernelEvents::RESPONSE => ['onResponse', -1000],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $req = $event->getRequest();
        $path = $req->getPathInfo();

        // Логируем только /api/suggestions...
        if (!str_starts_with($path, '/api/suggestions')) {
            return;
        }

        // Читаем тело запроса аккуратно
        $content = $req->getContent();
        // Чтобы логи не «раздувать» — обрежем до 2KB
        if (strlen($content) > 2048) {
            $content = substr($content, 0, 2048).'…[truncated]';
        }

        // Возьмём несколько полезных заголовков
        $headers = [
            'content-type' => $req->headers->get('content-type'),
            'cookie' => $req->headers->get('cookie') ? '[present]' : null,
            'authorization' => $req->headers->has('authorization') ? '[present]' : null,
            'user-agent' => $req->headers->get('user-agent'),
            'x-forwarded-for' => $req->headers->get('x-forwarded-for'),
        ];

        $this->logger->info('SUGGESTIONS_REQUEST', [
            'method' => $req->getMethod(),
            'path' => $path,
            'query' => $req->query->all(),
            'headers' => array_filter($headers, fn ($v) => null !== $v),
            'body' => $content ?: null,
        ]);
    }

    public function onResponse(ResponseEvent $event): void
    {
        $req = $event->getRequest();
        $path = $req->getPathInfo();

        if (!str_starts_with($path, '/api/suggestions')) {
            return;
        }

        $res = $event->getResponse();

        $this->logger->info('SUGGESTIONS_RESPONSE', [
            'status' => $res->getStatusCode(),
        ]);
    }
}
