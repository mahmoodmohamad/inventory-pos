<?php

declare(strict_types=1);

namespace App\Enums;

enum Transmission: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
    case Cvt = 'cvt';
    case Dct = 'dct';
}
