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

    /**
     * @var LandscapeTrajectorySnapshot[]
     */
    private array $trajectory = [];

    private int $maxTrajectory = 120;

    private ?LandscapeEpisode $currentEpisode = null;

    private ?LandscapeEpisode $lastCompletedEpisode = null;

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

    public function recordTrajectory(LandscapeTrajectorySnapshot $snapshot): void
    {
        $this->trajectory[] = $snapshot;

        if (count($this->trajectory) > $this->maxTrajectory) {
            array_shift($this->trajectory);
        }
    }

    public function lastTrajectory(): ?LandscapeTrajectorySnapshot
    {
        if ($this->trajectory === []) {
            return null;
        }

        return $this->trajectory[array_key_last($this->trajectory)];
    }

    public function currentEpisode(): ?LandscapeEpisode
    {
        return $this->currentEpisode;
    }

    public function lastCompletedEpisode(): ?LandscapeEpisode
    {
        return $this->lastCompletedEpisode;
    }

    public function previewEpisode(
        LandscapePhenomenon $phenomenon,
        int $generation,
        float $confidence,
        float $depthScore,
        float $populationTurnover,
        float $eliteSimilarity,
        bool $bestSignatureChanged
    ): LandscapeEpisode {
        if (
            $this->currentEpisode !== null &&
            $this->currentEpisode->active &&
            $this->currentEpisode->phenomenon === $phenomenon
        ) {
            return $this->currentEpisode->advance(
                generation: $generation,
                confidence: $confidence,
                depthScore: $depthScore,
                populationTurnover: $populationTurnover,
                eliteSimilarity: $eliteSimilarity,
                bestSignatureChanged: $bestSignatureChanged
            );
        }

        return LandscapeEpisode::start(
            phenomenon: $phenomenon,
            generation: $generation,
            confidence: $confidence,
            depthScore: $depthScore,
            populationTurnover: $populationTurnover,
            eliteSimilarity: $eliteSimilarity,
            bestSignatureChanged: $bestSignatureChanged
        );
    }

    public function recordEpisode(
        LandscapePhenomenon $phenomenon,
        int $generation,
        float $confidence,
        float $depthScore,
        float $populationTurnover,
        float $eliteSimilarity,
        bool $bestSignatureChanged
    ): LandscapeEpisode {
        if (
            $this->currentEpisode !== null &&
            $this->currentEpisode->active &&
            $this->currentEpisode->phenomenon === $phenomenon
        ) {
            $this->currentEpisode = $this->currentEpisode->advance(
                generation: $generation,
                confidence: $confidence,
                depthScore: $depthScore,
                populationTurnover: $populationTurnover,
                eliteSimilarity: $eliteSimilarity,
                bestSignatureChanged: $bestSignatureChanged
            );

            return $this->currentEpisode;
        }

        if ($this->currentEpisode !== null && $this->currentEpisode->active) {
            $exitMode = $phenomenon === LandscapePhenomenon::Neutral
                ? LandscapeEpisodeExitMode::Recovered
                : LandscapeEpisodeExitMode::PhenomenonShift;

            $this->lastCompletedEpisode = $this->currentEpisode->close($exitMode);
        }

        $this->currentEpisode = LandscapeEpisode::start(
            phenomenon: $phenomenon,
            generation: $generation,
            confidence: $confidence,
            depthScore: $depthScore,
            populationTurnover: $populationTurnover,
            eliteSimilarity: $eliteSimilarity,
            bestSignatureChanged: $bestSignatureChanged
        );

        return $this->currentEpisode;
    }

    public function bestDeltaWindow(int $window = 5): float
    {
        return $this->windowedDelta('bestFitness', $window);
    }

    public function avgDeltaWindow(int $window = 5): float
    {
        return $this->windowedDelta('avgFitness', $window);
    }

    public function lastFitnessDelta(): float
    {
        $count = count($this->points);

        if ($count < 2) {
            return 0.0;
        }

        return $this->points[$count - 1]->fitness - $this->points[$count - 2]->fitness;
    }

    public function recentFitnessSlope(int $window = 5): float
    {
        $points = array_slice($this->points, -max(2, $window));
        $count = count($points);

        if ($count < 2) {
            return 0.0;
        }

        $first = $points[0];
        $last = $points[$count - 1];
        $generationSpan = max(1, $last->generation - $first->generation);

        return ($last->fitness - $first->fitness) / $generationSpan;
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
        $this->trajectory = [];
        $this->currentEpisode = null;
        $this->lastCompletedEpisode = null;
    }

    /**
     * @param  'bestFitness'|'avgFitness'  $metric
     */
    private function windowedDelta(string $metric, int $window): float
    {
        $snapshots = array_slice($this->trajectory, -max(2, $window));
        $count = count($snapshots);

        if ($count < 2) {
            return 0.0;
        }

        $deltas = [];

        for ($index = 1; $index < $count; $index++) {
            $previous = $snapshots[$index - 1];
            $current = $snapshots[$index];
            $deltas[] = $current->{$metric} - $previous->{$metric};
        }

        if ($deltas === []) {
            return 0.0;
        }

        return array_sum($deltas) / count($deltas);
    }
}
