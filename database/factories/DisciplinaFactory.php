<?php

namespace Database\Factories;

use App\Models\Disciplina;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disciplina>
 */
class DisciplinaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => $this->faker->unique()->word().' '.$this->faker->randomLetter(),
            'codigo' => strtoupper($this->faker->unique()->bothify('DISC-###')),
            'carga_horaria_semanal' => $this->faker->numberBetween(2, 6),
            'descricao' => $this->faker->sentence(),
            'cor' => $this->faker->hexColor(),
            'ativa' => true,
        ];
    }
}
