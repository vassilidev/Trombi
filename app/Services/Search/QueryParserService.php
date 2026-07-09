<?php

namespace App\Services\Search;

use App\Services\OpenRouter\OpenRouterClient;
use App\Services\Prompt\PromptService;

/**
 * Parse une requête FR libre en DTO de filtres, en injectant la taxonomie dans le
 * prompt : l'IA ne peut extraire que des filtres qui existent réellement en base (PRD §7.2).
 */
class QueryParserService
{
    public function __construct(
        private readonly OpenRouterClient $client,
        private readonly PromptService $prompts,
    ) {}

    public function parse(string $query): SearchFilters
    {
        $result = $this->client->chat(
            [
                ['role' => 'system', 'content' => $this->prompts->parsing()],
                ['role' => 'user', 'content' => $query],
            ],
            config('services.openrouter.parsing_model'),
            [
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
            ],
        );

        $decoded = json_decode(trim($result->content), true);

        // Si le parsing casse, on retombe sur du sémantique pur avec la requête entière.
        // Puis on force les mots exacts de la taxonomie en filtres durs (filet LLM).
        return SearchFilters::fromLlm(
            is_array($decoded) ? $decoded : [],
            fallbackSemantic: $query,
        )->withKeywordFallback($query);
    }
}
