<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Phototype (échelle Fitzpatrick I à VI), attribut perçu.
 */
enum Carnation: string
{
    use HasOptions;

    case I = 'I';
    case II = 'II';
    case III = 'III';
    case IV = 'IV';
    case V = 'V';
    case VI = 'VI';
}
