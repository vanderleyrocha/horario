<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Application;

use App\Models\Horario;
use App\Modules\AG\Application\RunGeneticAlgorithm;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;

final class GenerateScheduleAction {
    public function __construct(
        private RunGeneticAlgorithm $runner,
        private PersistBestSolutionService $persist,
        private ScheduleExecutionRecorder $recorder
    ) {
    }

    public function execute(Horario $horario, ?ProgressReporterInterface $progress = null): array {

        $result = $this->runner->execute($horario, $progress);

        $this->persist->persist(
            $horario,
            $result['best']
        );

        $this->recorder->record(
            $horario,
            $result
        );

        return $result;
    }
}
