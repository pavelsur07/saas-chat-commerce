<?php

declare(strict_types=1);

namespace App\Service\Messaging;

use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\Pipeline\MessageMiddlewareInterface;

final class MessagePipeline
{
    /** @var MessageMiddlewareInterface[] */
    private array $middlewares;

    /**
     * @param iterable<MessageMiddlewareInterface> $middlewares
     */
    public function __construct(iterable $middlewares)
    {
        $this->middlewares = is_array($middlewares)
            ? $middlewares
            : iterator_to_array($middlewares, false);
    }

    public function handle(InboundMessage $m): void
    {
        $runner = array_reduce(
            array_reverse($this->middlewares),
            fn ($next, MessageMiddlewareInterface $mw) => fn (InboundMessage $msg) => $mw($msg, $next),
            fn (InboundMessage $msg) => null
        );
        $runner($m);
    }
}
