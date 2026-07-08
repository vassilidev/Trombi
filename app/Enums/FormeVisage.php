<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FormeVisage: string
{
    use HasOptions;

    case Ovale = 'ovale';
    case Rond = 'rond';
    case Carre = 'carre';
    case Rectangulaire = 'rectangulaire';
    case Coeur = 'coeur';
    case Diamant = 'diamant';
    case Triangulaire = 'triangulaire';
}
