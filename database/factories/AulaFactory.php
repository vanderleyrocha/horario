<?php

namespace Database\Factories;

use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aula>
 */
class AulaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'horario_id' => Horario::factory(),
            'professor_id' => Professor::factory(), // Certifique-se de ter ProfessorFactory
            'disciplina_id' => Disciplina::factory(),
            'turma_id' => Turma::factory(),

            'aulas_semana' => $this->faker->numberBetween(1, 4),
            'tipo' => 'simples',
            'aulas_consecutivas' => false,
            'preferencia_periodo' => 'qualquer',
            'ativa' => true,
        ];
    }
}
