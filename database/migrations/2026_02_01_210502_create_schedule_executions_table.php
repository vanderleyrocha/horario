<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_executions', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relacionamento com Horário
            |--------------------------------------------------------------------------
            */

            $table->foreignId('horario_id')->constrained('horarios')->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Controle da execução
            |--------------------------------------------------------------------------
            */

            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();

            $table->enum('status', [
                'running',
                'finished',
                'failed',
            ])->default('running');

            /*
            |--------------------------------------------------------------------------
            | Parâmetros do algoritmo
            |--------------------------------------------------------------------------
            */

            $table->integer('population_size')->nullable();
            $table->integer('island_count')->nullable();
            $table->integer('generations')->nullable();
            $table->integer('generations_without_improvement')->nullable();

            $table->json('parameters_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Resultados finais
            |--------------------------------------------------------------------------
            */

            $table->double('best_fitness')->nullable();
            $table->double('avg_fitness')->nullable();

            $table->integer('execution_time_ms')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Controle
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index('horario_id');
            $table->index('status');
            $table->index(['horario_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_executions');
    }
};
