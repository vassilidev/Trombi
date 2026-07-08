<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CheveuxTexture: string
{
    use HasOptions;

    case Raide = 'raide';
    case Ondule = 'ondule';
    case Boucle = 'boucle';
    case Frise = 'frise';
    case Crepu = 'crepu';
    case Na = 'na';
}
