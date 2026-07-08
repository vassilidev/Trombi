<?php

namespace App\Support;

/**
 * Nettoie et valide un payload d'attributs (sortie IA ou humaine) contre la
 * taxonomie canonique. Toute valeur hors vocabulaire est ramenée à null (single)
 * ou filtrée (multi), et signalée comme "non propre".
 */
class PayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array{payload: array<string, mixed>, valid: bool} valid = propreté (PRD §6.4)
     */
    public static function normalize(array $raw): array
    {
        $payload = [];
        $valid = true;

        foreach (Taxonomy::singleAttributes() as $field => $enum) {
            $value = $raw[$field] ?? null;

            if ($value === null || $value === '') {
                $payload[$field] = null;

                continue;
            }

            if (in_array($value, $enum::values(), true)) {
                $payload[$field] = $value;
            } else {
                $payload[$field] = null;
                $valid = false; // valeur hors taxonomie
            }
        }

        $payload['age_min'] = is_numeric($raw['age_min'] ?? null) ? (int) $raw['age_min'] : null;
        $payload['age_max'] = is_numeric($raw['age_max'] ?? null) ? (int) $raw['age_max'] : null;

        foreach (Taxonomy::multiAttributes() as $field => $enum) {
            $values = is_array($raw[$field] ?? null) ? $raw[$field] : [];
            $kept = [];

            foreach ($values as $value) {
                if (in_array($value, $enum::values(), true)) {
                    $kept[] = $value;
                } else {
                    $valid = false;
                }
            }

            $payload[$field] = array_values(array_unique($kept));
        }

        $description = $raw['description_fr'] ?? null;
        $payload['description_fr'] = is_string($description) ? $description : null;

        return ['payload' => $payload, 'valid' => $valid];
    }
}
