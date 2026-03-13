<?php

namespace App\Modules\AG\Infrastructure\Metrics;

use Illuminate\Support\Facades\DB;
use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;

class ExecutionMetricsRecorder
{
    private int $executionId;

    private array $buffer = [];

    private int $batchSize = 25;

    public function startExecution(int $horarioId, int $populationSize, int $generations, array $parameters): int
    {

        $this->executionId = DB::table('schedule_executions')
            ->insertGetId([
                'horario_id' => $horarioId,
                'start_time' => now(),
                'population_size' => $populationSize,
                'generations' => $generations,
                'parameters_json' => json_encode($parameters),
                'created_at' => now()
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
            'created_at' => now()
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

        DB::table('schedule_generation_metrics')
            ->insert($this->buffer);

        $this->buffer = [];
    }

    public function finishExecution(float $bestFitness): void
    {
        $this->flush();

        DB::table('schedule_executions')
            ->where('id', $this->executionId)
            ->update([
                'best_fitness' => $bestFitness,
                'end_time' => now(),
                'updated_at' => now()
            ]);
    }

    public function getExecutionId(): int
    {
        return $this->executionId;
    }

}
