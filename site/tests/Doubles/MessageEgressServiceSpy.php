<?php

namespace App\Tests\Doubles;

use App\Service\Messaging\Dto\OutboundMessage;
use App\Service\Messaging\MessageEgressService;

final class MessageEgressServiceSpy extends MessageEgressService
{
    /** @var OutboundMessage[] */
    public array $sent = [];

    public function __construct()
    {
    }

    public function send(OutboundMessage $m): void
    {
        $this->sent[] = $m;
    }
}
