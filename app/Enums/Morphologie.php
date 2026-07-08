<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Morphologie: string
{
    use HasOptions;

    case Mince = 'mince';
    case Athletique = 'athletique';
    case Moyen = 'moyen';
    case Rond = 'rond';
    case PlusSize = 'plus_size';
    case Inconnu = 'inconnu';
}
