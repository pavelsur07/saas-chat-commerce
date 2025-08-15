<?php
declare(strict_types=1);

namespace App\Service\Messaging;

use App\Service\Messaging\Dto\InboundMessage;
use App\Service\Messaging\Pipeline\MessageMiddlewareInterface;

final class MessagePipeline
{
    /** @param MessageMiddlewareInterface[] $middlewares */
    public function __construct(private array $middlewares) {}

    public function handle(InboundMessage $m): void
    {
        $runner = array_reduce(
            array_reverse($this->middlewares),
            fn($next, MessageMiddlewareInterface $mw) => fn(InboundMessage $msg) => $mw($msg, $next),
            fn(InboundMessage $msg) => null
        );
        $runner($m);
    }
}
