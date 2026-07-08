<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Tags cumulables de famille "signe_distinctif".
 */
enum SigneDistinctif: string
{
    use HasOptions;

    case TachesDeRousseur = 'taches_de_rousseur';
    case GrainDeBeaute = 'grain_de_beaute';
    case Lunettes = 'lunettes';
    case Tatouages = 'tatouages';
    case Piercings = 'piercings';
    case Fossettes = 'fossettes';
    case DentsDuBonheur = 'dents_du_bonheur';
    case Cicatrice = 'cicatrice';
}
