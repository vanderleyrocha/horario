<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->integer('ano');
            $table->integer('semestre');
            $table->json('configuracao')->nullable(); // Configurações do algoritmo genético
            $table->float('fitness_score')->nullable();

            $table->integer('geracoes_executadas')->nullable();
            $table->integer('geracoes_sem_melhoria')->nullable();
            $table->float('melhor_fitness')->nullable();
            $table->float('fitness_medio')->nullable();
            $table->json('historico_fitness')->nullable()->comment('Array com evolução do fitness');

            // Estatísticas de Conflitos
            $table->integer('conflitos_hard')->default(0)->comment('Conflitos críticos (professor/turma)');
            $table->integer('conflitos_soft')->default(0)->comment('Violações de preferências');
            $table->json('detalhes_conflitos')->nullable();

            // Tempo de Processamento
            $table->integer('tempo_processamento_segundos')->nullable();

            // Dados do Usuário
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('ativado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('ativado_em')->nullable();

            $table->enum('status', ['rascunho', 'em_geracao', 'concluido', 'ativo'])->default('rascunho');
            $table->timestamp('gerado_em')->nullable();
            $table->timestamps();


            // Índices
            $table->index('criado_por');
            $table->index(['status', 'ano']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
