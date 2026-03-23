<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

class OperatorPerformanceTracker
{
    /**
     * @var OperatorScore[]
     */
    private array $scores = [];

    private int $totalUses = 0;

    private OperatorCreditManager $creditManager;

    public function __construct()
    {
        $this->creditManager = new OperatorCreditManager();
    }

    /*
    ---------------------------------------------------------
    Registrar operador
    ---------------------------------------------------------
    */

    public function registerOperator(string $operator): void
    {
        if (!isset($this->scores[$operator])) {

            $this->scores[$operator] = new OperatorScore($operator);

        }
    }

    /*
    ---------------------------------------------------------
    Registrar uso
    ---------------------------------------------------------
    */

    public function registerUse(string $operator): void
    {
        $score = $this->getOrCreateScore($operator);

        $score->registerUse();

        $this->totalUses++;
    }

    /*
    ---------------------------------------------------------
    Registrar reward
    ---------------------------------------------------------
    */

    public function record(string $operator, float $reward): void
    {
        $score = $this->getOrCreateScore($operator);

        $score->addReward($reward);
    }

    /*
    ---------------------------------------------------------
    Credit Assignment
    ---------------------------------------------------------
    */

    public function recordReward(string $operator, float $reward): void
    {
        $score = $this->getOrCreateScore($operator);

        $score->addReward($reward);

        $this->creditManager->reward($operator, $reward);
    }

    /*
    ---------------------------------------------------------
    Estatísticas (compatível com seletores)
    ---------------------------------------------------------
    */

    public function getOperatorStatistics(): array
    {
        $stats = [];

        foreach ($this->scores as $operator => $score) {

            $stats[$operator] = [
                'score' => $score->score(),
                'uses' => $score->uses()
            ];
        }

        return $stats;
    }

    public function bestOperator(): ?string
    {
        if (empty($this->scores)) {
            return null;
        }

        $best = null;
        $bestScore = -INF;

        foreach ($this->scores as $operator => $score) {

            $value = $score->score();

            if ($value > $bestScore) {

                $bestScore = $value;
                $best = $operator;
            }
        }

        return $best;
    }

    public function totalUses(): int
    {
        return $this->totalUses;
    }

    public function scores(): array
    {
        return $this->scores;
    }

    /*
    ---------------------------------------------------------
    Utilitário
    ---------------------------------------------------------
    */

    private function getOrCreateScore(string $operator): OperatorScore
    {
        if (!isset($this->scores[$operator])) {

            $this->scores[$operator] = new OperatorScore($operator);

        }

        return $this->scores[$operator];
    }
}
