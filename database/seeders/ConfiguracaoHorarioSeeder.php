<?php
// database/seeders/ConfiguracaoHorarioSeeder.php

namespace Database\Seeders;

use App\Models\Horario;
use App\Models\ConfiguracaoHorario;
use Illuminate\Database\Seeder;

class ConfiguracaoHorarioSeeder extends Seeder
{
    public function run(): void
    {
        // Criar configuração para horários existentes
        $horarios = Horario::all();

        foreach ($horarios as $horario) {
            ConfiguracaoHorario::create([
                'horario_id' => $horario->id,
                'nome_escola' => 'Escola Exemplo',
                'aulas_por_dia' => 5,
                'dias_semana' => 5,
                'horario_inicio' => '07:00',
                'horario_fim' => '12:00',
                'duracao_aula_minutos' => 50,
                'duracao_intervalo_minutos' => 15,
                'horarios_intervalos' => [2, 4], // Intervalo após 2ª e 4ª aula
                'duracoes_intervalos' => [
                    '2' => 15,
                    '4' => 20,
                ],
                'permitir_janelas' => false,
                'agrupar_disciplinas' => true,
                'max_aulas_seguidas' => 3,
            ]);
        }
    }
}
