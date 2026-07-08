<?php

namespace App\Services\OpenRouter;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client mince pour OpenRouter (compatible OpenAI). Une seule clé, plusieurs usages :
 * vision, embeddings, parsing de requête. Cf. PRD §3.
 */
class OpenRouterClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly string $defaultVisionModel,
        private readonly string $defaultEmbeddingModel,
        private readonly int $embeddingDimensions,
    ) {}

    /**
     * Appel chat/completions. Les messages suivent le format OpenAI.
     * On demande à OpenRouter d'inclure le coût réel dans la réponse.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options  ex: ['temperature' => 0, 'response_format' => [...]]
     */
    public function chat(array $messages, ?string $model = null, array $options = []): ChatResult
    {
        $model ??= $this->defaultVisionModel;

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'usage' => ['include' => true],
        ], $options);

        $start = hrtime(true);
        $response = $this->request()->post('/chat/completions', $payload);
        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

        $response->throw();
        $data = $response->json();

        return new ChatResult(
            content: $data['choices'][0]['message']['content'] ?? '',
            model: $data['model'] ?? $model,
            promptTokens: $data['usage']['prompt_tokens'] ?? null,
            completionTokens: $data['usage']['completion_tokens'] ?? null,
            costUsd: isset($data['usage']['cost']) ? (float) $data['usage']['cost'] : null,
            latencyMs: $latencyMs,
            raw: $data,
        );
    }

    /**
     * Embedding d'un texte. Même modèle à l'ingestion et à la recherche (PRD §3).
     *
     * @return array<int, float>
     */
    public function embed(string $text, ?string $model = null): array
    {
        $response = $this->request()->post('/embeddings', [
            'model' => $model ?? $this->defaultEmbeddingModel,
            'input' => $text,
        ]);

        $response->throw();
        $embedding = $response->json('data.0.embedding');

        if (! is_array($embedding) || count($embedding) !== $this->embeddingDimensions) {
            throw new RuntimeException('Embedding invalide reçu depuis OpenRouter.');
        }

        return array_map('floatval', $embedding);
    }

    /**
     * Construit un message utilisateur combinant du texte et une image (data URI).
     *
     * @return array<string, mixed>
     */
    public static function imageMessage(string $text, string $dataUri): array
    {
        return [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $text],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
            ],
        ];
    }

    private function request(): PendingRequest
    {
        if (blank($this->apiKey)) {
            throw new RuntimeException('OPENROUTER_API_KEY manquante dans le .env.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->withHeaders([
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'Casting IA',
            ])
            ->timeout(120)
            ->retry(2, 1000, throw: false);
    }
}
