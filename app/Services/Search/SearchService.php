<?php

namespace App\Services\Search;

use App\Models\Brief;
use App\Services\OpenRouter\OpenRouterClient;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Moteur hybride : pré-filtre SQL sur les attributs (contraintes dures) + ranking
 * vectoriel pgvector sur le sémantique, avec fallback anti-résultat-vide (PRD §7).
 */
class SearchService
{
    public function __construct(
        private readonly QueryParserService $parser,
        private readonly OpenRouterClient $client,
    ) {}

    /**
     * @return array{brief_id: int, filters: array<string, mixed>, relaxed: bool, results: list<array{talent_id: int, similarite: float}>}
     */
    public function search(string $query, string $sourceKind = 'chat', int $limit = 5): array
    {
        $filters = $this->parser->parse($query);
        $semantic = $filters->semanticText !== '' ? $filters->semanticText : $query;

        $vector = $this->client->embed($semantic);
        $literal = $this->vectorLiteral($vector);

        [$results, $relaxed] = $this->rankWithFallback($filters, $literal, $limit);

        $brief = $this->logBrief($query, $sourceKind, $filters, $literal, $results);

        return [
            'brief_id' => $brief->id,
            'filters' => $filters->toArray(),
            'relaxed' => $relaxed,
            'results' => $results,
        ];
    }

    /**
     * Applique le pré-filtre, relâche les contraintes une à une si souple et < K.
     *
     * @return array{0: list<array{talent_id: int, similarite: float}>, 1: bool}
     */
    private function rankWithFallback(SearchFilters $filters, string $literal, int $limit): array
    {
        $attributeKeys = array_keys($filters->attributes);

        // Tiers du plus contraint au moins contraint.
        $tiers = [
            ['attributes' => $attributeKeys, 'age' => true, 'tags' => true],
        ];

        if ($filters->durete === 'souple') {
            $tiers[] = ['attributes' => $attributeKeys, 'age' => true, 'tags' => false];
            $tiers[] = ['attributes' => array_values(array_intersect($attributeKeys, ['genre'])), 'age' => true, 'tags' => false];
            $tiers[] = ['attributes' => [], 'age' => false, 'tags' => false]; // sémantique pur
        }

        $last = [];

        // On relâche uniquement quand un tier est VIDE : on préserve un petit set de
        // bons résultats, tout en garantissant qu'on ne renvoie jamais une liste vide.
        foreach ($tiers as $index => $tier) {
            $rows = $this->rank($filters, $literal, $limit, $tier['attributes'], $tier['age'], $tier['tags']);
            $last = $rows;

            if ($rows !== []) {
                return [$rows, $index > 0];
            }
        }

        return [$last, count($tiers) > 1];
    }

    /**
     * Une passe pré-filtre + ranking vectoriel.
     *
     * @param  list<string>  $attributeKeys
     * @return list<array{talent_id: int, similarite: float}>
     */
    private function rank(
        SearchFilters $filters,
        string $literal,
        int $limit,
        array $attributeKeys,
        bool $applyAge,
        bool $applyTags,
    ): array {
        $query = DB::table('talent_profiles as p')
            ->join('talent_appearances as a', 'a.talent_id', '=', 'p.talent_id')
            ->whereNotNull('p.description_embedding')
            ->selectRaw('p.talent_id, 1 - (p.description_embedding <=> ?::vector) as similarite', [$literal]);

        foreach ($attributeKeys as $key) {
            $query->whereIn("a.{$key}", $filters->attributes[$key]);
        }

        if ($applyAge && $filters->ageMin !== null && $filters->ageMax !== null) {
            $query->where('a.age_max', '>=', $filters->ageMin)
                ->where('a.age_min', '<=', $filters->ageMax);
        }

        if ($applyTags && $filters->tagsRequired !== []) {
            $query->whereExists($this->tagSubquery($filters->tagsRequired));
        }

        if ($applyTags && $filters->tagsExcluded !== []) {
            $query->whereNotExists($this->tagSubquery($filters->tagsExcluded));
        }

        $rows = $query
            ->orderByRaw('p.description_embedding <=> ?::vector', [$literal])
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row): array => [
            'talent_id' => (int) $row->talent_id,
            'similarite' => round((float) $row->similarite, 4),
        ])->all();
    }

    /**
     * Sous-requête : le talent possède au moins un tag parmi la liste.
     *
     * @param  list<string>  $slugs
     */
    private function tagSubquery(array $slugs): \Closure
    {
        return function (Builder $sub) use ($slugs): void {
            $sub->from('talent_tag as tt')
                ->join('tags as t', 't.id', '=', 'tt.tag_id')
                ->whereColumn('tt.talent_id', 'p.talent_id')
                ->whereIn('t.slug', $slugs);
        };
    }

    /**
     * Log du brief + des matches (historique et mesure de qualité dans le temps).
     *
     * @param  list<array{talent_id: int, similarite: float}>  $results
     */
    private function logBrief(string $query, string $sourceKind, SearchFilters $filters, string $literal, array $results): Brief
    {
        $brief = Brief::create([
            'raw_text' => $query,
            'source_kind' => $sourceKind,
            'parsed_filters' => $filters->toArray(),
            'semantic_text' => $filters->semanticText,
        ]);

        DB::statement(
            'UPDATE briefs SET query_embedding = ?::vector WHERE id = ?',
            [$literal, $brief->id],
        );

        foreach ($results as $index => $result) {
            $brief->matches()->create([
                'talent_id' => $result['talent_id'],
                'score' => $result['similarite'],
                'rank' => $index + 1,
            ]);
        }

        return $brief;
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function vectorLiteral(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
