<?php

namespace App\Modules\AG\Domain\Intensification\Kempe;

class KempeChain
{
    public array $genes = [];

    public int $slotA;

    public int $slotB;

    public function __construct(int $slotA, int $slotB)
    {
        $this->slotA = $slotA;
        $this->slotB = $slotB;
    }

    public function addGene(int $geneId): void
    {
        $this->genes[$geneId] = $geneId;
    }
}
