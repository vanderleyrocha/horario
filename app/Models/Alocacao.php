<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Alocacao extends Model
{
    use HasFactory;

    protected $table = 'alocacoes';

    protected $fillable = [
        'horario_id',
        'execution_id',
        'aula_id',
        'turma_id',
        'disciplina_id',
        'professor_id',
        'dia_semana',
        'tempo',
        'duracao_tempos',
        'eh_manual',
        'bloqueada',
        'horario_inicio',
        'horario_fim',
    ];

    protected $casts = [
        'horario_id' => 'integer',
        'execution_id' => 'integer',
        'aula_id' => 'integer',
        'turma_id' => 'integer',
        'disciplina_id' => 'integer',
        'professor_id' => 'integer',
        'tempo' => 'integer',
        'duracao_tempos' => 'integer',
        'horario_inicio' => 'string',
        'horario_fim' => 'string',
        'eh_manual' => 'boolean',
        'bloqueada' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $alocacao): void {
            if ($alocacao->execution_id === null) {
                return;
            }

            if ($alocacao->exists && ! $alocacao->isDirty(['execution_id', 'horario_id'])) {
                return;
            }

            $execution = ScheduleExecution::query()
                ->select(['id', 'horario_id'])
                ->find($alocacao->execution_id);

            if (! $execution) {
                throw ValidationException::withMessages([
                    'execution_id' => 'A execucao informada para a alocacao nao existe.',
                ]);
            }

            if ((int) $execution->horario_id !== (int) $alocacao->horario_id) {
                throw ValidationException::withMessages([
                    'execution_id' => 'A execucao informada pertence a outro horario.',
                ]);
            }
        });
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ScheduleExecution::class, 'execution_id');
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
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
