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
        'horario_inicio' => 'datetime:H:i',
        'horario_fim' => 'datetime:H:i',
        'eh_manual' => 'boolean',
        'bloqueada' => 'boolean'
    ];

    protected static function booted(): void
    {
        static::saving(function (self $alocacao): void {
            if (is_null($alocacao->execution_id)) {
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
                    'execution_id' => 'A execução informada para a alocação não existe.',
                ]);
            }

            if ((int) $execution->horario_id !== (int) $alocacao->horario_id) {
                throw ValidationException::withMessages([
                    'execution_id' => 'A execução informada pertence a outro horário.',
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
