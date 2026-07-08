<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CheveuxCouleur: string
{
    use HasOptions;

    case Brun = 'brun';
    case Chatain = 'chatain';
    case Blond = 'blond';
    case Roux = 'roux';
    case Noir = 'noir';
    case GrisBlanc = 'gris_blanc';
    case Colore = 'colore';
    case Rase = 'rase';
}
