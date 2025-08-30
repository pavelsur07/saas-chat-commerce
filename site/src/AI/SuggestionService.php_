<?php

namespace App\AI;

use App\Entity\Company\Company;
use App\Repository\Messaging\ClientRepository;
use App\Service\AI\AiFeature;
use App\Service\AI\LlmClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SuggestionService
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly ConversationContextProvider $contextProvider,
        private readonly SuggestionPromptBuilder $promptBuilder,
        private readonly ClientRepository $clients,
        #[Autowire('%ai.suggestions.model%')] private readonly string $model = 'gpt-4o-mini',
        #[Autowire('%ai.suggestions.temperature%')] private readonly float $temperature = 0.7,
        #[Autowire('%ai.suggestions.max_history%')] private readonly int $maxHistory = 12,
        #[Autowire('%ai.suggestions.max_chars%')] private readonly int $maxChars = 4000,
        #[Autowire('%ai.suggestions.timeout_seconds%')] private readonly int $timeoutSeconds = 10,
        // опционально, если сервис контекста подключён
        private readonly ?AiSuggestionContextService $contextService = null,
    ) {
    }

    /**
     * @return string[] max 4 suggestions
     */
    public function suggest(Company $company, string $clientId): array
    {
        try {
            // 1) История диалога (уже ограниченная maxHistory/maxChars)
            $context = $this->contextProvider->getContext($clientId, $this->maxHistory, $this->maxChars);

            // 2) Последняя user-реплика — нужна и для знаний, и для модели
            $lastUserText = '';
            for ($i = count($context) - 1; $i >= 0; --$i) {
                if (($context[$i]['role'] ?? '') === 'user') {
                    $lastUserText = (string) ($context[$i]['text'] ?? '');
                    break;
                }
            }
            if ('' === $lastUserText && !empty($context)) {
                // если последняя не user — возьмём текст последнего сообщения
                $last = $context[array_key_last($context)];
                $lastUserText = (string) ($last['text'] ?? '');
            }

            // 3) Бренд-контекст/знания (только если сервис доступен)
            $companyBlock = '';
            if (null !== $this->contextService) {
                // top-N знаний берём 5 — можно вынести в параметр
                $companyBlock = $this->contextService->buildBlock($company, $lastUserText, 5);
            }

            // 4) SYSTEM-блок правил + (опционально) бренд-контекст
            $system = $this->promptBuilder->buildSystemBlock(4, $companyBlock);

            // 5) Переносим историю в формат messages[] с ключом "content"
            $historyMessages = [];
            foreach ($context as $m) {
                $role = (string) ($m['role'] ?? 'user');
                $text = (string) ($m['text'] ?? '');
                if ('' === $text) {
                    continue;
                }
                $historyMessages[] = [
                    'role' => $role,
                    'content' => $text,
                ];
            }

            // 6) Если история пуста или нет последнего user — всё равно просим модель
            if ('' === $lastUserText) {
                $lastUserText = 'Сгенерируй 4 релевантные стартовые подсказки для первой реплики клиента.';
            }

            // 7) Финальные messages
            $messages = array_merge(
                [['role' => 'system', 'content' => $system]],
                $historyMessages,
                [['role' => 'user', 'content' => $lastUserText]],
            );

            // 8) Вызов LLM (обёртка LlmClientWithLogging создаст лог OK/ERROR)
            $result = $this->llm->chat([
                'company' => $company,
                'feature' => AiFeature::AGENT_SUGGEST_REPLY->value,
                'channel' => 'chat-center',
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'timeout_seconds' => $this->timeoutSeconds,
            ]);

            // 9) JSON ответа модели
            $content = (string) ($result['content'] ?? '');
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $list = (array) ($data['suggestions'] ?? []);
            $list = array_values(array_filter(
                array_map(static fn ($s) => trim((string) $s), $list),
                static fn ($s) => '' !== $s
            ));

            return array_slice($list, 0, 4);
        } catch (\Throwable $e) {
            // MVP: мягкий фоллбек (лог ошибки уже записан в LlmClientWithLogging)
            new \DomainException($e);
            return [];
        }
    }
}
