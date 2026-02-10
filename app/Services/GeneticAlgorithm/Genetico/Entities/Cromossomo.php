<?php

namespace App\Services\GeneticAlgorithm\Genetico\Entities;

use App\Models\ConfiguracaoHorario;
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessResult;
use Illuminate\Support\Collection;

class Cromossomo
{
    /** @var Collection<int, Gene> */
    public Collection $genes;
    public ?FitnessResult $fitnessResult = null;
    public string $id;

    private ConfiguracaoHorario $configuracao;

    public function __construct(ConfiguracaoHorario $configuracao, ?Collection $genes = null)
    {
        $this->configuracao = $configuracao;
        $this->genes = $genes ?? new Collection();
        $this->id = uniqid('cromossomo_');
    }

    public function addGene(Gene $gene): void
    {
        $this->genes->add($gene);
    }

    public function getGenesAtTimeSlot(int $diaSemana, int $periodoDia): Collection
    {
        return $this->genes->filter(function(Gene $gene) use ($diaSemana, $periodoDia) {
            return $gene->diaSemana === $diaSemana && $gene->periodoDia === $periodoDia;
        });
    }

    public function getProfessorGenes(int $professorId): Collection
    {
        return $this->genes->filter(function(Gene $gene) use ($professorId) {
            return !$gene->isEmpty() && $gene->professor && $gene->professor->id === $professorId;
        });
    }

    public function getTurmaGenes(int $turmaId): Collection
    {
        return $this->genes->filter(function(Gene $gene) use ($turmaId) {
            return !$gene->isEmpty() && $gene->turma && $gene->turma->id === $turmaId;
        });
    }

    public function clone(): self
    {
        $newGenes = $this->genes->map(fn(Gene $gene) => $gene->clone());
        $newCromossomo = new self($this->configuracao, $newGenes);
        return $newCromossomo;
    }

    public function setFitnessResult(FitnessResult $result): void
    {
        $this->fitnessResult = $result;
    }

    public function getFitnessScore(): float
    {
        return $this->fitnessResult ? $this->fitnessResult->totalScore : 0.0;
    }

    public function getConfiguracao(): ConfiguracaoHorario
    {
        return $this->configuracao;
    }
}
