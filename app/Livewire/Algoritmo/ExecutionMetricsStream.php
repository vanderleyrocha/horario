<?php

namespace App\Livewire\Algoritmo;

use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class ExecutionMetricsStream extends Component
{
    public int $executionId;

    public array $metrics = [];

    public function mount($executionId)
    {
        $this->executionId = $executionId;
    }

    public function pollMetrics()
    {
        $metrics = Cache::get("ga_execution_metrics_{$this->executionId}");

        if (!$metrics) {
            return;
        }

        $payload = is_array($metrics) && array_is_list($metrics)
            ? end($metrics)
            : $metrics;

        if (!is_array($payload)) {
            return;
        }

        $this->dispatch('metrics-update', metric: $payload);
    }

    public function render()
    {
        return view('livewire.algoritmo.metrics-stream');
    }
}
