<?php

namespace App\AI;

use App\Entity\Company\Company;
use App\Entity\Messaging\Client;
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
        private readonly ?AiSuggestionContextService $contextService = null,
    ) {}

    /**
     * @return string[] max 4 suggestions
     */
    public function suggest(Company $company, string $clientId): array
    {
        // 1) История диалога (массив реплик)
        $context = $this->contextProvider->getContext($clientId, $this->maxHistory, $this->maxChars);

        // 2) Последняя пользовательская реплика — нужна для поиска знаний
        $lastUserText = '';
        for ($i = count($context) - 1; $i >= 0; $i--) {
            if (($context[$i]['role'] ?? '') === 'user') {
                $lastUserText = (string)($context[$i]['text'] ?? '');
                break;
            }
        }
        if ($lastUserText === '' && !empty($context)) {
            $last = $context[count($context) - 1];
            $lastUserText = (string)($last['text'] ?? '');
        }

        // 3) Контекст компании (ToV/УТП/Knowledge <= 5)
        $companyBlock = '';
        if ($this->contextService && $lastUserText !== '') {
            $companyBlock = $this->contextService->buildBlock($company, $lastUserText, 5);
        }

        // 4) SYSTEM: правила + контекст бренда/знаний
        $system = "Ты помощник оператора. Верни строго валидный JSON {\"suggestions\":[...]}.\n\n"
            . $this->promptBuilder->buildSystemBlock(4, $companyBlock);

        // 5) Конвертируем историю в ChatML-сообщения (а не «простыню»)
        $messages = [
            ['role' => 'system', 'content' => $system],
        ];
        foreach ($context as $row) {
            $role = ($row['role'] ?? 'user') === 'agent' ? 'assistant' : 'user';
            $text = (string)($row['text'] ?? '');
            if ($text !== '') {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }

        // 6) Финальная инструкция — просим вернуть строго JSON (усиливаем формат)
        $messages[] = [
            'role' => 'user',
            'content' => 'Верни СТРОГО валидный JSON: {"suggestions":["...","...","...","..."]}',
        ];

        // 7) Безопасно получаем channel
        /** @var Client $client */
        $client = $this->clients->findOneByIdWithCompany($clientId, $company);
        $chanObj = $client?->getChannel();
        $channel = \is_object($chanObj) && property_exists($chanObj, 'value') ? (string)$chanObj->value : 'system';

        // 8) Вызов LLM
        $result = $this->llm->chat([
            'company'     => $company,
            'feature'     => AiFeature::AGENT_SUGGEST_REPLY->value,
            'channel'     => $channel,
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => $this->temperature,
            'max_tokens'  => 400,
            'timeout'     => $this->timeoutSeconds,
        ]);

        $items = is_array($result['suggestions'] ?? null) ? $result['suggestions'] : [];
        $items = array_values(array_filter(array_map('strval', $items)));
        return array_slice($items, 0, 4);
    }
}
