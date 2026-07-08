<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CheveuxLongueur: string
{
    use HasOptions;

    case Rase = 'rase';
    case Court = 'court';
    case MiLong = 'mi_long';
    case Long = 'long';
    case TresLong = 'tres_long';
}
