<?php

namespace App\Modules\AG\Domain\Contracts;

interface ProgressReporterInterface {
    public function report(array $data): void;
}
