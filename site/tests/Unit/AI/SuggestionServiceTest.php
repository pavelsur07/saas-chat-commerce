<?php

namespace App\Tests\Unit\AI;

use App\AI\ConversationContextProvider;
use App\AI\SuggestionPromptBuilder;
use App\AI\SuggestionService;
use App\Entity\Company\Company;
use App\Entity\Messaging\Client;
use App\Repository\Messaging\ClientRepository;
use App\Service\AI\LlmClient;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class SuggestionServiceTest extends TestCase
{
    public function testSuggestParsesJsonAndReturnsUpToFourItems(): void
    {
        $company = new Company(Uuid::uuid4()->toString(), 'Acme', 'acme');

        $client = new Client(Uuid::uuid4()->toString(), Client::TELEGRAM, '123', $company);
        $clientRepo = $this->createMock(ClientRepository::class);
        $clientRepo->method('find')->willReturn($client);

        $ctx = $this->createMock(ConversationContextProvider::class);
        $ctx->method('getContext')->willReturn([['role' => 'user', 'text' => 'Привет!']]);

        $llm = new class implements LlmClient {
            public function chat(array $params): array
            {
                return ['content' => json_encode(['suggestions' => ['ok1', 'ok2', 'ok3', 'ok4', 'ok5']], JSON_UNESCAPED_UNICODE)];
            }
        };

        $service = new SuggestionService(
            llm: $llm,
            contextProvider: $ctx,
            promptBuilder: new SuggestionPromptBuilder(),
            clients: $clientRepo,
            model: 'gpt-4o-mini',
            temperature: 0.7,
            maxHistory: 12,
            maxChars: 4000,
            timeoutSeconds: 10,
        );

        $result = $service->suggest($company, $client->getId());
        self::assertCount(4, $result);
        self::assertSame(['ok1', 'ok2', 'ok3', 'ok4'], $result);
    }

    public function testSuggestReturnsEmptyOnInvalidJson(): void
    {
        $company = new Company(Uuid::uuid4()->toString(), 'Acme', 'acme');
        $client = new Client(Uuid::uuid4()->toString(), Client::TELEGRAM, '123', $company);
        $clientRepo = $this->createMock(ClientRepository::class);
        $clientRepo->method('find')->willReturn($client);

        $ctx = $this->createMock(ConversationContextProvider::class);
        $ctx->method('getContext')->willReturn([]);

        $llm = new class implements LlmClient {
            public function chat(array $params): array
            {
                return ['content' => 'not a json'];
            }
        };

        $service = new SuggestionService(
            llm: $llm,
            contextProvider: $ctx,
            promptBuilder: new SuggestionPromptBuilder(),
            clients: $clientRepo,
            model: 'gpt-4o-mini',
            temperature: 0.7,
            maxHistory: 12,
            maxChars: 4000,
            timeoutSeconds: 10,
        );

        $result = $service->suggest($company, $client->getId());
        self::assertSame([], $result);
    }
}
