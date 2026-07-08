<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Pilosite: string
{
    use HasOptions;

    case Glabre = 'glabre';
    case BarbeNaissante = 'barbe_naissante';
    case BarbeCourte = 'barbe_courte';
    case BarbeFournie = 'barbe_fournie';
    case Moustache = 'moustache';
    case Bouc = 'bouc';
}
