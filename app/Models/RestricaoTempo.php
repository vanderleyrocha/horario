<?php
// app/Models/RestricaoTempo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RestricaoTempo extends Model
{
    protected $table = 'restricoes_tempo';

    protected $fillable = [
        'horario_id',
        'entidade_type',
        'entidade_id',
        'dia_semana',
        'tempo',
        'status',
        'motivo',
        'peso',
    ];

    protected $casts = [
        'dia_semana' => 'integer',
        'tempo' => 'integer',
        'peso' => 'integer',
    ];

    // Relacionamentos
    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function entidade(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeBloqueadas($query)
    {
        return $query->where('status', 'bloqueado');
    }

    public function scopePreferenciais($query)
    {
        return $query->where('status', 'preferencial');
    }

    public function scopeLivres($query)
    {
        return $query->where('status', 'livre');
    }

    public function scopePorDia($query, int $dia)
    {
        return $query->where('dia_semana', $dia);
    }

    public function scopePorTempo($query, int $tempo)
    {
        return $query->where('tempo', $tempo);
    }

    public function scopePorEntidade($query, string $type, int $id)
    {
        return $query->where('entidade_type', $type)->where('entidade_id', $id);
    }

    // Métodos Auxiliares
    public function ehBloqueio(): bool
    {
        return $this->status === 'bloqueado';
    }

    public function ehPreferencial(): bool
    {
        return $this->status === 'preferencial';
    }

    public function getPenalidade(): float
    {
        return match($this->status) {
            'bloqueado' => 1000.0 * $this->peso,
            'preferencial' => 10.0 * $this->peso,
            default => 0.0,
        };
    }

    public function getDiaNome(): string
    {
        $dias = [
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
        ];

        return $dias[$this->dia_semana] ?? 'Desconhecido';
    }

    public function getEntidadeNome(): string
    {
        return match($this->entidade_type) {
            'App\Models\Professor' => 'Professor: ' . $this->entidade->nome,
            'App\Models\Turma' => 'Turma: ' . $this->entidade->nome,
            'App\Models\Disciplina' => 'Disciplina: ' . $this->entidade->nome,
            default => 'Desconhecido',
        };
    }
}
