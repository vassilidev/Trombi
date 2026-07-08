<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TagFamille: string
{
    use HasOptions;

    case Vibe = 'vibe';
    case SigneDistinctif = 'signe_distinctif';
    case Categorie = 'categorie';
}
