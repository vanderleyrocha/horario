<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Professor>
 */
class ProfessorFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'nome' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'telefone' => $this->faker->phoneNumber(),

            // Define disponibilidade padrão para todos os dias úteis para facilitar testes de alocação
            'dias_disponiveis' => ['segunda', 'terca', 'quarta', 'quinta', 'sexta'],

            'carga_horaria_maxima' => $this->faker->numberBetween(20, 40),
            'ativo' => true,
        ];
    }
}
