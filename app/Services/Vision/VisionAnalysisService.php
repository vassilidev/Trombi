<?php

namespace App\Services\Vision;

use App\Models\Talent;
use App\Services\OpenRouter\OpenRouterClient;
use App\Services\Prompt\PromptService;
use App\Support\PayloadNormalizer;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Analyse vision d'un visage : extraction des attributs (portrait robot) + description FR,
 * en un seul appel OpenRouter. Le vocabulaire de la taxonomie est imposé au modèle (PRD §5.3).
 */
class VisionAnalysisService
{
    public function __construct(
        private readonly OpenRouterClient $client,
        private readonly PromptService $prompts,
    ) {}

    /**
     * Version du prompt vision courant, pour tracer les runs de benchmark.
     */
    public function promptVersion(): string
    {
        return 'v'.$this->prompts->visionVersion();
    }

    /**
     * Analyse la photo principale d'un talent.
     *
     * @param  list<Talent>  $fewShot  Exemples humains corrigés à injecter (« apprend de moi »).
     */
    public function analyze(Talent $talent, ?string $model = null, array $fewShot = []): AnalysisResult
    {
        $dataUri = $this->imageDataUri($talent);

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ...$this->fewShotMessages($fewShot),
            OpenRouterClient::imageMessage(
                'Analyse ce visage et renvoie uniquement le JSON demandé.',
                $dataUri,
            ),
        ];

        $result = $this->client->chat($messages, $model, [
            'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
        ]);

        [$raw, $decoded] = $this->parseDefensively($result->content);
        $normalized = PayloadNormalizer::normalize($decoded ?? []);

        return new AnalysisResult(
            payload: $normalized['payload'],
            descriptionFr: $normalized['payload']['description_fr'] ?? null,
            isValidJson: $decoded !== null && $normalized['valid'],
            model: $result->model,
            promptTokens: $result->promptTokens,
            completionTokens: $result->completionTokens,
            costUsd: $result->costUsd,
            latencyMs: $result->latencyMs,
            rawContent: $raw,
        );
    }

    /**
     * Prompt système : rôle + forme JSON stricte + vocabulaire contractuel (éditable en base).
     */
    public function systemPrompt(): string
    {
        return $this->prompts->vision();
    }

    /**
     * Construit les messages few-shot (image + JSON humain) depuis des talents déjà qualifiés.
     *
     * @param  list<Talent>  $fewShot
     * @return array<int, array<string, mixed>>
     */
    private function fewShotMessages(array $fewShot): array
    {
        $messages = [];

        foreach ($fewShot as $example) {
            $human = $example->annotations
                ->firstWhere('source', 'human');

            if ($human === null) {
                continue;
            }

            $messages[] = OpenRouterClient::imageMessage(
                'Exemple de référence à imiter — analyse ce visage.',
                $this->imageDataUri($example),
            );
            $messages[] = [
                'role' => 'assistant',
                'content' => json_encode($human->payload, JSON_UNESCAPED_UNICODE),
            ];
        }

        return $messages;
    }

    /**
     * Strip d'éventuels backticks markdown + json_decode robuste.
     *
     * @return array{0: string, 1: array<string, mixed>|null}
     */
    private function parseDefensively(string $content): array
    {
        $raw = trim($content);
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $raw) ?? $raw;
        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        return [$raw, is_array($decoded) ? $decoded : null];
    }

    private function imageDataUri(Talent $talent): string
    {
        $photo = $talent->displayPhotoPath();

        if ($photo === null || ! Storage::disk('public')->exists($photo)) {
            throw new RuntimeException("Aucune image exploitable pour le talent {$talent->code}.");
        }

        $binary = Storage::disk('public')->get($photo);

        return 'data:image/jpeg;base64,'.base64_encode($binary);
    }
}
