<?php

namespace App\Service\Realtime;

interface RealtimePublisher
{
    public function publish(string $channel, array $payload): void;

    public function toClient(string $clientId, string $event, array $data = []): void;

    public function toCompany(string $companyId, string $event, array $data = []): void;
}
