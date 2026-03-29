<?php

namespace App\Modules\Horarios\UI\Livewire\Aulas;

use App\Models\Aula;
use App\Modules\Horarios\Support\ComDadosComuns;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Editar Aula'])]
class Edit extends Component
{
    use ComDadosComuns;

    public Aula $aula;

    public string $professor_id = '';

    public string $disciplina_id = '';

    public string $turma_id = '';

    public int $aulas_semana = 2;

    public string $tipo = 'simples';

    public bool $aulas_consecutivas = false;

    public int $max_aulas_dia = 2;

    public int $min_intervalo_dias = 0;

    public string $preferencia_periodo = 'qualquer';

    public array $dias_preferidos = [];

    public array $tempos_preferidos = [];

    public string $observacoes = '';

    public array $diasDaSemanaOpcoes = [
        1 => 'Segunda-feira',
        2 => 'Terca-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    public array $temposDeAulaOpcoes = [
        1 => '1o Tempo',
        2 => '2o Tempo',
        3 => '3o Tempo',
        4 => '4o Tempo',
        5 => '5o Tempo',
        6 => '6o Tempo',
        7 => '7o Tempo',
        8 => '8o Tempo',
        9 => '9o Tempo',
        10 => '10o Tempo',
    ];

    protected $rules = [
        'professor_id' => 'required|exists:professores,id',
        'disciplina_id' => 'required|exists:disciplinas,id',
        'turma_id' => 'required|exists:turmas,id',
        'aulas_semana' => 'required|integer|min:1|max:10',
        'tipo' => 'required|in:simples,dupla,tripla',
        'aulas_consecutivas' => 'boolean',
        'max_aulas_dia' => 'required|integer|min:1|max:5',
        'min_intervalo_dias' => 'nullable|integer|min:0|max:6',
        'preferencia_periodo' => 'required|in:qualquer,manha,tarde,noite',
        'dias_preferidos' => 'nullable|array',
        'dias_preferidos.*' => 'integer|min:1|max:7',
        'tempos_preferidos' => 'nullable|array',
        'tempos_preferidos.*' => 'integer|min:1|max:10',
        'observacoes' => 'nullable|string|max:500',
    ];

    public function mount(Aula $aula): void
    {
        $this->aula = $aula;
        $this->professor_id = (string) $aula->professor_id;
        $this->disciplina_id = (string) $aula->disciplina_id;
        $this->turma_id = (string) $aula->turma_id;
        $this->aulas_semana = (int) $aula->aulas_semana;
        $this->tipo = (string) $aula->tipo;
        $this->aulas_consecutivas = (bool) $aula->aulas_consecutivas;
        $this->max_aulas_dia = (int) $aula->max_aulas_dia;
        $this->min_intervalo_dias = (int) ($aula->min_intervalo_dias ?? 0);
        $this->preferencia_periodo = (string) ($aula->preferencia_periodo ?? 'qualquer');
        $this->dias_preferidos = is_array($aula->dias_preferidos)
            ? $aula->dias_preferidos
            : (json_decode((string) $aula->dias_preferidos, true) ?? []);
        $this->tempos_preferidos = is_array($aula->tempos_preferidos)
            ? $aula->tempos_preferidos
            : (json_decode((string) $aula->tempos_preferidos, true) ?? []);
        $this->observacoes = (string) ($aula->observacoes ?? '');
    }

    public function salvarAula()
    {
        $this->validate();

        $this->aula->update([
            'professor_id' => $this->professor_id,
            'disciplina_id' => $this->disciplina_id,
            'turma_id' => $this->turma_id,
            'aulas_semana' => $this->aulas_semana,
            'tipo' => $this->tipo,
            'aulas_consecutivas' => $this->aulas_consecutivas,
            'max_aulas_dia' => $this->max_aulas_dia,
            'min_intervalo_dias' => $this->min_intervalo_dias,
            'preferencia_periodo' => $this->preferencia_periodo,
            'dias_preferidos' => ! empty($this->dias_preferidos) ? $this->dias_preferidos : null,
            'tempos_preferidos' => ! empty($this->tempos_preferidos) ? $this->tempos_preferidos : null,
            'observacoes' => $this->observacoes,
        ]);

        session()->flash('success', 'Aula atualizada com sucesso!');

        return redirect()->route('turmas.aulas', $this->aula->turma);
    }

    public function render()
    {
        return view('modules.horarios.livewire.aulas.edit', [
            'professores' => $this->professores,
            'disciplinas' => $this->disciplinas,
            'turmas' => $this->turmas,
        ]);
    }
}
