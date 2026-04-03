<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Representation\Entities;

use InvalidArgumentException;

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
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->aulaId <= 0) {
            throw new InvalidArgumentException('aulaId inválido');
        }

        if ($this->professorId <= 0) {
            throw new InvalidArgumentException('professorId inválido');
        }

        if ($this->turmaId <= 0) {
            throw new InvalidArgumentException('turmaId inválido');
        }

        if ($this->diaSemana <= 0) {
            throw new InvalidArgumentException('diaSemana inválido');
        }

        if ($this->periodoDia <= 0) {
            throw new InvalidArgumentException('periodoDia inválido');
        }

        if ($this->duracaoTempos <= 0) {
            throw new InvalidArgumentException('duracaoTempos inválido');
        }
    }

    /* ============================================================
     |  GETTERS PADRONIZADOS
     ============================================================ */

    public function aulaId(): int
    {
        return $this->aulaId;
    }

    public function professorId(): int
    {
        return $this->professorId;
    }

    public function turmaId(): int
    {
        return $this->turmaId;
    }

    public function disciplinaId(): int
    {
        return $this->disciplinaId;
    }

    public function diaSemana(): int
    {
        return $this->diaSemana;
    }

    public function periodoDia(): int
    {
        return $this->periodoDia;
    }

    public function duracaoTempos(): int
    {
        return $this->duracaoTempos;
    }

    /* ============================================================
     |  MÉTODOS UTILITÁRIOS
     ============================================================ */

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

    public function timeslots(): array
    {
        $slots = [];

        for ($i = 0; $i < $this->duracaoTempos; $i++) {
            $slots[] = $this->periodoDia + $i;
        }

        return $slots;
    }

    /**
     * Verifica conflito estrutural entre dois genes
     */
    public function conflictsWith(self $other): bool
    {
        if ($this->diaSemana !== $other->diaSemana) {
            return false;
        }

        for ($i = 0; $i < $this->duracaoTempos; $i++) {

            $tempoA = $this->periodoDia + $i;

            for ($j = 0; $j < $other->duracaoTempos; $j++) {

                $tempoB = $other->periodoDia + $j;

                if ($tempoA === $tempoB) {

                    if ($this->professorId === $other->professorId) {
                        return true;
                    }

                    if ($this->turmaId === $other->turmaId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function endPeriodo(): int
    {
        return $this->periodoDia + $this->duracaoTempos - 1;
    }
}
