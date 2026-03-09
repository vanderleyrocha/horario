<?php

namespace App\Modules\Horarios\Application;

use App\Models\Horario;

final class ScheduleExecutionRecorder {
    public function record(Horario $horario, array $result): void {
        $horario->executions()->create([
            'best_fitness' => $result['best_fitness'],
            'metrics' => json_encode($result['generation_metrics']),
        ]);
    }
}
