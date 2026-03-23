<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

final class BestIndividualsMigration implements MigrationPolicyInterface
{
    public function __construct(private readonly int $migrants = 1) {}

    public function migrate(array $islands): void
    {
        $count = count($islands);

        if ($count < 2) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $source = $islands[$i];
            $target = $islands[($i + 1) % $count];
            $population = $source->population();
            usort($population, fn ($a, $b) => $b->fitness() <=> $a->fitness());
            for ($j = 0; $j < $this->migrants && $j < count($population); $j++) {
                $target->injectIndividual($population[$j]->copy());
            }
        }
    }
}
