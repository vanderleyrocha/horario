<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

interface MigrationPolicyInterface
{
    /**
     * @param  Island[]  $islands
     */
    public function migrate(array $islands): void;
}
