<?php

namespace App\Livewire\Algoritmo;

use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class ExecutionStatusPanel extends Component
{
    public ScheduleExecution $execution;

    public array $progress = [];

    public int $metricsCount = 0;

    public function mount(ScheduleExecution $execution): void
    {
        $this->execution = $execution;
        $this->refreshState();
    }

    public function refreshState(): void
    {
        $this->execution->refresh();
        $this->metricsCount = ScheduleGenerationMetric::where('execution_id', $this->execution->id)->count();

        $progress = Cache::get("horario_geracao_{$this->execution->horario_id}", []);

        if (($progress['execution_id'] ?? null) !== null && (int) $progress['execution_id'] !== $this->execution->id) {
            $progress = [];
        }

        $this->progress = is_array($progress) ? $progress : [];
    }

    public function getElapsedProperty(): ?string
    {
        $start = $this->execution->start_time;

        if ($start === null) {
            return null;
        }

        $end = $this->execution->end_time ?? now();

        return $start->diffForHumans($end, true);
    }

    public function render()
    {
        return view('livewire.algoritmo.execution-status-panel');
    }
}
