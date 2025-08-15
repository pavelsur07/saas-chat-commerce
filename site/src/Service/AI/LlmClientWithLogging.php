<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Entity\Company\User;
use App\Service\Company\CompanyContextService;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class LlmClientWithLogging implements LlmClient
{
    public function __construct(
        private readonly LlmClient $inner,
        private readonly AiPromptLogService $logService,
        private readonly ?TokenStorageInterface $tokenStorage = null,
        private readonly CompanyContextService $companyCtx,
        private readonly bool $injectCompanySystemMsg = true,
    ) {
    }

    public function chat(array $params): array
    {
        // 1) company system message (изоляция по компании)
        if ($this->injectCompanySystemMsg) {
            $params = $this->withCompanySystemMessage($params);
        }

        // 2) превью промпта для логов
        $model = (string) ($params['model'] ?? 'gpt-4o-mini');
        $feature = (string) ($params['feature'] ?? 'unknown');
        $channel = (string) ($params['channel'] ?? 'system');
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
        } finally {
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $user = $this->resolveCurrentUserOrNull();

            // лог пишем всегда — и при error тоже
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

    private function withCompanySystemMessage(array $params): array
    {
        $company = $this->companyCtx->getCompany();
        $companyId = method_exists($company, 'getId') ? (string) $company->getId() : '';
        $companyName = method_exists($company, 'getName') ? (string) $company->getName() : '';

        $systemMsg = [
            'role' => 'system',
            'content' => "You are answering strictly for company ID={$companyId}, Name=\"{$companyName}\". ".
                'Never use data or context from other companies. '.
                "If the user asks for information outside this company's scope, refuse and explain.",
        ];

        if (!empty($params['messages']) && is_array($params['messages'])) {
            $hasSystem = !empty($params['messages'][0]['role']) && 'system' === $params['messages'][0]['role'];
            if (!$hasSystem) {
                array_unshift($params['messages'], $systemMsg);
            }

            return $params;
        }

        $prompt = (string) ($params['prompt'] ?? '');
        $params['messages'] = [
            $systemMsg,
            ['role' => 'user', 'content' => $prompt],
        ];
        unset($params['prompt']);

        return $params;
    }

    private function resolveCurrentUserOrNull(): ?User
    {
        if (!$this->tokenStorage) {
            return null;
        }
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }
        $symfonyUser = $token->getUser();
        if (!$symfonyUser instanceof UserInterface) {
            return null;
        }

        return $symfonyUser instanceof User ? $symfonyUser : null;
    }
}
