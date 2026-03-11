<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alocacao extends Model
{
    use HasFactory;

    protected $table = 'alocacoes';

    protected $fillable = [
        'horario_id',
        'execution_id',
        'turma_id',
        'disciplina_id',
        'professor_id',
        'dia_semana',
        'tempo',
        'duracao_tempos',
        'horario_inicio',
        'horario_fim',
        'aula_id',
        'eh_manual',
        'bloqueada'
    ];

    protected $casts = [
        'tempo' => 'integer',
        'duracao_tempos' => 'integer',
        'eh_manual' => 'boolean',
        'bloqueada' => 'boolean'
    ];

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ScheduleExecution::class, 'execution_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Professor::class);
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }

    public function scopeManuais($query)
    {
        return $query->where('eh_manual', true);
    }

    public function scopeBloqueadas($query)
    {
        return $query->where('bloqueada', true);
    }

    public function scopeEditaveis($query)
    {
        return $query->where('bloqueada', false);
    }
}