<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

use App\Service\AI\LlmClient;

final class LlmClientSpy implements LlmClient
{
    public int $calls = 0;
    public string $nextContent = '{"suggestions":[]}';
    /** @var array<int,array<string,mixed>> */
    public array $capturedPayloads = [];

    /** @param array<string,mixed> $payload */
    public function chat(array $payload): array
    {
        ++$this->calls;
        $this->capturedPayloads[] = $payload;

        return ['content' => $this->nextContent];
    }
}
