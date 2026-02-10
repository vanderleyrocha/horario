<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Horario>
 */
class HorarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => $this->faker->sentence(3) . ' ' . $this->faker->year(),
            'ano' => $this->faker->year(),
            'semestre' => $this->faker->numberBetween(1, 2),
            'status' => 'rascunho', // Valor padrão do enum
            'conflitos_hard' => 0,
            'conflitos_soft' => 0,
            // 'criado_por' => \App\Models\User::factory(), // Descomente se tiver UserFactory
        ];
    }
}