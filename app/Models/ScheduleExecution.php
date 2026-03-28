<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScheduleExecution extends Model
{
    use HasFactory;

    protected $table = 'schedule_executions';

    protected $fillable = [
        'horario_id',
        'start_time',
        'end_time',
        'status',
        'population_size',
        'island_count',
        'generations',
        'generations_without_improvement',
        'parameters_json',
        'best_fitness',
        'avg_fitness',
        'execution_time_ms',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'parameters_json' => 'array',
        'population_size' => 'integer',
        'island_count' => 'integer',
        'generations' => 'integer',
        'generations_without_improvement' => 'integer',
        'best_fitness' => 'float',
        'avg_fitness' => 'float',
        'execution_time_ms' => 'integer',
    ];

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ScheduleGenerationMetric::class, 'execution_id');
    }

    public function latestMetric(): HasOne
    {
        return $this->hasOne(ScheduleGenerationMetric::class, 'execution_id')
            ->ofMany('generation', 'max');
    }

    public function alocacoes(): HasMany
    {
        return $this->hasMany(Alocacao::class, 'execution_id');
    }
}
