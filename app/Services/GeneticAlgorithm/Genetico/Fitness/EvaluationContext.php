<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness;

use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;

final class EvaluationContext {

    public readonly array $indexProfessor;
    public readonly array $indexTurma;
    public readonly array $indexAula;
    
    public readonly array $genes;

    /**
     * Estrutura:
     * [
     *   'professor' => [professorId => [dia => [tempo => true]]],
     *   'turma'     => [turmaId     => [dia => [tempo => true]]],
     * ]
     */
    public readonly array $restricoesIndexadas;

    /**
     * [ aulaId => cargaEsperada ]
     */
    public readonly array $cargaEsperada;

    /**
     * [ aulaId => [dia1, dia2, ...] ]
     */
    public readonly array $diasPreferidos;

    /**
     * [ aulaId => [tempo1, tempo2, ...] ]
     */
    public readonly array $temposPreferidos;

    public function __construct(
        array $genes,
        array $restricoesIndexadas,
        array $cargaEsperada,
        array $diasPreferidos = [],
        array $temposPreferidos = []
    ) {
        $this->genes = $genes;
        $this->restricoesIndexadas = $restricoesIndexadas;
        $this->cargaEsperada = $cargaEsperada;
        $this->diasPreferidos = $diasPreferidos;
        $this->temposPreferidos = $temposPreferidos;
    }
}
