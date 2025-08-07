<?php

namespace App\Service;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

// src/Service/TelegramService.php
class TelegramService
{
    /**
     * @return array<string, mixed>
     */
    public function validateToken(string $token): array
    {
        $response = $this->sendTelegramRequest($token, 'getMe', []);

        return $response['result'];
    }

    public function setWebhook(string $token, string $webhookUrl): bool
    {
        $response = $this->sendTelegramRequest($token, 'setWebhook', ['url' => $webhookUrl]);

        return isset($response['ok']) && true === $response['ok'];
    }

    public function deleteWebhook(string $token): bool
    {
        $response = $this->sendTelegramRequest($token, 'deleteWebhook', []);

        return isset($response['ok']) && true === $response['ok'];
    }

    public function sendMessage(string $token, string $chatId, string $text): void
    {
        $this->sendTelegramRequest($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }

    /**
     * Read messages from a telegram bot and return them as an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchMessages(string $token): array
    {
        $telegram = new Telegram($token, '');
        $telegram->useGetUpdatesWithoutDatabase();

        $response = Request::getUpdates();

        if (!$response->isOk()) {
            throw new \RuntimeException('Ошибка Telegram API: '.$response->getDescription());
        }

        $messages = [];

        foreach ($response->getResult() as $update) {
            $message = $update->getMessage();
            if (null !== $message) {
                $messages[] = [
                    'message_id' => $message->getMessageId(),
                    'chat_id' => $message->getChat()->getId(),
                    'text' => $message->getText(),
                ];
            }
        }

        return $messages;
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

        if (false === $result) {
            throw new \RuntimeException("Ошибка CURL: $error");
        }

        $data = json_decode($result, true);

        if (!isset($data['ok']) || true !== $data['ok']) {
            throw new \RuntimeException('Ошибка Telegram API: '.($data['description'] ?? 'unknown'));
        }

        return $data;
    }
}
