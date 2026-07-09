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
     * Filet déterministe : si la requête contient un mot EXACT de la taxonomie
     * (« femme », « brun », « roux »…), on le force en filtre dur, même si le LLM
     * l'a raté. Évite qu'un « homme » remonte sur une recherche « femme ».
     * La carnation (chiffres romains I…VI) est exclue car trop ambiguë.
     */
    public function withKeywordFallback(string $query): self
    {
        $haystack = self::normalize($query);

        $attributes = $this->attributes;
        foreach (Taxonomy::singleAttributes() as $field => $enum) {
            if ($field === 'carnation') {
                continue;
            }
            $synonyms = self::SYNONYMS[$field] ?? [];
            foreach ($enum::cases() as $case) {
                $needles = [$case->value, $case->label(), ...($synonyms[$case->value] ?? [])];
                if (self::mentionsAny($haystack, $needles)) {
                    $attributes[$field] = array_values(array_unique([...($attributes[$field] ?? []), $case->value]));
                }
            }
        }

        $tags = $this->tagsRequired;
        foreach ([...Vibe::cases(), ...SigneDistinctif::cases()] as $case) {
            if (self::mentionsAny($haystack, [$case->value, $case->label()])) {
                $tags[] = $case->value;
            }
        }

        return new self(
            attributes: $attributes,
            ageMin: $this->ageMin,
            ageMax: $this->ageMax,
            tagsRequired: array_values(array_unique($tags)),
            tagsExcluded: $this->tagsExcluded,
            durete: $this->durete,
            semanticText: $this->semanticText,
        );
    }

    /**
     * Synonymes courants (langage naturel) → valeur de taxonomie. Le LLM est censé
     * les gérer mais reste faillible : ce filet garantit les cas critiques (genre).
     *
     * @var array<string, array<string, list<string>>>
     */
    private const SYNONYMS = [
        'genre' => [
            'femme' => ['fille', 'meuf', 'nana', 'dame', 'feminin', 'feminine', 'woman', 'women', 'girl'],
            'homme' => ['mec', 'gars', 'garcon', 'monsieur', 'masculin', 'man', 'men', 'boy', 'guy'],
            'non_binaire' => ['non binaire', 'nonbinaire', 'enby', 'nb'],
            'androgyne' => ['androgyne', 'androgyn'],
        ],
    ];

    /**
     * L'un des mots (valeur, libellé ou synonyme) apparaît-il comme mot entier ?
     *
     * @param  list<string>  $needles
     */
    private static function mentionsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needle = self::normalize(str_replace('_', ' ', $needle));
            if (mb_strlen($needle) < 2) {
                continue;
            }
            if (preg_match('/\b'.preg_quote($needle, '/').'\b/u', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Minuscule + sans accents, pour une comparaison robuste.
     */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return $ascii === false ? $text : $ascii;
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
