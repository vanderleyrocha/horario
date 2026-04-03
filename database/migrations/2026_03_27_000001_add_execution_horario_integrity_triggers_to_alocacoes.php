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

        DB::unprepared('DROP TRIGGER IF EXISTS trg_alocacoes_validate_execution_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_alocacoes_validate_execution_update');

        DB::unprepared(
            <<<'SQL'
            CREATE TRIGGER trg_alocacoes_validate_execution_insert
            BEFORE INSERT ON alocacoes
            FOR EACH ROW
            BEGIN
                IF NEW.execution_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1
                    FROM schedule_executions se
                    WHERE se.id = NEW.execution_id
                      AND se.horario_id = NEW.horario_id
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'execution_id invalido para o horario da alocacao';
                END IF;
            END
            SQL
        );

        DB::unprepared(
            <<<'SQL'
            CREATE TRIGGER trg_alocacoes_validate_execution_update
            BEFORE UPDATE ON alocacoes
            FOR EACH ROW
            BEGIN
                IF NEW.execution_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1
                    FROM schedule_executions se
                    WHERE se.id = NEW.execution_id
                      AND se.horario_id = NEW.horario_id
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'execution_id invalido para o horario da alocacao';
                END IF;
            END
            SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_alocacoes_validate_execution_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_alocacoes_validate_execution_update');
    }
};
