<?php

namespace App\Modules\Horarios\Application;

use App\Models\Horario;
use App\Modules\AG\Application\RunGeneticAlgorithm;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;

final class GenerateScheduleAction {
    public function execute(
        Horario $horario,
        ?ProgressReporterInterface $progressReporter = null
    ): array {

        $runner = new RunGeneticAlgorithm();

        return $runner->execute($horario, $progressReporter);
    }
}
