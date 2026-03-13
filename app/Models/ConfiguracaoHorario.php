<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracaoHorario extends Model
{
    use HasFactory;

    protected $table = 'configuracoes_horario';

    protected $fillable = [
        'horario_id',
        'nome_escola',
        'aulas_por_dia',
        'dias_semana',
        'horario_inicio',
        'horario_fim',
        'duracao_aula_minutos',
        'duracao_intervalo_minutos',
        'horarios_intervalos',
        'duracoes_intervalos',
        'permitir_janelas',
        'agrupar_disciplinas',
        'max_aulas_seguidas',
        'elitism_count',
        'target_fitness',
        'max_generations_without_improvement',
    ];

    protected $casts = [
        'horario_inicio' => 'string',
        'horario_fim' => 'string',
        'horarios_intervalos' => 'array',
        'duracoes_intervalos' => 'array',
        'permitir_janelas' => 'boolean',
        'agrupar_disciplinas' => 'boolean',
        'aulas_por_dia' => 'integer',
        'dias_semana' => 'integer',
        'duracao_aula_minutos' => 'integer',
        'duracao_intervalo_minutos' => 'integer',
        'max_aulas_seguidas' => 'integer',
        'elitism_count' => 'integer',
        'target_fitness' => 'float',
        'max_generations_without_improvement' => 'integer',
    ];

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function getHorarioTempo(int $tempo): string
    {
        $horarioInicio = CarbonImmutable::parse($this->horario_inicio);
        $duracaoAula = (int) $this->duracao_aula_minutos;
        $duracaoIntervaloPadrao = (int) $this->duracao_intervalo_minutos;
        $horariosIntervalos = array_map('intval', $this->horarios_intervalos ?? []);
        $duracoesIntervalos = array_map('intval', $this->duracoes_intervalos ?? []);

        $currentHorario = $horarioInicio;

        for ($i = 1; $i < $tempo; $i++) {
            $currentHorario = $currentHorario->addMinutes($duracaoAula);

            if (in_array($i, $horariosIntervalos, true)) {
                $intervaloIndex = array_search($i, $horariosIntervalos, true);
                $intervaloDuracao = (int) ($duracoesIntervalos[$intervaloIndex] ?? $duracaoIntervaloPadrao);
                $currentHorario = $currentHorario->addMinutes($intervaloDuracao);
            }
        }

        return $currentHorario->format('H:i');
    }

    public function getTemposFim(int $tempo): string
    {
        $horarioInicioTempo = CarbonImmutable::parse($this->getHorarioTempo($tempo));

        return $horarioInicioTempo
            ->addMinutes((int) $this->duracao_aula_minutos)
            ->format('H:i');
    }

    public function getDiasLetivos(): array
    {
        $dias = ['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta', 'Sabado', 'Domingo'];

        return array_slice($dias, 0, (int) $this->dias_semana);
    }

    public function getTotalTempos(): int
    {
        return (int) $this->aulas_por_dia;
    }
}
