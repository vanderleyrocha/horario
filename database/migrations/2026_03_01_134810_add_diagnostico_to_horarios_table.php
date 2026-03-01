<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('horarios', function (Blueprint $table) {
            $table->json('diagnostico_json')->nullable()->after('fitness_score');
            $table->unsignedTinyInteger('indice_risco')->nullable()->after('diagnostico_json');
        });
    }

    public function down(): void {
        Schema::table('horarios', function (Blueprint $table) {
            $table->dropColumn(['diagnostico_json', 'indice_risco']);
        });
    }
};
