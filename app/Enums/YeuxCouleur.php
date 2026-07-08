<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum YeuxCouleur: string
{
    use HasOptions;

    case Marron = 'marron';
    case Noisette = 'noisette';
    case Vert = 'vert';
    case Bleu = 'bleu';
    case Gris = 'gris';
    case Ambre = 'ambre';
}
