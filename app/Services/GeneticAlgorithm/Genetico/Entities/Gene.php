<?php

namespace App\Services\GeneticAlgorithm\Genetico\Entities;

use App\Models\Aula;
use App\Models\Professor;
use App\Models\Disciplina;
use App\Models\Turma;

class Gene
{
    public ?Aula $aula;
    public ?Professor $professor;
    public ?Disciplina $disciplina;
    public ?Turma $turma;
    public int $diaSemana;
    public int $periodoDia;
    public int $duracaoTempos;

    public function __construct(
        ?Aula $aula,
        int $diaSemana,
        int $periodoDia,
        int $duracaoTempos = 1,
        ?Professor $professor = null,
        ?Disciplina $disciplina = null,
        ?Turma $turma = null
    ) {
        $this->aula = $aula;
        $this->diaSemana = $diaSemana;
        $this->periodoDia = $periodoDia;
        $this->duracaoTempos = $duracaoTempos;
        $this->professor = $professor ?? $aula?->professor;
        $this->disciplina = $disciplina ?? $aula?->disciplina;
        $this->turma = $turma ?? $aula?->turma;
    }

    public function isEmpty(): bool
    {
        return $this->aula === null;
    }

    public function clone(): self
    {
        return new self(
            $this->aula,
            $this->diaSemana,
            $this->periodoDia,
            $this->duracaoTempos,
            $this->professor,
            $this->disciplina,
            $this->turma
        );
    }
}
