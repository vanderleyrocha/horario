<?php

namespace Database\Factories;

use App\Models\Aula;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Alocacao>
 */
class AlocacaoFactory extends Factory
{
    public function definition(): array
    {
        // Por padrão, cria uma nova aula e usa os dados dela para manter integridade
        return [
            'aula_id' => Aula::factory(), 
            
            // Define os relacionamentos baseados na aula criada acima para garantir consistência
            'horario_id' => function (array $attributes) {
                return Aula::find($attributes['aula_id'])->horario_id;
            },
            'turma_id' => function (array $attributes) {
                return Aula::find($attributes['aula_id'])->turma_id;
            },
            'disciplina_id' => function (array $attributes) {
                return Aula::find($attributes['aula_id'])->disciplina_id;
            },
            'professor_id' => function (array $attributes) {
                return Aula::find($attributes['aula_id'])->professor_id;
            },

            'dia_semana' => $this->faker->randomElement(['segunda', 'terca', 'quarta', 'quinta', 'sexta']),
            'tempo' => $this->faker->numberBetween(1, 5),
            'duracao_tempos' => 1,
            'horario_inicio' => '07:00:00',
            'horario_fim' => '07:50:00',
            'eh_manual' => false,
            'bloqueada' => false,
        ];
    }
}