<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_generation_metrics', function (Blueprint $table) {
            $table->string('landscape_phenomenon')->nullable()->after('landscape_state');
            $table->json('landscape_observation')->nullable()->after('landscape_phenomenon');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_generation_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'landscape_phenomenon',
                'landscape_observation',
            ]);
        });
    }
};
