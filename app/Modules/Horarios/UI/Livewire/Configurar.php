<?php

namespace App\Modules\Horarios\UI\Livewire;

use App\Models\ConfiguracaoHorario;
use App\Models\Horario;
use App\Traits\ComDadosComuns;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Configurar Horario'])]
class Configurar extends Component
{
    use ComDadosComuns;

    public Horario $horario;

    public int $etapaAtual = 1;
    public int $totalEtapas = 5;

    public string $nome_escola = '';
    public int $aulas_por_dia = 5;
    public int $dias_semana = 5;
    public string $horario_inicio = '07:00';
    public string $horario_fim = '12:00';
    public int $duracao_aula_minutos = 50;
    public int $duracao_intervalo_minutos = 15;
    public array $horarios_intervalos = [2, 4];
    public array $duracoes_intervalos = [];
    public bool $permitir_janelas = false;
    public bool $agrupar_disciplinas = true;
    public int $max_aulas_seguidas = 3;

    public int $populacao = 100;
    public int $geracoes = 500;
    public float $taxa_mutacao = 0.3;
    public float $taxa_crossover = 0.7;
    public float $taxa_elitismo = 0.05;
    public float $taxa_mutacao_min = 0.01;
    public float $taxa_mutacao_max = 0.6;
    public int $limite_estagnacao = 10;
    public int $elitism_count = 10;
    public float $target_fitness = 95.0;
    public int $max_generations_without_improvement = 50;

    public $editandoId = null;
    public $professor_id = '';
    public $disciplina_id = '';
    public $turma_id = '';
    public $aulas_semana = 2;
    public $tipo = 'simples';
    public $aulas_consecutivas = false;
    public $max_aulas_dia = 2;
    public $min_intervalo_dias = 0;
    public $preferencia_periodo = 'qualquer';
    public $dias_preferidos = [];
    public $tempos_preferidos = [];
    public $observacoes = '';

    public function mount(Horario $horario): void
    {
        $this->horario = $horario;

        $configuracaoHorario = ConfiguracaoHorario::where('horario_id', $horario->id)->first();

        if ($configuracaoHorario) {
            $this->nome_escola = $configuracaoHorario->nome_escola;
            $this->aulas_por_dia = $configuracaoHorario->aulas_por_dia;
            $this->dias_semana = $configuracaoHorario->dias_semana;
            $this->horario_inicio = CarbonImmutable::parse($configuracaoHorario->horario_inicio)->format('H:i');
            $this->horario_fim = CarbonImmutable::parse($configuracaoHorario->horario_fim)->format('H:i');
            $this->duracao_aula_minutos = $configuracaoHorario->duracao_aula_minutos;
            $this->duracao_intervalo_minutos = $configuracaoHorario->duracao_intervalo_minutos;
            $this->horarios_intervalos = $configuracaoHorario->horarios_intervalos ?? [2, 4];
            $this->duracoes_intervalos = $configuracaoHorario->duracoes_intervalos ?? [];
            $this->permitir_janelas = $configuracaoHorario->permitir_janelas;
            $this->agrupar_disciplinas = $configuracaoHorario->agrupar_disciplinas;
            $this->max_aulas_seguidas = $configuracaoHorario->max_aulas_seguidas;
            $this->target_fitness = $configuracaoHorario->target_fitness ?? 95.0;
            $this->max_generations_without_improvement = $configuracaoHorario->max_generations_without_improvement ?? 50;
        }

        $configuracaoAlgoritmo = $horario->configuracao ?? [];

        $this->populacao = (int) ($configuracaoAlgoritmo['populacao'] ?? $this->populacao);
        $this->geracoes = (int) ($configuracaoAlgoritmo['geracoes'] ?? $this->geracoes);
        $this->taxa_mutacao = (float) ($configuracaoAlgoritmo['taxa_mutacao'] ?? $this->taxa_mutacao);
        $this->taxa_crossover = (float) ($configuracaoAlgoritmo['taxa_crossover'] ?? $this->taxa_crossover);
        $this->taxa_elitismo = (float) ($configuracaoAlgoritmo['taxa_elitismo'] ?? $this->taxa_elitismo);
        $this->taxa_mutacao_min = (float) ($configuracaoAlgoritmo['taxa_mutacao_min'] ?? $this->taxa_mutacao_min);
        $this->taxa_mutacao_max = (float) ($configuracaoAlgoritmo['taxa_mutacao_max'] ?? $this->taxa_mutacao_max);
        $this->limite_estagnacao = (int) ($configuracaoAlgoritmo['limite_estagnacao'] ?? $this->limite_estagnacao);
        $this->target_fitness = (float) ($configuracaoAlgoritmo['target_fitness'] ?? $this->target_fitness);
        $this->max_generations_without_improvement = (int) ($configuracaoAlgoritmo['geracoes_sem_melhoria'] ?? $this->max_generations_without_improvement);
        $this->recalculateElitismCount();
    }

    public function render()
    {
        Log::info('Renderizando view livewire.horarios.configurar via app\\Livewire\\Horarios\\Configurar.php');

        return view('livewire.horarios.configurar');
    }

    public function proximaEtapa(): void
    {
        if ($this->etapaAtual === 1) {
            $this->salvarConfiguracaoBasica();

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        } elseif ($this->etapaAtual === 4) {
            $this->salvarConfiguracaoAlgoritmoGenetico();

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        }

        if ($this->etapaAtual < $this->totalEtapas) {
            $this->etapaAtual++;
        }
    }

    public function etapaAnterior(): void
    {
        if ($this->etapaAtual > 1) {
            $this->etapaAtual--;
        }
    }

    public function irParaEtapa(int $etapa): void
    {
        if ($etapa > 1 && ! $this->horario->configuracaoHorario()->exists()) {
            session()->flash('error', 'Por favor, salve a Configuracao Basica (Etapa 1) antes de prosseguir.');
            $this->etapaAtual = 1;

            return;
        }

        $this->etapaAtual = $etapa;
    }

    public function updatedTaxaElitismo(): void
    {
        $this->recalculateElitismCount();
    }

    public function updatedPopulacao(): void
    {
        $this->recalculateElitismCount();
    }

    public function salvarConfiguracaoBasica(): void
    {
        $this->validate([
            'nome_escola' => 'required|string|max:255',
            'aulas_por_dia' => 'required|integer|min:1|max:10',
            'dias_semana' => 'required|integer|min:1|max:7',
            'horario_inicio' => ['required', 'date_format:H:i'],
            'horario_fim' => ['required', 'date_format:H:i', 'after:horario_inicio'],
            'duracao_aula_minutos' => 'required|integer|min:10|max:120',
            'duracao_intervalo_minutos' => 'required|integer|min:0|max:60',
            'max_aulas_seguidas' => 'required|integer|min:1|max:5',
        ], [
            'horario_inicio.date_format' => 'O formato do Horario de Inicio deve ser HH:MM.',
            'horario_fim.date_format' => 'O formato do Horario de Fim deve ser HH:MM.',
            'horario_fim.after' => 'O Horario de Fim deve ser posterior ao Horario de Inicio.',
        ]);

        $configuracaoHorario = ConfiguracaoHorario::firstOrNew(['horario_id' => $this->horario->id]);
        $configuracaoHorario->fill([
            'nome_escola' => $this->nome_escola,
            'aulas_por_dia' => $this->aulas_por_dia,
            'dias_semana' => $this->dias_semana,
            'horario_inicio' => $this->horario_inicio,
            'horario_fim' => $this->horario_fim,
            'duracao_aula_minutos' => $this->duracao_aula_minutos,
            'duracao_intervalo_minutos' => $this->duracao_intervalo_minutos,
            'horarios_intervalos' => $this->horarios_intervalos,
            'duracoes_intervalos' => $this->duracoes_intervalos,
            'permitir_janelas' => $this->permitir_janelas,
            'agrupar_disciplinas' => $this->agrupar_disciplinas,
            'max_aulas_seguidas' => $this->max_aulas_seguidas,
        ])->save();

        $this->horario->load('configuracaoHorario');

        session()->flash('success', 'Configuracao basica salva com sucesso!');
    }

    public function salvarConfiguracaoAlgoritmoGenetico(): void
    {
        $this->validate([
            'populacao' => 'required|integer|min:20|max:1000',
            'geracoes' => 'required|integer|min:10|max:5000',
            'taxa_mutacao' => 'required|numeric|min:0|max:1',
            'taxa_crossover' => 'required|numeric|min:0|max:1',
            'taxa_elitismo' => 'required|numeric|min:0|max:1',
            'taxa_mutacao_min' => 'required|numeric|min:0|max:1',
            'taxa_mutacao_max' => 'required|numeric|min:0|max:1|gte:taxa_mutacao_min',
            'limite_estagnacao' => 'required|integer|min:0|max:1000',
            'target_fitness' => 'required|numeric|min:0|max:100',
            'max_generations_without_improvement' => 'required|integer|min:0',
        ]);

        $this->recalculateElitismCount();

        $configuracaoHorario = ConfiguracaoHorario::firstOrNew(['horario_id' => $this->horario->id]);
        $configuracaoHorario->fill([
            'elitism_count' => $this->elitism_count,
            'target_fitness' => $this->target_fitness,
            'max_generations_without_improvement' => $this->max_generations_without_improvement,
        ])->save();

        $this->horario->configuracao = [
            ...($this->horario->configuracao ?? []),
            'populacao' => $this->populacao,
            'geracoes' => $this->geracoes,
            'taxa_mutacao' => $this->taxa_mutacao,
            'taxa_crossover' => $this->taxa_crossover,
            'taxa_elitismo' => $this->taxa_elitismo,
            'taxa_mutacao_min' => $this->taxa_mutacao_min,
            'taxa_mutacao_max' => $this->taxa_mutacao_max,
            'limite_estagnacao' => $this->limite_estagnacao,
            'target_fitness' => $this->target_fitness,
            'geracoes_sem_melhoria' => $this->max_generations_without_improvement,
        ];
        $this->horario->save();

        $this->horario->load('configuracaoHorario');

        session()->flash('success', 'Configuracoes do Algoritmo Genetico salvas com sucesso!');
    }

    public function addIntervalo(): void
    {
        $this->horarios_intervalos[] = count($this->horarios_intervalos) + 1;
        $this->duracoes_intervalos[] = $this->duracao_intervalo_minutos;
    }

    public function removeIntervalo(int $index): void
    {
        unset($this->horarios_intervalos[$index]);
        unset($this->duracoes_intervalos[$index]);

        $this->horarios_intervalos = array_values($this->horarios_intervalos);
        $this->duracoes_intervalos = array_values($this->duracoes_intervalos);
    }

    public function fecharModal(): void
    {
    }

    private function recalculateElitismCount(): void
    {
        $this->elitism_count = max(1, (int) round($this->populacao * $this->taxa_elitismo));
    }
}
