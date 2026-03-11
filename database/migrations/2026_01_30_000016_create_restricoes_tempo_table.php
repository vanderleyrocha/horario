<?php
// database/migrations/2026_02_01_003_create_restricoes_tempo_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restricoes_tempo', function (Blueprint $table) {
            $table->id();

            // Relacionamento com Horário
            $table->foreignId('horario_id')
                ->constrained('horarios')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Relacionamento Polimórfico (Professor, Turma ou Disciplina)
            $table->morphs('entidade'); // Cria entidade_type e entidade_id

            // Tempo Específico
            $table->integer('dia_semana')->comment('1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado');
            $table->integer('tempo')->comment('Número do tempo/aula no dia (1, 2, 3, 4, 5, ...)');

            // Status da Restrição
            $table->enum('status', ['livre', 'preferencial', 'bloqueado'])
                ->default('livre')
                ->comment('livre: sem restrição | preferencial: evitar se possível | bloqueado: não usar');

            // Informações Adicionais
            $table->text('motivo')->nullable()->comment('Motivo do bloqueio ou preferência');
            $table->integer('peso')->default(1)->comment('Peso da restrição (1-10). Maior = mais importante');

            $table->timestamps();

            // Índices compostos para performance
            $table->index(['horario_id', 'dia_semana', 'tempo']);
            $table->index(['entidade_type', 'entidade_id', 'horario_id']);
            $table->index('status');

            // Unique constraint - evitar duplicação de restrições
            $table->unique(
                ['horario_id', 'entidade_type', 'entidade_id', 'dia_semana', 'tempo'],
                'restricoes_unique_constraint'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restricoes_tempo');
    }
};
