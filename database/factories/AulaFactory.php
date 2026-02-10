<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Aula>
 */
class AulaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'horario_id' => \App\Models\Horario::factory(),
            'professor_id' => \App\Models\Professor::factory(), // Certifique-se de ter ProfessorFactory
            'disciplina_id' => \App\Models\Disciplina::factory(),
            'turma_id' => \App\Models\Turma::factory(),
            
            'aulas_semana' => $this->faker->numberBetween(1, 4),
            'tipo' => 'simples',
            'aulas_consecutivas' => false,
            'preferencia_periodo' => 'qualquer',
            'ativa' => true,
        ];
    }
}