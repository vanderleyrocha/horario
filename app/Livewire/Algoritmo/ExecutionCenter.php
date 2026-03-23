<?php

namespace App\Livewire\Algoritmo;

use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Jobs\GerarHorarioJob;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.app-layout', ['title' => 'Solver Center'])]
class ExecutionCenter extends Component
{
    public Horario $horario;

    public array $config = [
        'population' => 120,
        'generations' => 500
    ];

    public function mount(Horario $horario)
    {
        $this->horario = $horario;
    }

    public function runSolver()
    {
        $execution = ScheduleExecution::create([
            'horario_id' => $this->horario->id,
            'status' => 'running',
            'start_time' => now(),
            'population_size' => $this->config['population'],
            'generations' => $this->config['generations'],
            'island_count' => 4,
            'parameters_json' => $this->config,
        ]);

        GerarHorarioJob::dispatch($this->horario, $execution->id);

        return redirect()->route('algoritmo.execution', $execution);
    }

    public function getExecutionsProperty()
    {
        return ScheduleExecution::where('horario_id', $this->horario->id)
            ->latest()
            ->limit(20)
            ->get();
    }

    public function render()
    {
        return view('livewire.algoritmo.execution-center', [
            'executions' => $this->executions
        ]);
    }
}
