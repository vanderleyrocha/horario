<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

use Illuminate\Support\Facades\Log;

final class ProfileAwareBestIndividualsMigration implements MigrationPolicyInterface
{
    /**
     * @param array<string, int> $migrantsByProfile
     */
    public function __construct(private readonly array $migrantsByProfile)
    {
    }

    public function migrate(array $islands): void
    {
        $count = count($islands);

        if ($count < 2) {
            return;
        }

        for ($index = 0; $index < $count; $index++) {
            /** @var Island $source */
            $source = $islands[$index];
            /** @var Island $target */
            $target = $islands[($index + 1) % $count];

            $sourcePopulation = $source->population();
            $targetBestBefore = $target->best()->fitness();

            usort($sourcePopulation, static fn ($left, $right): int => $right->fitness() <=> $left->fitness());

            $sourceProfile = $source->profile()->value;
            $migrants = max(1, (int) ($this->migrantsByProfile[$sourceProfile] ?? 1));
            $migrated = 0;

            for ($migrantIndex = 0; $migrantIndex < $migrants && $migrantIndex < count($sourcePopulation); $migrantIndex++) {
                $target->injectIndividual($sourcePopulation[$migrantIndex]->copy());
                $migrated++;
            }

            $targetBestAfter = $target->best()->fitness();

            Log::info('ga.islands.migration.profile_aware', [
                'source_island' => $source->getislandNum(),
                'target_island' => $target->getislandNum(),
                'source_profile' => $sourceProfile,
                'target_profile' => $target->profile()->value,
                'migrants' => $migrated,
                'target_best_before' => $targetBestBefore,
                'target_best_after' => $targetBestAfter,
                'target_best_delta' => round($targetBestAfter - $targetBestBefore, 6),
            ]);
        }
    }
}
