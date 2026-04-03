<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_generation_metrics', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Execução associada
            |--------------------------------------------------------------------------
            */

            $table->foreignId('execution_id')
                ->constrained('schedule_executions')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Geração
            |--------------------------------------------------------------------------
            */

            $table->integer('generation');

            /*
            |--------------------------------------------------------------------------
            | Métricas de fitness
            |--------------------------------------------------------------------------
            */

            $table->double('best_fitness')->nullable();
            $table->double('avg_fitness')->nullable();
            $table->double('variance')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Métricas de diversidade
            |--------------------------------------------------------------------------
            */

            $table->double('diversity')->nullable();
            $table->double('entropy')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Parâmetros evolutivos
            |--------------------------------------------------------------------------
            */

            $table->double('mutation_rate')->nullable();
            $table->double('crossover_rate')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Hyper-Heuristic telemetry
            |--------------------------------------------------------------------------
            */

            $table->string('operator_used')->nullable();
            $table->double('operator_reward')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Landscape Analysis
            |--------------------------------------------------------------------------
            */

            $table->string('landscape_state')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Controle de estagnação
            |--------------------------------------------------------------------------
            */

            $table->integer('stagnation')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index('execution_id');
            $table->index(['execution_id', 'generation']);
            $table->index('generation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_generation_metrics');
    }
};
