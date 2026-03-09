<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use Spatie\Async\Pool;

final class IslandModelEngine {
    /** @var Island[] */
    private array $islands = [];

    public function __construct(
        private readonly MigrationPolicyInterface $migrationPolicy,
        private readonly int $migrationInterval = 20,
        private readonly int $concurrency = 4
    ) {
    }

    public function addIsland(Island $island): void {
        $this->islands[] = $island;
    }

    public function run(int $generations): Cromossomo {
        foreach ($this->islands as $island) {
            $island->initialize();
        }

        $globalBest = null;

        for ($generation = 0; $generation < $generations; $generation++) {

            $pool = Pool::create()->concurrency($this->concurrency);

            $results = [];

            foreach ($this->islands as $index => $island) {

                $pool->add(function () use ($island) {
                    return $island->evolveGeneration();
                })
                    ->then(function (Cromossomo $best) use (&$results, $index) {
                        $results[$index] = $best;
                    });
            }

            $pool->wait();

            foreach ($results as $best) {

                if (
                    $globalBest === null ||
                    $best->fitness() > $globalBest->fitness()
                ) {

                    $globalBest = $best;
                }
            }

            if ($generation % $this->migrationInterval === 0) {

                $this->migrationPolicy->migrate($this->islands);
            }
        }

        if ($globalBest === null) {
            throw new \RuntimeException('Island model produced no individuals.');
        }

        return $globalBest;
    }
}
