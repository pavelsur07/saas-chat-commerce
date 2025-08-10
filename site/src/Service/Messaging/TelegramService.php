<?php
declare(strict_types=1);

namespace App\Service\Messaging;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * TelegramService (refactored)
 *
 * Цели:
 * - Централизация вызовов Telegram API (один метод apiCall).
 * - Безопасные таймауты, повторные попытки, обработка 429/5xx.
 * - Единые DTO массивы для входящих сообщений.
 * - Совместимость: публичные методы и сигнатуры сохранены.
 */
final class TelegramService
{
    private const API_BASE = 'https://api.telegram.org/bot';
    private const TIMEOUT_SEC = 8.0;
    private const RETRIES = 2;
    private const RETRY_BASE_DELAY_MS = 250;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Проверка токена (getMe)
     * @return array<string,mixed>
     */
    public function validateToken(string $token): array
    {
        return $this->apiCall($token, 'getMe', [], 'GET');
    }

    /**
     * Установка webhook
     * @return array<string,mixed>
     */
    public function setWebhook(string $token, string $webhookUrl): array
    {
        return $this->apiCall($token, 'setWebhook', [
            'url' => $webhookUrl,
            'allowed_updates' => ['message', 'callback_query'],
        ]);
    }

    /**
     * Удаление webhook
     * @return array<string,mixed>
     */
    public function deleteWebhook(string $token): array
    {
        return $this->apiCall($token, 'deleteWebhook', ['drop_pending_updates' => false]);
    }

    /**
     * Отправка сообщения
     * @return array<string,mixed>
     */
    public function sendMessage(string $token, string $chatId, string $text): array
    {
        return $this->apiCall($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text'    => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }

    /**
     * Получение новых сообщений (polling; совместимость).
     * Возвращает нормализованные сообщения: [['message_id'=>..., 'chat_id'=>..., 'text'=>..., 'date'=>...], ...]
     * @return array<int, array<string, mixed>>
     */
    public function fetchMessages(string $token): array
    {
        $updates = $this->apiCall($token, 'getUpdates', [
            'timeout' => 0,
            'allowed_updates' => ['message'],
        ], 'GET');

        $normalized = [];
        $result = $updates['result'] ?? [];
        foreach ($result as $update) {
            if (!isset($update['message'])) {
                continue;
            }
            $m = $update['message'];

            $normalized[] = [
                'message_id' => $m['message_id'] ?? null,
                'chat_id'    => $m['chat']['id'] ?? null,
                'text'       => $m['text'] ?? '',
                'date'       => $m['date'] ?? null,
                'username'   => $m['from']['username'] ?? null,
                '_raw'       => $m,
            ];
        }
        return $normalized;
    }

    /**
     * Центральная точка для вызовов Telegram API.
     * Делает до 1 + RETRIES попыток с бэкоффом, логирует ошибки.
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function apiCall(string $token, string $method, array $params = [], string $httpMethod = 'POST'): array
    {
        $url = self::API_BASE . $token . '/' . $method;
        $attempt = 0;
        $lastException = null;

        while ($attempt <= self::RETRIES) {
            try {
                $options = ['timeout' => self::TIMEOUT_SEC];

                if (strtoupper($httpMethod) === 'GET') {
                    if (!empty($params)) {
                        $options['query'] = $params;
                    }
                    $response = $this->http->request('GET', $url, $options);
                } else {
                    $options['body'] = $params;
                    $response = $this->http->request('POST', $url, $options);
                }

                $status = $response->getStatusCode();
                $body = $response->getContent(false);
                $data = json_decode($body, true);

                if (!is_array($data)) {
                    $this->log('telegram.api.invalid_json', [
                        'method' => $method, 'status' => $status, 'body' => $body
                    ]);
                    throw new \RuntimeException('Invalid JSON from Telegram');
                }

                // Telegram-style errors
                if (($data['ok'] ?? false) !== true) {
                    $description = $data['description'] ?? 'unknown';
                    $errorCode   = (int)($data['error_code'] ?? 0);

                    // 429 Too Many Requests — уважаем retry_after
                    if ($errorCode === 429 && isset($data['parameters']['retry_after'])) {
                        $retrySec = (int)$data['parameters']['retry_after'];
                        $this->sleepMs(($retrySec * 1000) + self::RETRY_BASE_DELAY_MS);
                        $attempt++;
                        continue;
                    }

                    // 5xx — пробуем повторить
                    if ($errorCode >= 500 && $attempt < self::RETRIES) {
                        $this->sleepMs(self::RETRY_BASE_DELAY_MS * (1 + $attempt));
                        $attempt++;
                        continue;
                    }

                    $this->log('telegram.api.error', [
                        'method' => $method, 'status' => $status, 'error' => $description, 'data' => $data
                    ]);
                    throw new \RuntimeException('Telegram API error: ' . $description);
                }

                return $data;
            } catch (HttpExceptionInterface|TransportExceptionInterface $e) {
                $lastException = $e;
                $this->log('telegram.http.exception', [
                    'method' => $method,
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt >= self::RETRIES) {
                    break;
                }
                $this->sleepMs(self::RETRY_BASE_DELAY_MS * (1 + $attempt));
                $attempt++;
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->log('telegram.unexpected.exception', [
                    'method' => $method,
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt >= self::RETRIES) {
                    break;
                }
                $this->sleepMs(self::RETRY_BASE_DELAY_MS * (1 + $attempt));
                $attempt++;
            }
        }

        // если дошли сюда — значит все попытки неудачны
        $message = $lastException ? $lastException->getMessage() : 'Unknown error';
        throw new \RuntimeException('Telegram API call failed: ' . $message, previous: $lastException);
    }

    private function sleepMs(int $ms): void
    {
        usleep($ms * 1000);
    }

    /**
     * Унифицированное логирование (опционально).
     * @param array<string, mixed> $context
     */
    private function log(string $event, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info('[Telegram] ' . $event, $context);
        }
    }
}
