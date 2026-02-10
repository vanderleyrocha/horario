<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Turma>
 */
class TurmaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => 'Turma ' . $this->faker->unique()->bothify('##?'),
            'codigo' => strtoupper($this->faker->unique()->bothify('TUR-###')),
            'turno' => $this->faker->randomElement(['matutino', 'vespertino', 'noturno', 'integral']),
            'numero_alunos' => $this->faker->numberBetween(20, 50),
            'ano' => $this->faker->year(),
            'ativa' => true,
        ];
    }
}