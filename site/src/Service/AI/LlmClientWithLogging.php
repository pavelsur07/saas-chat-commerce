<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Entity\Company\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class LlmClientWithLogging implements LlmClient
{
    public function __construct(
        private readonly LlmClient $inner,
        private readonly AiPromptLogService $logService,
        private readonly ?TokenStorageInterface $tokenStorage = null, // может быть null в CLI
    ) {
    }

    public function chat(array $params): array
    {
        $model = (string) ($params['model'] ?? 'gpt-4o-mini');
        $feature = (string) ($params['feature'] ?? 'unknown');
        $channel = (string) ($params['channel'] ?? 'system');

        // короткий превью-промпт
        $promptPreview = '';
        if (!empty($params['messages'])) {
            $userMsgs = array_values(array_filter($params['messages'], static fn ($m) => ($m['role'] ?? '') === 'user'));
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
            // реши — нужно ли пробрасывать дальше:
            // throw $e;
        } finally {
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $user = $this->resolveCurrentUserOrNull();

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
            ], $user);
        }

        if ('ok' !== $status) {
            return ['content' => '', 'usage' => $usage];
        }

        return ['content' => $respText, 'usage' => $usage];
    }

    /**
     * Достаём текущего пользователя, если он есть и валиден.
     * Возвращаем null для CLI/анонимных/нестандартных токенов.
     */
    private function resolveCurrentUserOrNull(): ?User
    {
        // tokenStorage может быть null (например, в CLI) — безопасно возвращаем null
        if (!$this->tokenStorage) {
            return null;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        $symfonyUser = $token->getUser();

        // Анонимный пользователь — это строка 'anon.' или объект, не реализующий UserInterface
        if (!$symfonyUser instanceof UserInterface) {
            return null;
        }

        // Если у тебя собственный User implements UserInterface — вернём его.
        // Иначе попробуем извлечь доменную сущность из обёртки.
        if ($symfonyUser instanceof User) {
            return $symfonyUser;
        }

        // На случай кастомных адаптеров — добавь свою маппинг-логику здесь.
        return null;
    }
}
