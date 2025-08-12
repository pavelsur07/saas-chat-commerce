<?php

declare(strict_types=1);

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiClient implements LlmClient
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {
    }

    public function chat(array $params): array
    {
        $model = $params['model'] ?? 'gpt-4o-mini';
        $messages = $params['messages'] ?? [['role' => 'user', 'content' => $params['prompt'] ?? '']];
        $temperature = $params['temperature'] ?? 0.2;
        $maxTokens = $params['max_tokens'] ?? 512;

        // Если пока нет ключа — раскомментируй заглушку:
        return ['content' => '[stub] hello', 'usage' => ['total_tokens' => 0]];

        // TODO получить ключ и раскоментировать
        /*$resp = $this->http->request('POST', $this->baseUrl.'/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
            ],
            'timeout' => 60,
        ]);

        $data = $resp->toArray(false);

        $content = (string)($data['choices'][0]['message']['content'] ?? '');
        $usage = [
            'prompt_tokens'     => $data['usage']['prompt_tokens']     ?? null,
            'completion_tokens' => $data['usage']['completion_tokens'] ?? null,
            'total_tokens'      => $data['usage']['total_tokens']      ?? null,
        ];

        return ['content' => $content, 'usage' => $usage];*/
    }
}
