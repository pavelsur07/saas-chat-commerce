<?php

declare(strict_types=1);

namespace App\Service\AI;

interface LlmClient
{
    /**
     * Универсальный чат-вызов.
     *
     * @param array{
     *   model:string,
     *   messages?:array<array{role:string, content:string}>,
     *   prompt?:string,
     *   max_tokens?:int,
     *   temperature?:float,
     *   feature?:string
     * } $params
     *
     * @return array{content:string, usage?:array{prompt_tokens?:int, completion_tokens?:int, total_tokens?:int}}
     */
    public function chat(array $params): array;
}
