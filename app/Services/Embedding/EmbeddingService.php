<?php

namespace App\Services\Embedding;

use App\Models\Talent;
use App\Models\TalentAppearance;
use App\Models\TalentProfile;
use App\Services\OpenRouter\OpenRouterClient;
use App\Support\Taxonomy;
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
        $searchable = $this->buildSearchableText($appearance, $talent->tags->pluck('label')->all(), $description);

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
     * Texte recherchable : portrait-robot structuré (attributs + âge) + vibe/signes
     * (tags) + description libre. C'est CE texte qui est embeddé, donc un profil sans
     * description écrite reste tout de même riche et cherchable (attributs seuls).
     *
     * @param  array<int, string>  $tagLabels
     */
    public function buildSearchableText(TalentAppearance $appearance, array $tagLabels, string $description = ''): string
    {
        $segments = array_filter([
            $this->describeAttributes($appearance),
            $tagLabels === [] ? null : implode(', ', $tagLabels),
            trim($description) !== '' ? trim($description) : null,
        ]);

        $text = implode('. ', $segments);

        return $text !== '' ? $text : 'profil sans description';
    }

    /**
     * Transforme les attributs structurés en une phrase lisible (libellés FR),
     * e.g. « Homme, Europeen, Carnation II, Brun, Court, …, 30 à 35 ans ».
     */
    private function describeAttributes(TalentAppearance $appearance): ?string
    {
        $parts = [];

        foreach (Taxonomy::singleAttributes() as $field => $enum) {
            $label = $enum::tryFromValue($appearance->{$field})?->label();

            if ($label !== null) {
                $parts[] = $label;
            }
        }

        if ($appearance->age_min !== null || $appearance->age_max !== null) {
            $parts[] = trim("{$appearance->age_min} à {$appearance->age_max}").' ans';
        }

        return $parts === [] ? null : implode(', ', $parts);
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
