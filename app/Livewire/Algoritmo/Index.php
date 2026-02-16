<?php

namespace App\Livewire\Algoritmo;

use App\Models\Horario;
use App\Jobs\GerarHorarioJob;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Layout('components.app-layout', ['title' => 'Gerar Horário'])]
class Index extends Component {
    public Horario $horario;
    public bool $emGeracao = false;
    public ?string $statusGeracao = null; // 'iniciado', 'em_progresso', 'concluido', 'erro'

    public int $progresso = 0;
    public int $geracaoAtual = 0;
    public int $totalGeracoes = 0;
    public float $melhorFitness = 0.0;
    public ?string $mensagemStatus = null; // ✅ ADICIONADO

    public ?string $status = null;
    public ?string $mensagemErro = null;

    public array $configuracao = [];

    protected array $rules = [
        'configuracao.populacao' => 'required|integer|min:10',
        'configuracao.geracoes' => 'required|integer|min:1',
        'configuracao.taxa_mutacao' => 'required|numeric|between:0,1',
        'configuracao.taxa_crossover' => 'required|numeric|between:0,1',
    ];

    public function mount(Horario $horario) {
        $this->horario = $horario;

        $this->configuracao = $this->horario->configuracao ?? [
            'populacao' => 100,
            'geracoes' => 500,
            'taxa_mutacao' => 0.3,
            'taxa_crossover' => 0.7,
        ];

        $cacheKey = "horario_geracao_{$this->horario->id}";

        // Log para depuração: Verifique o que está no cache ao montar
        Log::info("Montando Algoritmo/Index para horário {$this->horario->id}. Cache existe: " . (Cache::has($cacheKey) ? 'Sim' : 'Não'));

        if (Cache::has($cacheKey)) {
            $this->emGeracao = true;
            $this->statusGeracao = 'em_progresso'; // Assume que está em progresso se o cache existe
            $this->atualizarProgresso(); // Carrega os dados do cache
        } else {
            // Se não há cache, garante que o status inicial seja 'pronto para iniciar'
            $this->emGeracao = false;
            $this->statusGeracao = 'pronto';
            $this->mensagemStatus = 'Aguardando início da geração.';
        }
        $this->atualizarStatus();
    }

    public function updatedConfiguracao() {
        $this->validate(); // Valida usando as regras acima

        // Joga o array local de volta para o Model e salva
        $this->horario->configuracao = $this->configuracao;
        $this->horario->save();
    }

    public function iniciarGeracao() {
        $this->validate([
            'horario.id' => 'required|exists:horarios,id',
        ]);

        $this->emGeracao = true;
        $this->statusGeracao = 'iniciado'; // ✅ ADICIONADO
        $this->progresso = 0;
        $this->geracaoAtual = 0;
        $this->melhorFitness = 0.0;
        $this->mensagemStatus = 'Iniciando processo de geração...'; // ✅ ADICIONADO

        // Dispara o Job assíncrono
        Log::info("Algoritmo.Index Component: dispatch Job para horário {$this->horario->id}");
        GerarHorarioJob::dispatch($this->horario);

        // O polling começará a buscar atualizações do cache
        $this->dispatch('startPolling');
    }

    public function atualizarProgresso(): void {
        $cacheKey = "horario_geracao_{$this->horario->id}";
        $dadosProgresso = Cache::get($cacheKey);

        // Log para depuração: Verifique os dados lidos do cache
        // Log::info("Lendo progresso do cache para horário {$this->horario->id}", $dadosProgresso ?? ['status' => 'Cache vazio']);

        if ($dadosProgresso) {
            $this->mensagemStatus = $dadosProgresso['mensagem_status'] ?? 'Processando...'; // ✅ CRUCIAL: Valor padrão
            $this->statusGeracao = $dadosProgresso['status_geracao'] ?? 'em_progresso'; // ✅ CRUCIAL: Valor padrão

            if ($this->statusGeracao === 'concluido' || $this->statusGeracao === 'erro') {
                $this->emGeracao = false;
                // Emitir evento para parar o polling, se necessário (o wire:poll já lida com a condição $emGeracao)
                $this->dispatch('stopPolling');
            }
        } else {
            // Se o cache sumiu inesperadamente, assume que a geração parou ou falhou
            $this->emGeracao = false;
            $this->statusGeracao = 'erro';
            $this->mensagemStatus = 'A geração foi interrompida ou o cache expirou.';
            $this->progresso = 0;
            $this->geracaoAtual = 0;
            $this->melhorFitness = 0.0;
            $this->dispatch('stopPolling');
        }
    }

    public function atualizarStatus() {
        $cache = Cache::get("horario_geracao_{$this->horario->id}");

        if (!$cache) {
            return;
        }

        $this->status = $cache['status'] ?? null;
        $this->progresso = (int) ($cache['progresso'] ?? 0);
        $this->geracaoAtual = (int) ($cache['geracao_atual'] ?? 0);
        $this->totalGeracoes = (int) ($cache['total_geracoes'] ?? 0);
        $this->melhorFitness = (float) ($cache['melhor_fitness'] ?? 0);

        $this->emGeracao = $this->status === 'em_execucao';

        if ($this->status === 'erro') {
            $this->mensagemErro = $cache['mensagem'] ?? 'Erro desconhecido.';
        }

        Log::info("Algoritmo.Index Component: Atualizando progresso do cache para horário {$this->horario->id}");

        $this->atualizarProgresso();
    }


    public function cancelarGeracao() {
        // Implementar lógica para cancelar o job, se possível.
        // Por enquanto, apenas remove o cache para parar o polling e reseta o status.
        Cache::forget("horario_geracao_{$this->horario->id}");
        $this->horario->update(['status' => 'rascunho']);
        $this->emGeracao = false;
        $this->statusGeracao = 'cancelado';
        $this->mensagemStatus = 'Geração cancelada pelo usuário.';
        $this->dispatch('stopPolling');
    }

    public function visualizarResultado() {
        return redirect()->route('horarios.show', $this->horario);
    }

    public function render() {
        // Garante que o horário e suas relações estejam atualizados
        $this->horario->loadMissing(['aulas', 'restricoes', 'configuracaoHorario']);
        return view('livewire.algoritmo.index');
    }
}
