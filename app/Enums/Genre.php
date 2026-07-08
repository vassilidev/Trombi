<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Genre: string
{
    use HasOptions;

    case Femme = 'femme';
    case Homme = 'homme';
    case NonBinaire = 'non_binaire';
    case Androgyne = 'androgyne';
}
