<?php

namespace App\Jobs;

use App\Models\Horario;
use App\Modules\AG\Infrastructure\Progress\CacheProgressReporter;
use App\Modules\Horarios\Application\GenerateScheduleAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class GerarHorarioJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Horario $horario) {
    }

    public function handle(): void {
        $progress = new CacheProgressReporter($this->horario->id);

        $action = app(GenerateScheduleAction::class);

        $action->execute(
            $this->horario,
            $progress
        );
    }
}
