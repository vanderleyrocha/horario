<?php

namespace App\Modules\AG\Domain\Intensification\LNS\DTO;

use App\Modules\AG\Domain\Representation\Entities\Gene;

class PartialSolution {
    public function __construct(private array $assigned, private array $unassigned) {
    }

    public function assigned(): array {
        return $this->assigned;
    }

    public function unassigned(): array {
        return $this->unassigned;
    }
}
