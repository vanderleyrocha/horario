<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alocacoes', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relacionamento com horário
            |--------------------------------------------------------------------------
            */

            $table->foreignId('horario_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Execução do solver que gerou esta alocação
            |--------------------------------------------------------------------------
            */

            $table->foreignId('execution_id')
                ->nullable()
                ->constrained('schedule_executions')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Aula relacionada
            |--------------------------------------------------------------------------
            */

            $table->foreignId('aula_id')
                ->nullable()
                ->constrained('aulas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            /*
            |--------------------------------------------------------------------------
            | Entidades
            |--------------------------------------------------------------------------
            */

            $table->foreignId('turma_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('disciplina_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('professor_id')
                ->constrained('professores')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Posição no horário
            |--------------------------------------------------------------------------
            */

            $table->enum('dia_semana', [
                'segunda',
                'terca',
                'quarta',
                'quinta',
                'sexta',
            ]);

            $table->integer('tempo')
                ->comment('Posição da aula no dia (1,2,3...)');

            $table->integer('duracao_tempos')
                ->default(1)
                ->comment('1=simples,2=dupla,3=tripla');

            /*
            |--------------------------------------------------------------------------
            | Controle
            |--------------------------------------------------------------------------
            */

            $table->boolean('eh_manual')
                ->default(false)
                ->comment('Alocação feita manualmente');

            $table->boolean('bloqueada')
                ->default(false)
                ->comment('Não pode ser alterada pelo solver');

            /*
            |--------------------------------------------------------------------------
            | Horários
            |--------------------------------------------------------------------------
            */

            $table->time('horario_inicio');
            $table->time('horario_fim');

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices importantes para performance
            |--------------------------------------------------------------------------
            */

            $table->index(['horario_id', 'dia_semana', 'tempo']);
            $table->index(['turma_id', 'dia_semana', 'tempo']);
            $table->index(['professor_id', 'dia_semana', 'tempo']);

            $table->index('aula_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alocacoes');
    }
};
