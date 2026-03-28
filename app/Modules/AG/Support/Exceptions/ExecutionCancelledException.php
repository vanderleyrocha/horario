<?php

declare(strict_types=1);

namespace App\Modules\AG\Support\Exceptions;

use RuntimeException;

final class ExecutionCancelledException extends RuntimeException
{
    public static function forExecution(int $executionId): self
    {
        return new self("Execution {$executionId} was cancelled.");
    }
}
