<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Conflict;

class ConflictSet {
    public function __construct(private readonly array $conflicts) {
    }

    public function all(): array {
        return $this->conflicts;
    }
}
