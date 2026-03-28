<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleGenerationMetric extends Model
{
    protected $table = 'schedule_generation_metrics';

    protected $fillable = [
        'execution_id',
        'generation',
        'best_fitness',
        'avg_fitness',
        'variance',
        'diversity',
        'entropy',
        'mutation_rate',
        'crossover_rate',
        'operator_used',
        'operator_reward',
        'alns_destroy_operator',
        'alns_repair_operator',
        'alns_improvement',
        'landscape_state',
        'landscape_phenomenon',
        'landscape_observation',
        'stagnation',
    ];

    protected $casts = [
        'generation' => 'integer',
        'best_fitness' => 'float',
        'avg_fitness' => 'float',
        'variance' => 'float',
        'diversity' => 'float',
        'entropy' => 'float',
        'mutation_rate' => 'float',
        'crossover_rate' => 'float',
        'operator_reward' => 'float',
        'alns_improvement' => 'float',
        'landscape_observation' => 'array',
        'stagnation' => 'integer',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ScheduleExecution::class, 'execution_id');
    }
}
