<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Disciplina>
 */
class DisciplinaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => $this->faker->unique()->word() . ' ' . $this->faker->randomLetter(),
            'codigo' => strtoupper($this->faker->unique()->bothify('DISC-###')),
            'carga_horaria_semanal' => $this->faker->numberBetween(2, 6),
            'descricao' => $this->faker->sentence(),
            'cor' => $this->faker->hexColor(),
            'ativa' => true,
        ];
    }
}