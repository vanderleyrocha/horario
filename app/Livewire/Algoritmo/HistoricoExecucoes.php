<?php

namespace App\Livewire\Algoritmo;

use App\Models\Horario;
use App\Models\ScheduleExecution;
use Livewire\Component;

class HistoricoExecucoes extends Component
{
    public Horario $horario;

    public function mount(Horario $horario): void
    {
        $this->horario = $horario;
    }

    public function getExecucoesProperty()
    {
        return $this->horario->executions()->latest()->get();
    }

    public function abrirExecucao(int $execucaoId): void
    {
        $this->redirect(route('algoritmo.execution', ['execution' => $execucaoId]), navigate: true);
    }

    public function excluirExecucao(int $execucaoId): void
    {
        $execucao = ScheduleExecution::query()
            ->where('id', $execucaoId)
            ->where('horario_id', $this->horario->id)
            ->first();

        if (!$execucao) {
            return;
        }

        // Nao remove execucao em andamento.
        if ($execucao->status === 'running') {
            return;
        }

        $execucao->delete();

        $this->dispatch('$refresh');
    }

    public function render()
    {
        return view('livewire.algoritmo.historico-execucoes');
    }
}
