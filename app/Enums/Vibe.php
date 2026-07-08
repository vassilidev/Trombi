<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Tags cumulables de famille "vibe" (ambiance / usage casting).
 */
enum Vibe: string
{
    use HasOptions;

    case Commercial = 'commercial';
    case Editorial = 'editorial';
    case Luxe = 'luxe';
    case Streetwear = 'streetwear';
    case Corporate = 'corporate';
    case Naturel = 'naturel';
    case Edgy = 'edgy';
    case Glamour = 'glamour';
}
