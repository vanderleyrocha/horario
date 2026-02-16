<?php

declare(strict_types=1);

namespace App\Services\GeneticAlgorithm\Genetico\Entities;

final readonly class Gene
{
    public function __construct(
        private int $aulaId,
        private int $professorId,
        private int $turmaId,
        private int $disciplinaId,
        private int $diaSemana,
        private int $periodoDia,
        private int $duracaoTempos
    ) {}

    public function getAulaId(): int
    {
        return $this->aulaId;
    }

    public function getProfessorId(): int
    {
        return $this->professorId;
    }

    public function getTurmaId(): int
    {
        return $this->turmaId;
    }

    public function getDisciplinaId(): int
    {
        return $this->disciplinaId;
    }

    public function getDiaSemana(): int
    {
        return $this->diaSemana;
    }

    public function getPeriodoDia(): int
    {
        return $this->periodoDia;
    }

    public function getDuracaoTempos(): int
    {
        return $this->duracaoTempos;
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos utilitários industriais
    |--------------------------------------------------------------------------
    */

    public function withDiaPeriodo(int $diaSemana, int $periodoDia): self
    {
        return new self(
            $this->aulaId,
            $this->professorId,
            $this->turmaId,
            $this->disciplinaId,
            $diaSemana,
            $periodoDia,
            $this->duracaoTempos
        );
    }

    public function withProfessor(int $professorId): self
    {
        return new self(
            $this->aulaId,
            $professorId,
            $this->turmaId,
            $this->disciplinaId,
            $this->diaSemana,
            $this->periodoDia,
            $this->duracaoTempos
        );
    }

    public function withTurma(int $turmaId): self
    {
        return new self(
            $this->aulaId,
            $this->professorId,
            $turmaId,
            $this->disciplinaId,
            $this->diaSemana,
            $this->periodoDia,
            $this->duracaoTempos
        );
    }
}