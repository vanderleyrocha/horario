<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeHeatmapBuilder
{
    private int $resolution = 30;

    public function build(array $points): array
    {
        $grid = [];

        for ($x = 0; $x < $this->resolution; $x++) {
            for ($y = 0; $y < $this->resolution; $y++) {
                $grid[$x][$y] = 0;
            }
        }

        foreach ($points as $point) {

            $x = (int) floor($point->diversity * ($this->resolution - 1));
            $y = (int) floor((1 - $point->fitness) * ($this->resolution - 1));

            $x = max(0, min($this->resolution - 1, $x));
            $y = max(0, min($this->resolution - 1, $y));

            $grid[$x][$y]++;
        }

        return $grid;
    }
}
