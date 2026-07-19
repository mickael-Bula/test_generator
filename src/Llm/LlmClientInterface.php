<?php

declare(strict_types=1);

namespace App\Llm;

interface LlmClientInterface
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function call(array $messages, string $model): string;
}
