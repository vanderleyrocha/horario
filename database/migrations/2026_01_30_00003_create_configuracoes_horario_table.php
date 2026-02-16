<?php
// database/migrations/2026_02_01_001_create_configuracoes_horario_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_horario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horario_id')
                ->constrained('horarios')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Informações da Escola
            $table->string('nome_escola')->nullable();

            // Configurações de Tempo
            $table->integer('aulas_por_dia')->default(5)->comment('Quantidade de aulas por dia');
            $table->integer('dias_semana')->default(5)->comment('Dias letivos na semana (1=Segunda a 5=Sexta)');

            // Horários
            $table->time('horario_inicio')->default('07:00')->comment('Início do primeiro tempo');
            $table->time('horario_fim')->default('12:00')->comment('Fim do último tempo');

            // Duração das Aulas
            $table->integer('duracao_aula_minutos')->default(50)->comment('Duração de cada aula em minutos');
            $table->integer('duracao_intervalo_minutos')->default(15)->comment('Duração padrão do intervalo');

            // Configuração de Intervalos
            $table->json('horarios_intervalos')->nullable()->comment('Array com posições dos intervalos. Ex: [2,4] = após 2ª e 4ª aula');
            $table->json('duracoes_intervalos')->nullable()->comment('Array com durações específicas. Ex: {"2":15,"4":20}');

            // Configurações Adicionais
            $table->boolean('permitir_janelas')->default(false)->comment('Permitir janelas (tempos livres) no horário');
            $table->boolean('agrupar_disciplinas')->default(true)->comment('Tentar agrupar aulas da mesma disciplina');
            $table->integer('max_aulas_seguidas')->default(3)->comment('Máximo de aulas seguidas da mesma disciplina');

            $table->integer('elitism_count')->default(10);
            $table->float('target_fitness')->default(95.0);
            $table->integer('max_generations_without_improvement')->default(50);
        
            $table->timestamps();

            // Índices
            $table->index('horario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_horario');
    }
};
