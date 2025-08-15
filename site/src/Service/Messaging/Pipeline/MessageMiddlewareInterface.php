<?php
declare(strict_types=1);

namespace App\Service\Messaging\Pipeline;

use App\Service\Messaging\Dto\InboundMessage; // ВАЖНО: правильный use

interface MessageMiddlewareInterface
{
    public function __invoke(InboundMessage $m, callable $next): void;
}
