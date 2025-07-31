<?php

namespace App\Service;

// src/Service/TelegramService.php
class TelegramService
{
    public function validateToken(string $token): bool
    {
        $response = $this->sendTelegramRequest($token, 'getMe', []);

        return isset($response['ok']) && $response['ok'] === true;
    }

    public function setWebhook(string $token, string $webhookUrl): bool
    {
        $response = $this->sendTelegramRequest($token, 'setWebhook', ['url' => $webhookUrl]);

        return isset($response['ok']) && $response['ok'] === true;
    }

    private function sendTelegramRequest(string $token, string $method, array $params): array
    {
        $url = "https://api.telegram.org/bot{$token}/{$method}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \RuntimeException("Ошибка CURL: $error");
        }

        $data = json_decode($result, true);

        if (!isset($data['ok']) || $data['ok'] !== true) {
            throw new \RuntimeException('Ошибка Telegram API: ' . ($data['description'] ?? 'unknown'));
        }

        return $data;
    }
}
