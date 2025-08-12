<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Entity\Company\User;

final class LlmClientWithLogging implements LlmClient
{
    public function __construct(
        private readonly LlmClient $inner,                 // настоящий клиент (OpenAiClient)
        private readonly AiPromptLogService $logService,
        private readonly ?User $securityUser = null,        // может быть null вне HTTP/без авторизации
    ) {
    }

    public function chat(array $params): array
    {
        $model = (string) ($params['model'] ?? 'gpt-4o-mini');
        $feature = (string) ($params['feature'] ?? 'unknown');
        $channel = (string) ($params['channel'] ?? 'system'); // можно пробрасывать 'web'/'telegram'

        // короткий “промпт” для логов
        $promptPreview = '';
        if (!empty($params['messages'])) {
            $userMsgs = array_values(array_filter($params['messages'], fn ($m) => ($m['role'] ?? '') === 'user'));
            $promptPreview = (string) ($userMsgs[0]['content'] ?? '');
        } else {
            $promptPreview = (string) ($params['prompt'] ?? '');
        }

        $startedAt = microtime(true);
        $status = 'ok';
        $errorMessage = null;
        $respText = '';
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

        try {
            $result = $this->inner->chat($params);
            $respText = (string) ($result['content'] ?? '');
            $usage = $result['usage'] ?? $usage;
        } catch (\Throwable $e) {
            $status = 'error';
            $errorMessage = substr($e->getMessage(), 0, 245);
            // можно rethrow, если нужно:
            // throw $e;
        } finally {
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->logService->log([
                'channel' => $channel,
                'model' => $model,
                'prompt' => $promptPreview,
                'response' => $respText,
                'promptTokens' => (int) ($usage['prompt_tokens'] ?? 0),
                'completionTokens' => (int) ($usage['completion_tokens'] ?? 0),
                'totalTokens' => (int) ($usage['total_tokens'] ?? 0),
                'latencyMs' => $latencyMs,
                'status' => $status,
                'errorMessage' => $errorMessage,
                'metadata' => ['feature' => $feature],
            ], $this->securityUser);
        }

        // если была ошибка и мы не кинули исключение, вернем пустой ответ
        if ('ok' !== $status) {
            return ['content' => '', 'usage' => $usage];
        }

        return ['content' => $respText, 'usage' => $usage];
    }
}
