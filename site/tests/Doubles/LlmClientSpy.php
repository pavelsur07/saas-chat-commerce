<?php

declare(strict_types=1);

namespace App\Tests\Doubles;

/**
 * Шпион: считает вызовы chat(). Удобно для проверок "LLM не должен вызываться".
 */
final class LlmClientSpy
{
    public int $calls = 0;
    public string $nextContent = '{"suggestions":[]}';

    /** @var array<int,array<string,mixed>> */
    public array $capturedPayloads = [];

    /**
     * @param array<string,mixed> $payload
     *
     * @return array{content:string}
     */
    public function chat(array $payload): array
    {
        ++$this->calls;
        $this->capturedPayloads[] = $payload;

        return ['content' => $this->nextContent];
    }
}
