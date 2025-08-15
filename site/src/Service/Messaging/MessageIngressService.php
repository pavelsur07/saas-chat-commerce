<?php

declare(strict_types=1);

namespace App\Service\Messaging;

use App\Service\Messaging\Dto\InboundMessage;

final class MessageIngressService
{
    public function __construct(private MessagePipeline $pipeline)
    {
    }

    public function accept(InboundMessage $msg): void
    {
        $this->pipeline->handle($msg);
    }
}
