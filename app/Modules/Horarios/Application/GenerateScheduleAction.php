<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Application;

use App\Models\Horario;
use App\Modules\AG\Application\RunGeneticAlgorithm;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use Illuminate\Support\Facades\Log;

final class GenerateScheduleAction
{
    public function __construct(private RunGeneticAlgorithm $runner, private PersistBestSolutionService $persist)
    {
    }

    public function execute(
        Horario $horario,
        int $executionId,
        ?ProgressReporterInterface $progress = null,
        ?ExecutionMetricsRecorder $executionMetrics = null,
    ): array {
        Log::info('GenerateScheduleAction::execute() iniciado');
        $result = $this->runner->execute($horario, $progress, $executionMetrics);

        $viable = $result['viable'] ?? true;

        if (! $viable) {
            Log::critical('schedule.partial_result_will_be_persisted', [
                'execution_id' => $executionId,
                'horario_id' => $horario->id,
                'best_fitness' => $result['best']->fitness(),
                'note' => 'Solucao com hard_penalty > 0 apos reparo final malsucedido. Persistindo resultado parcial para evitar perda total do trabalho evolutivo.',
            ]);
        }

        $this->persist->persist($horario, $result['best'], $executionId);

        return $result;
    }
}
