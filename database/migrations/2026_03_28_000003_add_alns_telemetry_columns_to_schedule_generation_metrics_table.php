<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_generation_metrics', function (Blueprint $table) {
            $table->string('alns_destroy_operator')->nullable()->after('operator_reward');
            $table->string('alns_repair_operator')->nullable()->after('alns_destroy_operator');
            $table->double('alns_improvement')->nullable()->after('alns_repair_operator');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_generation_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'alns_destroy_operator',
                'alns_repair_operator',
                'alns_improvement',
            ]);
        });
    }
};
