<?php

namespace App\Livewire\Algoritmo;

use App\Models\Horario;
use App\Models\ExecucaoAlgoritmo;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

class HistoricoExecucoes extends Component {
    public Horario $horario;

    public function mount(Horario $horario): void {
        $this->horario = $horario;
    }

    public function getExecucoesProperty() {
        return $this->horario->execucoes()->latest()->get();
    }

    public function ativarExecucao(int $execucaoId): void {
        DB::transaction(function () use ($execucaoId) {

            $this->horario->execucoes()->update(['ativa' => false]);

            ExecucaoAlgoritmo::where('id', $execucaoId)->where('horario_id', $this->horario->id)->update(['ativa' => true]);
        });

        $this->dispatch('$refresh');
    }

    public function excluirExecucao(int $execucaoId): void {
        $execucao = ExecucaoAlgoritmo::where('id', $execucaoId)->where('horario_id', $this->horario->id)->first();

        if (!$execucao) {
            return;
        }

        // Se for ativa, não permitir excluir
        if ($execucao->ativa) {
            return;
        }

        $execucao->delete();

        $this->dispatch('$refresh');
    }

    public function render() {
        return view('livewire.algoritmo.historico-execucoes');
    }
}
