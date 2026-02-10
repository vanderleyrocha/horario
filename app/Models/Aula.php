<?php
// app/Models/Aula.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Aula extends Model
{

    use HasFactory;
    
    protected $fillable = [
        'horario_id',
        'professor_id',
        'disciplina_id',
        'turma_id',
        'aulas_semana',
        'tipo',
        'aulas_consecutivas',
        'max_aulas_dia',
        'min_intervalo_dias',
        'preferencia_periodo',
        'dias_preferidos',
        'tempos_preferidos',
        'ativa',
        'observacoes',
    ];

    protected $casts = [
        'aulas_semana' => 'integer',
        'aulas_consecutivas' => 'boolean',
        'max_aulas_dia' => 'integer',
        'min_intervalo_dias' => 'integer',
        'dias_preferidos' => 'array',
        'tempos_preferidos' => 'array',
        'ativa' => 'boolean',
    ];

    // Relacionamentos
    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Professor::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function alocacoes(): HasMany
    {
        return $this->hasMany(Alocacao::class);
    }

    // Scopes
    public function scopeAtivas($query)
    {
        return $query->where('ativa', true);
    }

    public function scopePorTurma($query, $turmaId)
    {
        return $query->where('turma_id', $turmaId);
    }

    public function scopePorProfessor($query, $professorId)
    {
        return $query->where('professor_id', $professorId);
    }

    // Métodos Auxiliares
    public function getDuracaoTempos(): int
    {
        return match($this->tipo) {
            'simples' => 1,
            'dupla' => 2,
            'tripla' => 3,
            default => 1,
        };
    }

    public function getTotalTemposNecessarios(): int
    {
        return $this->aulas_semana * $this->getDuracaoTempos();
    }

    public function podeAlocarNoDia(int $dia): bool
    {
        if (empty($this->dias_preferidos)) {
            return true;
        }

        return in_array($dia, $this->dias_preferidos);
    }

    public function podeAlocarNoTempo(int $tempo): bool
    {
        if (empty($this->tempos_preferidos)) {
            return true;
        }

        return in_array($tempo, $this->tempos_preferidos);
    }
}
