<?php

namespace App\Services\GeneticAlgorithm\Genetico\Repositories;

use App\Models\Horario;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;

class ConfiguracaoHorarioReadRepository
{
    /**
     * Obtém todas as configurações necessárias para o algoritmo genético para um dado Horario.
     *
     * @param Horario $horario
     * @return GeneticAlgorithmConfigDTO
     * @throws \Exception Se a ConfiguracaoHorario não for encontrada.
     */
    public function getForHorario(Horario $horario): GeneticAlgorithmConfigDTO
    {
        return GeneticAlgorithmConfigDTO::fromModels($horario);
    }
}
