<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

use Illuminate\Support\Facades\Log;

final class ProfileAwareBestIndividualsMigration implements MigrationPolicyInterface
{
    /**
     * @param array<string, int> $migrantsByProfile Nº de migrantes a enviar por perfil de origem.
     * @param array<string, string|null> $directionalMatrix Perfil de destino preferencial por perfil de origem; null = round-robin.
     */
    public function __construct(
        private readonly array $migrantsByProfile,
        private readonly array $directionalMatrix = [],
    ) {
    }

    public function migrate(array $islands): void
    {
        $count = count($islands);

        if ($count < 2) {
            return;
        }

        // Indexa ilhas por perfil para busca direcional.
        /** @var array<string, list<Island>> $islandsByProfile */
        $islandsByProfile = [];

        foreach ($islands as $island) {
            $islandsByProfile[$island->profile()->value][] = $island;
        }

        for ($index = 0; $index < $count; $index++) {
            /** @var Island $source */
            $source = $islands[$index];
            $sourceProfile = $source->profile()->value;

            // Resolve ilha de destino: direcional (por perfil preferencial) ou round-robin.
            $target = $this->resolveTarget($source, $islands, $islandsByProfile, $index, $count);

            $sourcePopulation = $source->population();
            $targetBestBefore = $target->best()->fitness();

            usort($sourcePopulation, static fn ($left, $right): int => $right->fitness() <=> $left->fitness());

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
                'directional' => isset($this->directionalMatrix[$sourceProfile]) && $this->directionalMatrix[$sourceProfile] !== null,
                'migrants' => $migrated,
                'target_best_before' => $targetBestBefore,
                'target_best_after' => $targetBestAfter,
                'target_best_delta' => round($targetBestAfter - $targetBestBefore, 6),
            ]);
        }
    }

    /**
     * Resolve a ilha de destino para a fonte dada.
     * Tenta primeiro a matriz direcional; faz fallback para round-robin.
     *
     * @param Island[] $islands
     * @param array<string, list<Island>> $islandsByProfile
     */
    private function resolveTarget(
        Island $source,
        array $islands,
        array $islandsByProfile,
        int $sourceIndex,
        int $count,
    ): Island {
        $sourceProfile = $source->profile()->value;
        $preferredTargetProfile = $this->directionalMatrix[$sourceProfile] ?? null;

        if ($preferredTargetProfile !== null && isset($islandsByProfile[$preferredTargetProfile])) {
            // Pega a primeira ilha do perfil-alvo que não seja a própria origem.
            foreach ($islandsByProfile[$preferredTargetProfile] as $candidate) {
                if ($candidate->getislandNum() !== $source->getislandNum()) {
                    return $candidate;
                }
            }
        }

        // Fallback: próxima ilha em ordem circular.
        return $islands[($sourceIndex + 1) % $count];
    }
}
