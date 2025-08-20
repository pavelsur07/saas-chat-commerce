<?php

declare(strict_types=1);

namespace App\Service\Messaging;

use App\Service\Messaging\Dto\OutboundMessage;
use App\Service\Messaging\Port\ChannelAdapterInterface;

class MessageEgressService
{
    /** @param iterable<ChannelAdapterInterface> $adapters */
    public function __construct(private iterable $adapters)
    {
    }

    public function send(OutboundMessage $m): void
    {
        foreach ($this->adapters as $a) {
            if ($a->supports($m->channel)) {
                $a->send($m);

                return;
            }
        }
        throw new \RuntimeException("No adapter for channel {$m->channel}");
    }
}
