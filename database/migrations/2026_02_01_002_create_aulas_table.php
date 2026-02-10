<?php
// database/migrations/2026_02_01_002_create_aulas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aulas', function (Blueprint $table) {
            $table->id();

            // Relacionamentos
            $table->foreignId('horario_id')
                ->constrained('horarios')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('professor_id')
                ->constrained('professores')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('disciplina_id')
                ->constrained('disciplinas')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('turma_id')
                ->constrained('turmas')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Configurações da Aula
            $table->integer('aulas_semana')->default(2)->comment('Quantidade de aulas desta disciplina na semana');

            $table->enum('tipo', ['simples', 'dupla', 'tripla'])
                ->default('simples')
                ->comment('Tipo de aula: simples (1 tempo), dupla (2 tempos), tripla (3 tempos)');

            // Preferências de Distribuição
            $table->boolean('aulas_consecutivas')->default(false)->comment('Preferir que as aulas sejam consecutivas (geminadas)');
            $table->integer('max_aulas_dia')->default(2)->comment('Máximo de aulas desta disciplina por dia');
            $table->integer('min_intervalo_dias')->default(0)->comment('Mínimo de dias entre aulas (0 = sem restrição)');

            // Preferências de Horário
            $table->enum('preferencia_periodo', ['manha', 'tarde', 'qualquer'])
                ->default('qualquer')
                ->comment('Preferência de período para esta aula');

            $table->json('dias_preferidos')->nullable()->comment('Array de dias preferidos [1,2,3,4,5]');
            $table->json('tempos_preferidos')->nullable()->comment('Array de tempos preferidos [1,2,3,4,5]');

            // Controle
            $table->boolean('ativa')->default(true);
            $table->text('observacoes')->nullable();

            $table->timestamps();

            // Índices
            $table->index(['horario_id', 'turma_id']);
            $table->index(['horario_id', 'professor_id']);
            $table->index(['horario_id', 'disciplina_id']);
            $table->index('ativa');

            $table->index(['horario_id', 'professor_id', 'disciplina_id', 'turma_id'], 'aulas_unique_combination');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aulas');
    }
};
