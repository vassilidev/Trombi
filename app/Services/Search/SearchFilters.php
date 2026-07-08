<?php

namespace App\Services\Search;

use App\Enums\SigneDistinctif;
use App\Enums\Vibe;
use App\Support\Taxonomy;

/**
 * DTO de filtres extrait d'une requête FR par l'IA, normalisé contre la taxonomie.
 * Chaque attribut est ramené à une liste de valeurs autorisées (gère l'ambiguïté :
 * « méditerranéen » → europeen + latino). Cf. PRD §7.2.
 */
class SearchFilters
{
    /**
     * @param  array<string, list<string>>  $attributes  clé attribut → valeurs autorisées
     * @param  list<string>  $tagsRequired
     * @param  list<string>  $tagsExcluded
     * @param  'souple'|'stricte'  $durete
     */
    public function __construct(
        public readonly array $attributes,
        public readonly ?int $ageMin,
        public readonly ?int $ageMax,
        public readonly array $tagsRequired,
        public readonly array $tagsExcluded,
        public readonly string $durete,
        public readonly string $semanticText,
    ) {}

    /**
     * Construit le DTO depuis la sortie brute du LLM, en filtrant tout ce qui
     * n'existe pas réellement en base (vocabulaire contractuel).
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromLlm(array $raw, string $fallbackSemantic = ''): self
    {
        $attributes = [];

        foreach (Taxonomy::singleAttributes() as $field => $enum) {
            $values = self::toList($raw[$field] ?? null);
            $kept = array_values(array_filter(
                $values,
                static fn (string $v): bool => in_array($v, $enum::values(), true),
            ));

            if ($kept !== []) {
                $attributes[$field] = $kept;
            }
        }

        $tagVocabulary = [...Vibe::values(), ...SigneDistinctif::values()];

        $semantic = is_string($raw['semantic_text'] ?? null) ? trim($raw['semantic_text']) : '';

        return new self(
            attributes: $attributes,
            ageMin: is_numeric($raw['age_min'] ?? null) ? (int) $raw['age_min'] : null,
            ageMax: is_numeric($raw['age_max'] ?? null) ? (int) $raw['age_max'] : null,
            tagsRequired: self::filterTags($raw['tags_requis'] ?? null, $tagVocabulary),
            tagsExcluded: self::filterTags($raw['tags_exclus'] ?? null, $tagVocabulary),
            durete: ($raw['durete'] ?? 'souple') === 'stricte' ? 'stricte' : 'souple',
            semanticText: $semantic !== '' ? $semantic : $fallbackSemantic,
        );
    }

    /**
     * @return array<string, mixed> Forme sérialisable, stockée dans briefs.parsed_filters.
     */
    public function toArray(): array
    {
        return [
            'attributes' => $this->attributes,
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
            'tags_requis' => $this->tagsRequired,
            'tags_exclus' => $this->tagsExcluded,
            'durete' => $this->durete,
            'semantic_text' => $this->semanticText,
        ];
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private static function toList($value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        return [];
    }

    /**
     * @param  mixed  $value
     * @param  list<string>  $vocabulary
     * @return list<string>
     */
    private static function filterTags($value, array $vocabulary): array
    {
        return array_values(array_filter(
            self::toList($value),
            static fn (string $v): bool => in_array($v, $vocabulary, true),
        ));
    }
}
