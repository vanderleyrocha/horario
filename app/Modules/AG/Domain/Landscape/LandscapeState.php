<?php

namespace App\Modules\AG\Domain\Landscape;

enum LandscapeState: string
{
    case Exploration = 'exploration';

    case Exploitation = 'exploitation';

    case Plateau = 'plateau';

    case PrematureConvergence = 'premature_convergence';

    case Chaotic = 'chaotic';
}
