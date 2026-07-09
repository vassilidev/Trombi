<?php

namespace App\Services\Search;

use App\Support\AttributeGlossary;
use App\Support\Taxonomy;

/**
 * Score de pertinence EXPLICABLE (inspiré du reranking Regald) : combien des
 * critères demandés le profil satisfait, plutôt qu'un cosinus opaque. « femme »
 * sur une femme = 100 %. Le cosinus ne sert plus qu'au départage sémantique.
 */
class RelevanceScorer
{
    /**
     * @param  array<string, mixed>  $attributes  valeurs du portrait-robot (appearance)
     * @param  list<string>  $tagSlugs
     * @return array{score: float, cosine: float, matched: list<string>, missed: list<string>}
     */
    public function score(SearchFilters $filters, array $attributes, array $tagSlugs, float $cosine): array
    {
        $labels = $this->labels();
        $total = 0;
        $hit = 0;
        $matched = [];
        $missed = [];

        foreach ($filters->attributes as $field => $values) {
            $total++;
            $label = $labels[$field] ?? $field;
            if (in_array($attributes[$field] ?? null, $values, true)) {
                $hit++;
                $matched[] = $label;
            } else {
                $missed[] = $label;
            }
        }

        if ($filters->ageMin !== null && $filters->ageMax !== null) {
            $total++;
            $min = $attributes['age_min'] ?? null;
            $max = $attributes['age_max'] ?? null;
            if ($min !== null && $max !== null && $max >= $filters->ageMin && $min <= $filters->ageMax) {
                $hit++;
                $matched[] = 'âge';
            } else {
                $missed[] = 'âge';
            }
        }

        foreach ($filters->tagsRequired as $tag) {
            $total++;
            if (in_array($tag, $tagSlugs, true)) {
                $hit++;
                $matched[] = $labels[$tag] ?? $tag;
            } else {
                $missed[] = $labels[$tag] ?? $tag;
            }
        }

        // Aucun critère structuré (recherche purement sémantique) : on retombe sur
        // le cosinus. Sinon le score = part des critères satisfaits.
        $score = $total === 0 ? $cosine : $hit / $total;

        return [
            'score' => round($score, 4),
            'cosine' => round($cosine, 4),
            'matched' => $matched,
            'missed' => $missed,
        ];
    }

    /**
     * Libellés lisibles par clé d'attribut et par slug de valeur (pour l'explication).
     *
     * @return array<string, string>
     */
    private function labels(): array
    {
        $labels = [];
        $fe = AttributeGlossary::forFrontend();

        foreach ([...$fe['single'], ...$fe['multi']] as $attr) {
            $labels[$attr['key']] = $attr['label'];
            foreach ($attr['options'] as $option) {
                $labels[$option['value']] = $option['label'];
            }
        }

        // Sécurité : couvre les clés d'attribut même si absentes du glossaire front.
        foreach (array_keys([...Taxonomy::singleAttributes(), ...Taxonomy::multiAttributes()]) as $key) {
            $labels[$key] ??= $key;
        }

        return $labels;
    }
}
