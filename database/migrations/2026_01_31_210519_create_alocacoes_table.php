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
            $table->foreignId('horario_id')->constrained()->onDelete('cascade');
            $table->foreignId('aula_id')->nullable()->constrained('aulas')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('turma_id')->constrained()->onDelete('cascade');
            $table->foreignId('disciplina_id')->constrained()->onDelete('cascade');
            $table->foreignId('professor_id')->constrained('professores')->onDelete('cascade');
            
            
            $table->enum('dia_semana', ['segunda', 'terca', 'quarta', 'quinta', 'sexta']);
            $table->integer('tempo')->comment('Posição da aula no dia (1, 2, 3, ...)');
            $table->integer('duracao_tempos')->default(1)->comment('Duração em tempos (1=simples, 2=dupla, 3=tripla)');
            
            $table->boolean('eh_manual')->default(false)->comment('Alocação foi feita manualmente');
            $table->boolean('bloqueada')->default(false)->comment('Não pode ser modificada pelo algoritmo');

            $table->time('horario_inicio');
            $table->time('horario_fim');
            $table->timestamps();

            // $table->unique(['horario_id', 'turma_id', 'dia_semana', 'tempo'], 'unique_alocacao_turma');
            // $table->unique(['horario_id', 'professor_id', 'dia_semana', 'tempo'], 'unique_alocacao_professor');

            
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
