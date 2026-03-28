<?php

namespace App\Livewire\Algoritmo;

use App\Models\ScheduleExecution;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class ExecutionMetricsStream extends Component
{
    public int $executionId;

    public int $horarioId;

    public array $metrics = [];

    public function mount($executionId, ?int $horarioId = null)
    {
        $this->executionId = (int) $executionId;
        $this->horarioId = $horarioId
            ?? (int) ScheduleExecution::query()
                ->whereKey($this->executionId)
                ->value('horario_id');
    }

    public function pollMetrics()
    {
        $metrics = Cache::get("ga_execution_metrics_{$this->executionId}");

        if (! $metrics && $this->horarioId > 0) {
            $progress = Cache::get("horario_geracao_{$this->horarioId}");

            if (
                is_array($progress)
                && (int) ($progress['execution_id'] ?? 0) === $this->executionId
            ) {
                $metrics = $progress;
            }
        }

        $payload = is_array($metrics) && array_is_list($metrics)
            ? end($metrics)
            : $metrics;

        if (! is_array($payload)) {
            return;
        }

        $this->dispatch('metrics-update', metric: $payload);
    }

    public function render()
    {
        return view('livewire.algoritmo.metrics-stream');
    }
}
