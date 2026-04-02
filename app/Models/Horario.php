<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Horario extends Model
{
    use HasFactory;

    protected $table = 'horarios';

    protected $fillable = [
        'nome',
        'ano',
        'semestre',
        'configuracao',
        'fitness_score',
        'diagnostico_json',
        'indice_risco',
        'geracoes_executadas',
        'geracoes_sem_melhoria',
        'melhor_fitness',
        'fitness_medio',
        'historico_fitness',
        'conflitos_hard',
        'conflitos_soft',
        'detalhes_conflitos',
        'tempo_processamento_segundos',
        'criado_por',
        'ativado_por',
        'ativado_em',
        'status',
        'gerado_em',
    ];

    protected $casts = [
        'ano' => 'integer',
        'semestre' => 'integer',
        'configuracao' => 'array',
        'fitness_score' => 'float',
        'diagnostico_json' => 'array',
        'indice_risco' => 'integer',
        'geracoes_executadas' => 'integer',
        'geracoes_sem_melhoria' => 'integer',
        'melhor_fitness' => 'float',
        'fitness_medio' => 'float',
        'historico_fitness' => 'array',
        'conflitos_hard' => 'integer',
        'conflitos_soft' => 'integer',
        'detalhes_conflitos' => 'array',
        'tempo_processamento_segundos' => 'integer',
        'criado_por' => 'integer',
        'ativado_por' => 'integer',
        'ativado_em' => 'datetime',
        'gerado_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $horario): void {
            if (! empty($horario->configuracao)) {
                return;
            }

            $horario->configuracao = [
                'geracoes' => 500,
                'populacao' => 100,
                'taxa_mutacao' => 0.3,
                'taxa_crossover' => 0.7,
            ];
        });
    }

    public function alocacoes(): HasMany
    {
        return $this->hasMany(Alocacao::class);
    }

    public function configuracaoHorario(): HasOne
    {
        return $this->hasOne(ConfiguracaoHorario::class);
    }

    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class);
    }

    public function restricoes(): HasMany
    {
        return $this->hasMany(RestricaoTempo::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function ativadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ativado_por');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ScheduleExecution::class);
    }

    public function scheduleConstraints(): HasMany
    {
        return $this->hasMany(\App\Models\ScheduleConstraint::class);
    }

    public function lastExecution(): HasOne
    {
        return $this->hasOne(ScheduleExecution::class)->latestOfMany();
    }

    public function scopeAtivo($query)
    {
        return $query->where('status', 'ativo');
    }

    public function scopePorAno($query, int $ano)
    {
        return $query->where('ano', $ano);
    }

    public function scopePorSemestre($query, int $semestre)
    {
        return $query->where('semestre', $semestre);
    }
}
