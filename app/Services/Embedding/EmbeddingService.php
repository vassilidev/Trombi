<?php

namespace App\Services\Embedding;

use App\Models\Talent;
use App\Models\TalentProfile;
use App\Services\OpenRouter\OpenRouterClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Construit le texte recherchable (description + tags) et l'embedde via OpenRouter,
 * puis écrit le vecteur pgvector. MÊME modèle qu'à la recherche (PRD §3).
 */
class EmbeddingService
{
    public function __construct(private readonly OpenRouterClient $client) {}

    /**
     * Embedde le profil d'un talent à partir de ses valeurs canoniques.
     */
    public function embedTalent(Talent $talent): TalentProfile
    {
        $talent->loadMissing(['appearance', 'tags']);

        $appearance = $talent->appearance;

        if ($appearance === null) {
            throw new RuntimeException("Le talent {$talent->code} n'a pas d'appearance à embedder.");
        }

        $description = $appearance->raw_analysis['description_fr'] ?? '';
        $searchable = $this->buildSearchableText($description, $talent->tags->pluck('label')->all());

        $vector = $this->client->embed($searchable);

        $profile = $talent->profile()->updateOrCreate(
            ['talent_id' => $talent->id],
            [
                'description_fr' => $description !== '' ? $description : $searchable,
                'searchable_text' => $searchable,
                'model_used' => config('services.openrouter.embedding_model'),
                'embedded_at' => now(),
            ],
        );

        $this->writeVector($talent->id, $vector);

        return $profile->refresh();
    }

    /**
     * Concatène description + libellés de tags en un texte à embedder.
     *
     * @param  array<int, string>  $tagLabels
     */
    public function buildSearchableText(string $description, array $tagLabels): string
    {
        $parts = array_filter([
            trim($description),
            $tagLabels === [] ? null : implode(', ', $tagLabels),
        ]);

        $text = implode('. ', $parts);

        return $text !== '' ? $text : 'profil sans description';
    }

    /**
     * Écrit le vecteur via SQL brut (le query builder ne gère pas le type vector).
     *
     * @param  array<int, float>  $vector
     */
    private function writeVector(int $talentId, array $vector): void
    {
        $literal = '['.implode(',', $vector).']';

        DB::statement(
            'UPDATE talent_profiles SET description_embedding = ?::vector WHERE talent_id = ?',
            [$literal, $talentId],
        );
    }
}
