<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE schedule_executions
             MODIFY status ENUM('running','cancel_requested','cancelled','finished','failed')
             COLLATE utf8mb4_unicode_ci
             NOT NULL DEFAULT 'running'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('schedule_executions')
            ->whereIn('status', ['cancel_requested', 'cancelled'])
            ->update(['status' => 'failed']);

        DB::statement(
            "ALTER TABLE schedule_executions
             MODIFY status ENUM('running','finished','failed')
             COLLATE utf8mb4_unicode_ci
             NOT NULL DEFAULT 'running'"
        );
    }
};
