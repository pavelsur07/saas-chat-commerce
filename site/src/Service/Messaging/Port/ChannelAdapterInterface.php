<?php
declare(strict_types=1);

namespace App\Service\Messaging\Port;

use App\Service\Messaging\Dto\OutboundMessage;

interface ChannelAdapterInterface
{
    public function supports(string $channel): bool;
    public function send(OutboundMessage $msg): void;
}
