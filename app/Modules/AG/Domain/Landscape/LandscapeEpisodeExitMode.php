<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

enum LandscapeEpisodeExitMode: string
{
    case Active = 'active';

    case Recovered = 'recovered';

    case PhenomenonShift = 'phenomenon_shift';

    case Reset = 'reset';
}
