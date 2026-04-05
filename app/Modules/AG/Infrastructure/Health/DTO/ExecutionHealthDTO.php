<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Health\DTO;

final class ExecutionHealthDTO
{
    /**
     * @param list<string> $recommendations
     */
    public function __construct(
        public readonly string $health,
        public readonly string $dominantPhase,
        public readonly array $recommendations,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'health' => $this->health,
            'dominant_phase' => $this->dominantPhase,
            'recommendations' => $this->recommendations,
        ];
    }
}
