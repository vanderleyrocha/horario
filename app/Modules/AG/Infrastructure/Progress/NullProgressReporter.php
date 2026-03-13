<?php

namespace App\Modules\AG\Infrastructure\Progress;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;

class NullProgressReporter implements ProgressReporterInterface
{
    public function report(array $data): void
    {
        // não faz nada
    }
}
