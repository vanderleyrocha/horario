<?php

namespace App\Livewire\Algoritmo;

use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.app-layout', ['title' => 'Solver Execution'])]
class ExecutionDashboard extends Component
{
    public ScheduleExecution $execution;

    public array $metrics = [];

    public function mount(ScheduleExecution $execution)
    {
        $this->execution = $execution;

        $this->loadMetrics();
    }

    public function loadMetrics()
    {
        $this->metrics = ScheduleGenerationMetric::where('execution_id', $this->execution->id)
            ->orderBy('generation')
            ->get()
            ->toArray();
    }

    public function getExecutionInfoProperty()
    {
        return [
            'status' => $this->execution->status,
            'population' => $this->execution->population_size,
            'islands' => $this->execution->island_count,
            'generations' => $this->execution->generations,
            'bestFitness' => $this->execution->best_fitness,
            'avgFitness' => $this->execution->avg_fitness,
            'runtime' => $this->execution->execution_time_ms
        ];
    }

    public function render()
    {
        return view('livewire.algoritmo.execution-dashboard', [
            'metrics' => $this->metrics,
            'executionInfo' => $this->executionInfo
        ]);
    }
}
