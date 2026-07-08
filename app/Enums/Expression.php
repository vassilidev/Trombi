<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Expression: string
{
    use HasOptions;

    case Sourire = 'sourire';
    case Neutre = 'neutre';
    case Serieux = 'serieux';
    case Intense = 'intense';
    case Doux = 'doux';
    case Joyeux = 'joyeux';
}
