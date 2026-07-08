<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TypePercu: string
{
    use HasOptions;

    case Europeen = 'europeen';
    case Afro = 'afro';
    case Maghrebin = 'maghrebin';
    case MoyenOriental = 'moyen_oriental';
    case AsiatiqueEst = 'asiatique_est';
    case AsiatiqueSud = 'asiatique_sud';
    case Latino = 'latino';
    case Metis = 'metis';
    case Autre = 'autre';
}
