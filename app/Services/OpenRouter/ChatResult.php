<?php

namespace App\Services\OpenRouter;

/**
 * Résultat normalisé d'un appel chat/completions OpenRouter.
 */
class ChatResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly ?int $promptTokens,
        public readonly ?int $completionTokens,
        public readonly ?float $costUsd,
        public readonly int $latencyMs,
        public readonly array $raw,
    ) {}
}
