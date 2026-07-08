<?php

namespace App\Support;

use App\Enums\Carnation;
use App\Enums\CheveuxCouleur;
use App\Enums\CheveuxLongueur;
use App\Enums\CheveuxTexture;
use App\Enums\Expression;
use App\Enums\FormeVisage;
use App\Enums\Genre;
use App\Enums\Morphologie;
use App\Enums\Pilosite;
use App\Enums\SigneDistinctif;
use App\Enums\TypePercu;
use App\Enums\Vibe;
use App\Enums\YeuxCouleur;

/**
 * Source de vérité unique de la taxonomie casting.
 *
 * Sert aux trois endroits du PRD : les selects de qualification manuelle,
 * le prompt d'analyse vision, et le prompt de parsing de requête. Le vocabulaire
 * de la base est donc contractuel et injecté partout.
 */
class Taxonomy
{
    /**
     * Attributs enum à valeur unique (colonnes typées de talent_appearances).
     * Clé = nom de colonne / champ JSON, valeur = classe enum.
     *
     * @return array<string, class-string>
     */
    public static function singleAttributes(): array
    {
        return [
            'genre' => Genre::class,
            'type_percu' => TypePercu::class,
            'carnation' => Carnation::class,
            'cheveux_couleur' => CheveuxCouleur::class,
            'cheveux_longueur' => CheveuxLongueur::class,
            'cheveux_texture' => CheveuxTexture::class,
            'yeux_couleur' => YeuxCouleur::class,
            'forme_visage' => FormeVisage::class,
            'pilosite' => Pilosite::class,
            'expression' => Expression::class,
            'morphologie' => Morphologie::class,
        ];
    }

    /**
     * Attributs multi-valeurs (tags cumulables).
     * Clé = nom de champ JSON, valeur = classe enum.
     *
     * @return array<string, class-string>
     */
    public static function multiAttributes(): array
    {
        return [
            'signes_distinctifs' => SigneDistinctif::class,
            'vibe' => Vibe::class,
        ];
    }

    /**
     * Valeurs autorisées par attribut single, e.g. ['genre' => ['femme', ...]].
     *
     * @return array<string, array<int, string>>
     */
    public static function singleValues(): array
    {
        return array_map(
            static fn (string $enum): array => $enum::values(),
            self::singleAttributes(),
        );
    }

    /**
     * Valeurs autorisées par attribut multi.
     *
     * @return array<string, array<int, string>>
     */
    public static function multiValues(): array
    {
        return array_map(
            static fn (string $enum): array => $enum::values(),
            self::multiAttributes(),
        );
    }

    /**
     * Payload complet pour le front (options value/label par attribut).
     *
     * @return array{single: array<string, array<int, array{value: string, label: string}>>, multi: array<string, array<int, array{value: string, label: string}>>}
     */
    public static function forFrontend(): array
    {
        return [
            'single' => array_map(
                static fn (string $enum): array => $enum::options(),
                self::singleAttributes(),
            ),
            'multi' => array_map(
                static fn (string $enum): array => $enum::options(),
                self::multiAttributes(),
            ),
        ];
    }

    /**
     * Bloc texte listant tout le vocabulaire autorisé, injecté dans les prompts IA.
     */
    public static function promptVocabulary(): string
    {
        $lines = [];

        foreach (self::singleValues() as $attribute => $values) {
            $lines[] = "{$attribute}: ".implode(' | ', $values);
        }

        $lines[] = 'age_min / age_max: entiers (âge perçu)';

        foreach (self::multiValues() as $attribute => $values) {
            $lines[] = "{$attribute} (liste): ".implode(' | ', $values);
        }

        return implode("\n", $lines);
    }
}
