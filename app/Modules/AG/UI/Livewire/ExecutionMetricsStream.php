<?php

namespace App\Modules\AG\UI\Livewire;

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

        if (! $metrics) {
            $metrics = Cache::get("ga_execution_progress_{$this->executionId}");
        }

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
            $payload = $this->resolveDatabaseFallbackPayload();
        }

        if (! is_array($payload)) {
            return;
        }

        $this->dispatch('metrics-update', metric: $payload);
    }

    public function render()
    {
        return view('modules.ag.livewire.algoritmo.metrics-stream');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDatabaseFallbackPayload(): ?array
    {
        $execution = ScheduleExecution::query()
            ->whereKey($this->executionId)
            ->first(['id', 'status', 'status_context_json', 'updated_at', 'end_time', 'start_time']);

        if ($execution === null) {
            return null;
        }

        $statusContext = is_array($execution->status_context_json)
            ? $execution->status_context_json
            : [];

        if ($statusContext === [] && ! in_array($execution->status, ['failed', 'cancelled', 'finished'], true)) {
            return [
                'phase' => 'monitoring',
                'stage' => 'no_cached_progress',
                'execution_id' => $execution->id,
                'execution_status' => $execution->status,
                'timestamp' => optional($execution->updated_at ?? $execution->start_time)->toDateTimeString(),
            ];
        }

        return [
            'phase' => 'terminal',
            'execution_id' => $execution->id,
            'execution_status' => $execution->status,
            'timestamp' => optional($execution->updated_at ?? $execution->end_time)->toDateTimeString(),
            'terminal_summary' => $statusContext,
        ];
    }
}
