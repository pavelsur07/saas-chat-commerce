<?php

namespace App\Service;

// src/Service/TelegramService.php

class TelegramService
{
    public function validateToken(string $token): bool
    {
        $response = @file_get_contents("https://api.telegram.org/bot{$token}/getMe");
        return $response && json_decode($response, true)['ok'] ?? false;
    }

    public function setWebhook(string $token, string $webhookUrl): bool
    {
        $url = "https://api.telegram.org/bot{$token}/setWebhook";
        $data = http_build_query(['url' => $webhookUrl]);

        $options = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded", 'content' => $data]];
        $context = stream_context_create($options);

        $response = file_get_contents($url, false, $context);
        return $response && json_decode($response, true)['ok'] ?? false;
    }
}
