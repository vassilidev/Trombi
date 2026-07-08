<?php

namespace App\Services\Calibration;

use App\Support\Taxonomy;

/**
 * Compare un payload IA à un payload humain (gold), champ par champ.
 * Mesure par type (PRD §6.2) : enums = match exact, âge = chevauchement de
 * fourchettes (tolérance 5 ans), listes = indice de Jaccard.
 */
class AgreementScorer
{
    private const AGE_TOLERANCE = 5;

    private const JACCARD_THRESHOLD = 0.5;

    /**
     * @param  array<string, mixed>  $human
     * @param  array<string, mixed>  $ai
     * @return array{per_field: array<string, bool>, scores: array<string, float>, overall: float}
     */
    public function compare(array $human, array $ai): array
    {
        $perField = [];
        $scores = [];

        foreach (array_keys(Taxonomy::singleAttributes()) as $field) {
            $score = ($human[$field] ?? null) === ($ai[$field] ?? null) ? 1.0 : 0.0;
            $scores[$field] = $score;
            $perField[$field] = $score === 1.0;
        }

        $ageScore = $this->ageScore($human, $ai);
        $scores['age'] = $ageScore;
        $perField['age'] = $ageScore === 1.0;

        foreach (array_keys(Taxonomy::multiAttributes()) as $field) {
            $jaccard = $this->jaccard(
                is_array($human[$field] ?? null) ? $human[$field] : [],
                is_array($ai[$field] ?? null) ? $ai[$field] : [],
            );
            $scores[$field] = $jaccard;
            $perField[$field] = $jaccard >= self::JACCARD_THRESHOLD;
        }

        $overall = count($scores) > 0 ? array_sum($scores) / count($scores) : 0.0;

        return ['per_field' => $perField, 'scores' => $scores, 'overall' => round($overall, 4)];
    }

    /**
     * @param  array<string, mixed>  $human
     * @param  array<string, mixed>  $ai
     */
    private function ageScore(array $human, array $ai): float
    {
        $hMin = $human['age_min'] ?? null;
        $hMax = $human['age_max'] ?? null;
        $aMin = $ai['age_min'] ?? null;
        $aMax = $ai['age_max'] ?? null;

        if ($hMin === null && $hMax === null && $aMin === null && $aMax === null) {
            return 1.0;
        }

        if ($hMin === null || $hMax === null || $aMin === null || $aMax === null) {
            return 0.0;
        }

        // Chevauchement des fourchettes...
        if ($hMax >= $aMin && $hMin <= $aMax) {
            return 1.0;
        }

        // ... ou bornes proches (tolérance).
        return abs($hMin - $aMin) <= self::AGE_TOLERANCE && abs($hMax - $aMax) <= self::AGE_TOLERANCE
            ? 1.0
            : 0.0;
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private function jaccard(array $a, array $b): float
    {
        $a = array_unique($a);
        $b = array_unique($b);

        if ($a === [] && $b === []) {
            return 1.0;
        }

        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union === 0 ? 1.0 : round($intersection / $union, 4);
    }
}
