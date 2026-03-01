<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('execucao_algoritmos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('horario_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('fitness_score', 6, 2)->nullable();
            $table->integer('geracoes_executadas')->nullable();
            $table->integer('tempo_execucao_ms')->nullable();

            $table->json('parametros_utilizados')->nullable();

            $table->enum('status', [
                'em_execucao',
                'concluida',
                'falhou'
            ])->default('em_execucao');

            $table->boolean('ativa')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('execucao_algoritmos');
    }
};
