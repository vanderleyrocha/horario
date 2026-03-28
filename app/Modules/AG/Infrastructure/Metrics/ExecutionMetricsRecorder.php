<?php

namespace App\Modules\AG\Infrastructure\Metrics;

use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;
use Illuminate\Support\Facades\DB;

class ExecutionMetricsRecorder
{
    private ?int $executionId = null;

    private array $buffer = [];

    private int $batchSize = 1;

    public function startExecution(
        int $horarioId,
        int $populationSize,
        int $generations,
        array $parameters,
        ?int $executionId = null
    ): int {
        $payload = [
            'horario_id' => $horarioId,
            'start_time' => now(),
            'status' => 'running',
            'population_size' => $populationSize,
            'generations' => $generations,
            'parameters_json' => json_encode($parameters),
            'updated_at' => now(),
        ];

        if ($executionId !== null) {
            DB::table('schedule_executions')
                ->where('id', $executionId)
                ->update($payload);

            $this->executionId = $executionId;

            return $this->executionId;
        }

        $this->executionId = DB::table('schedule_executions')
            ->insertGetId($payload + [
                'created_at' => now(),
            ]);

        return $this->executionId;
    }

    public function recordGeneration(GenerationMetrics $metrics): void
    {
        $this->buffer[] = [
            'execution_id' => $this->executionId,
            'generation' => $metrics->generation,
            'best_fitness' => $metrics->bestFitness,
            'avg_fitness' => $metrics->avgFitness,
            'variance' => $metrics->variance,
            'diversity' => $metrics->diversity,
            'entropy' => $metrics->entropy,
            'mutation_rate' => $metrics->mutationRate,
            'stagnation' => $metrics->stagnation,
            'operator_used' => $metrics->operatorUsed,
            'operator_reward' => $metrics->operatorReward,
            'landscape_state' => $metrics->landscapeState,
            'created_at' => now(),
        ];

        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        DB::table('schedule_generation_metrics')->insert($this->buffer);

        $this->buffer = [];
    }

    public function finishExecution(float $bestFitness): void
    {
        if ($this->executionId === null) {
            return;
        }

        $this->flush();

        DB::table('schedule_executions')
            ->where('id', $this->executionId)
            ->update([
                'status' => 'finished',
                'best_fitness' => $bestFitness,
                'end_time' => now(),
                'updated_at' => now(),
            ]);
    }

    public function failExecution(): void
    {
        if ($this->executionId === null) {
            return;
        }

        $this->flush();

        DB::table('schedule_executions')
            ->where('id', $this->executionId)
            ->update([
                'status' => 'failed',
                'end_time' => now(),
                'updated_at' => now(),
            ]);
    }

    public function cancelExecution(): void
    {
        if ($this->executionId === null) {
            return;
        }

        $this->flush();

        DB::table('schedule_executions')
            ->where('id', $this->executionId)
            ->update([
                'status' => 'cancelled',
                'end_time' => now(),
                'updated_at' => now(),
            ]);
    }

    public function hasExecutionId(): bool
    {
        return $this->executionId !== null;
    }

    public function getExecutionId(): int
    {
        if ($this->executionId === null) {
            throw new \RuntimeException('Execution ID not initialized.');
        }

        return $this->executionId;
    }
}
