<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeMemory
{
    /*
    ---------------------------------------------------------
    Histórico de estados do landscape
    ---------------------------------------------------------
    */

    private array $history = [];

    private int $maxHistory = 50;

    /*
    ---------------------------------------------------------
    Pontos do landscape (para heatmap)
    ---------------------------------------------------------
    */

    private array $points = [];

    private int $maxPoints = 5000;

    /*
    ---------------------------------------------------------
    Registrar estado detectado
    ---------------------------------------------------------
    */

    public function record(LandscapeState $state): void
    {
        $this->history[] = $state;

        if (count($this->history) > $this->maxHistory) {
            array_shift($this->history);
        }
    }

    /*
    ---------------------------------------------------------
    Registrar ponto no landscape
    ---------------------------------------------------------
    */

    public function recordPoint(LandscapePoint $point): void
    {
        $this->points[] = $point;

        if (count($this->points) > $this->maxPoints) {
            array_shift($this->points);
        }
    }

    /*
    ---------------------------------------------------------
    Retornar pontos registrados
    ---------------------------------------------------------
    */

    public function points(): array
    {
        return $this->points;
    }

    /*
    ---------------------------------------------------------
    Duração de plateau
    ---------------------------------------------------------
    */

    public function plateauDuration(): int
    {
        $count = 0;

        for ($i = count($this->history) - 1; $i >= 0; $i--) {

            if ($this->history[$i] === LandscapeState::Plateau) {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }

    /*
    ---------------------------------------------------------
    Tendência de convergência prematura
    ---------------------------------------------------------
    */

    public function convergenceTrend(): float
    {
        $size = count($this->history);

        if ($size < 2) {
            return 0;
        }

        $premature = 0;

        foreach ($this->history as $state) {

            if ($state === LandscapeState::PrematureConvergence) {
                $premature++;
            }
        }

        return $premature / $size;
    }

    /*
    ---------------------------------------------------------
    Estatísticas do landscape
    ---------------------------------------------------------
    */

    public function landscapeDensity(): float
    {
        $n = count($this->points);

        if ($n < 2) {
            return 0;
        }

        $diversities = array_map(fn ($p) => $p->diversity, $this->points);

        $min = min($diversities);
        $max = max($diversities);

        if ($max - $min == 0) {
            return 1;
        }

        return $n / ($max - $min);
    }

    /*
    ---------------------------------------------------------
    Reset memória (usado após restart do solver)
    ---------------------------------------------------------
    */

    public function reset(): void
    {
        $this->history = [];
        $this->points = [];
    }
}
