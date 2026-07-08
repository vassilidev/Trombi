<?php

namespace App\Services\Vision;

/**
 * Résultat normalisé d'une analyse vision d'un visage.
 */
class AnalysisResult
{
    /**
     * @param  array<string, mixed>  $payload  Attributs nettoyés contre la taxonomie.
     */
    public function __construct(
        public readonly array $payload,
        public readonly ?string $descriptionFr,
        public readonly bool $isValidJson,
        public readonly string $model,
        public readonly ?int $promptTokens,
        public readonly ?int $completionTokens,
        public readonly ?float $costUsd,
        public readonly int $latencyMs,
        public readonly string $rawContent,
    ) {}
}
