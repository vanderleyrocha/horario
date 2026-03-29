<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('solver_operator_usage');
        Schema::dropIfExists('solver_landscape_history');
        Schema::dropIfExists('solver_generation_metrics');
        Schema::dropIfExists('solver_executions');
    }

    public function down(): void
    {
        Schema::create('solver_executions', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('horario_id');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->integer('population_size')->nullable();
            $table->integer('island_count')->nullable();
            $table->integer('max_generations')->nullable();
            $table->double('best_fitness')->nullable();
            $table->integer('generations_executed')->nullable();
            $table->string('status', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('solver_generation_metrics', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('execution_id');
            $table->integer('generation')->nullable();
            $table->double('best_fitness')->nullable();
            $table->double('avg_fitness')->nullable();
            $table->double('variance')->nullable();
            $table->double('diversity')->nullable();
            $table->double('entropy')->nullable();
            $table->double('mutation_rate')->nullable();
            $table->integer('stagnation')->nullable();
            $table->string('landscape_state', 50)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['execution_id', 'generation'], 'idx_execution_generation');
        });

        Schema::create('solver_landscape_history', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('execution_id')->nullable();
            $table->integer('generation')->nullable();
            $table->string('state', 30)->nullable();
            $table->double('diversity')->nullable();
            $table->double('entropy')->nullable();
            $table->double('variance')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('solver_operator_usage', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('execution_id')->nullable();
            $table->integer('generation')->nullable();
            $table->string('operator_name', 100)->nullable();
            $table->integer('usage_count')->nullable();
            $table->double('avg_reward')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
};
