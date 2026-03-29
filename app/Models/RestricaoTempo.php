<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RestricaoTempo extends Model
{
    use HasFactory;

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
        'horario_id' => 'integer',
        'entidade_id' => 'integer',
        'dia_semana' => 'integer',
        'tempo' => 'integer',
        'peso' => 'integer',
    ];

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function entidade(): MorphTo
    {
        return $this->morphTo();
    }

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
        return match ($this->status) {
            'bloqueado' => 1000.0 * $this->peso,
            'preferencial' => 10.0 * $this->peso,
            default => 0.0,
        };
    }

    public function getDiaNome(): string
    {
        $dias = [
            1 => 'Segunda-feira',
            2 => 'Terca-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sabado',
        ];

        return $dias[$this->dia_semana] ?? 'Desconhecido';
    }

    public function getEntidadeNome(): string
    {
        return match ($this->entidade_type) {
            Professor::class => 'Professor: '.$this->entidade->nome,
            Turma::class => 'Turma: '.$this->entidade->nome,
            Disciplina::class => 'Disciplina: '.$this->entidade->nome,
            default => 'Desconhecido',
        };
    }
}
