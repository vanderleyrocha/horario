<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

enum LandscapePhenomenon: string
{
    case Neutral = 'neutral';

    case Plateau = 'plateau';

    case LocalMinimum = 'local_minimum';

    case DeepValley = 'deep_valley';
}
