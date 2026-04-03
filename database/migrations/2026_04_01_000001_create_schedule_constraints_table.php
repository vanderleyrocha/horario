<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_constraints', function (Blueprint $table) {
            $table->id();

            $table->foreignId('horario_id')
                ->constrained('horarios')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 64);
            $table->string('level', 16);
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('payload_json');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('horario_id');
            $table->index('type');
            $table->index('is_active');
            $table->index(['horario_id', 'is_active']);
            $table->index(['horario_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_constraints');
    }
};
