<?php

namespace App\Modules\AG\Domain\Intensification\LNS\DTO;

use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Representation\Entities\Gene;

class PartialSolution
{
    public function __construct(private array $assigned, private array $unassigned) {}

    public function assigned(): array
    {
        return $this->assigned;
    }

    public function unassigned(): array
    {
        return $this->unassigned;
    }

    /**
     * 🔧 PRIORIDADE 10: Extrair AffectedRegion dos genes removidos (destroyed).
     * Usado para delta evaluation na repair phase.
     *
     * Genes unassigned foram movidos -> afetam professores, turmas, dias, períodos.
     */
    public function buildAffectedRegion(): AffectedRegion
    {
        $geneIndexes = [];
        $professores = [];
        $turmas = [];
        $dias = [];
        $periodos = [];

        foreach ($this->unassigned as $index => $gene) {
            if (! $gene instanceof Gene) {
                continue;
            }

            $geneIndexes[] = (int) $index;
            $professores[] = $gene->professorId();
            $turmas[] = $gene->turmaId();
            $dias[] = $gene->diaSemana();
            $periodos[] = $gene->periodoDia();

            // Se gene tem duração > 1, afeta múltiplos períodos
            for ($offset = 1; $offset < $gene->duracaoTempos(); $offset++) {
                $periodos[] = $gene->periodoDia() + $offset;
            }
        }

        return new AffectedRegion(
            geneIndexes: array_unique($geneIndexes),
            professores: array_unique($professores),
            turmas: array_unique($turmas),
            dias: array_unique($dias),
            periodos: array_unique($periodos)
        );
    }
}
