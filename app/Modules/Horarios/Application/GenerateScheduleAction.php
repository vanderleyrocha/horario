<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Application;

use App\Models\Horario;
use App\Modules\AG\Application\RunGeneticAlgorithm;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use Illuminate\Support\Facades\Log;

final class GenerateScheduleAction
{
    public function __construct(private RunGeneticAlgorithm $runner, private PersistBestSolutionService $persist)
    {
    }

    public function execute(Horario $horario, int $executionId, ?ProgressReporterInterface $progress = null): array
    {
        Log::info("GenerateScheduleAction::execute() iniciado");
        $result = $this->runner->execute($horario, $progress);

        $this->persist->persist($horario, $result['best'], $executionId);

        return $result;
    }
}
