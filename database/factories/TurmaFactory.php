<?php

namespace Database\Factories;

use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turma>
 */
class TurmaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => 'Turma '.$this->faker->unique()->bothify('##?'),
            'codigo' => strtoupper($this->faker->unique()->bothify('TUR-###')),
            'serie' => $this->faker->numberBetween(1, 9),
            'turno' => $this->faker->randomElement(['matutino', 'vespertino', 'noturno', 'integral']),
            'numero_alunos' => $this->faker->numberBetween(20, 50),
            'ano' => $this->faker->year(),
            'ativa' => true,
        ];
    }
}
