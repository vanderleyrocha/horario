<?php

namespace App\Services\GeneticAlgorithm\Genetico\Entities;

final class Gene
{
    public readonly ?int $aulaId;
    public readonly ?int $professorId;
    public readonly ?int $disciplinaId;
    public readonly ?int $turmaId;

    public readonly int $diaSemana;
    public readonly int $periodoDia;
    public readonly int $duracaoTempos;

    public function __construct(
        ?int $aulaId,
        int $diaSemana,
        int $periodoDia,
        int $duracaoTempos = 1,
        ?int $professorId = null,
        ?int $disciplinaId = null,
        ?int $turmaId = null
    ) {
        $this->aulaId = $aulaId;
        $this->diaSemana = $diaSemana;
        $this->periodoDia = $periodoDia;
        $this->duracaoTempos = $duracaoTempos;
        $this->professorId = $professorId;
        $this->disciplinaId = $disciplinaId;
        $this->turmaId = $turmaId;
    }

    public function isEmpty(): bool
    {
        return $this->aulaId === null;
    }

    public function copy(): self
    {
        return new self(
            $this->aulaId,
            $this->diaSemana,
            $this->periodoDia,
            $this->duracaoTempos,
            $this->professorId,
            $this->disciplinaId,
            $this->turmaId
        );
    }
}