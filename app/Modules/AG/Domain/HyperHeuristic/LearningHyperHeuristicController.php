<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

use App\Modules\AG\Domain\Landscape\LandscapeState;
use App\Modules\AG\Domain\Operators\EvolutionaryOperatorInterface;

class LearningHyperHeuristicController
{
    private array $operatorMap = [];

    private MoveLearningEngine $moveLearning;

    private ?LandscapeState $landscapeState = null;

    public function __construct(private OperatorPerformanceTracker $tracker, private OperatorSelectionStrategy $selectionStrategy, private OperatorRewardCalculator $rewardCalculator)
    {

        $this->moveLearning = new MoveLearningEngine();

    }

    /*
     |-------------------------------------------------------
     | Register operator pool
     |-------------------------------------------------------
     */

    public function registerOperators(array $operators): void
    {
        foreach ($operators as $operator) {

            if (!$operator instanceof EvolutionaryOperatorInterface) {
                throw new \InvalidArgumentException("Operador precisa implementar EvolutionaryOperatorInterface");
            }

            $name = method_exists($operator, 'getName')
                ? $operator->getName()
                : class_basename($operator);

            $this->operatorMap[$name] = $operator;

            $this->tracker->registerOperator($name);
        }
    }

    /*
     |-------------------------------------------------------
     | Select operator
     |-------------------------------------------------------
     */

    public function selectOperator(array $fallbackOperators): EvolutionaryOperatorInterface
    {
        $allowedNames = [];

        foreach ($fallbackOperators as $op) {
            $name = method_exists($op, 'getName')
                ? $op->getName()
                : class_basename($op);

            $allowedNames[$name] = true;

            if (!isset($this->operatorMap[$name])) {
                $this->operatorMap[$name] = $op;
                $this->tracker->registerOperator($name);
            }
        }

        /*
        -----------------------------------------------------
        Se nenhum operador foi registrado ainda
        -----------------------------------------------------
        */

        if (empty($this->operatorMap)) {

            foreach ($fallbackOperators as $op) {

                $name = method_exists($op, 'getName')
                    ? $op->getName()
                    : class_basename($op);

                $this->operatorMap[$name] = $op;

                $this->tracker->registerOperator($name);
            }
        }

        /*
        -----------------------------------------------------
        Move Learning Engine
        -----------------------------------------------------
        */

        $eligibleOperators = array_intersect_key($this->operatorMap, $allowedNames);

        if ($this->landscapeState !== null) {

            $best = $this->moveLearning->bestOperator($this->landscapeState);

            if ($best && isset($eligibleOperators[$best])) {

                $this->tracker->registerUse($best);

                return $eligibleOperators[$best];
            }
        }

        /*
        -----------------------------------------------------
        Hyper-Heuristic clássico
        -----------------------------------------------------
        */

        $stats = array_intersect_key($this->tracker->getOperatorStatistics(), $allowedNames);

        if (empty($stats)) {

            $operators = array_values($this->operatorMap);

            return $operators[array_rand($operators)];
        }

        $selected = $this->selectionStrategy->select($stats);

        if ($selected && isset($eligibleOperators[$selected])) {

            $this->tracker->registerUse($selected);

            return $eligibleOperators[$selected];
        }

        /*
        -----------------------------------------------------
        fallback aleatório
        -----------------------------------------------------
        */

        $operators = array_values($eligibleOperators);

        return $operators[array_rand($operators)];
    }

    /*
     |-------------------------------------------------------
     | Record operator reward
     |-------------------------------------------------------
     */

    public function record(object $operator, float $before, float $after): void
    {
        $reward = $this->rewardCalculator->compute($before, $after);

        $name = method_exists($operator, 'getName')
            ? $operator->getName()
            : class_basename($operator);

        /*
        -----------------------------------------------------
        Credit Assignment
        -----------------------------------------------------
        */

        $this->tracker->recordReward($name, $reward);

        /*
        -----------------------------------------------------
        Move Learning Engine
        -----------------------------------------------------
        */

        if ($this->landscapeState !== null) {

            $this->moveLearning->record($this->landscapeState, $name, $reward);
        }
    }

    /*
     |-------------------------------------------------------
     | Estatísticas
     |-------------------------------------------------------
     */

    public function getOperatorStatistics(): array
    {
        return $this->tracker->getOperatorStatistics();
    }

    /*
     |-------------------------------------------------------
     | Atualização do Landscape
     |-------------------------------------------------------
     */

    public function updateLandscapeState(LandscapeState $state): void
    {
        $this->landscapeState = $state;
    }

    public function recordRewardByName(string $name, float $reward): void
    {
        if (!isset($this->operatorMap[$name])) {
            $this->tracker->registerOperator($name);
        }

        $this->tracker->recordReward($name, $reward);

        if ($this->landscapeState !== null) {
            $this->moveLearning->record($this->landscapeState, $name, $reward);
        }
    }

}
