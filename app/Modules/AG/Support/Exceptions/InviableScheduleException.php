<?php

declare(strict_types=1);

namespace App\Modules\AG\Support\Exceptions;

use Exception;
use App\Modules\AG\Support\AGError;

final class InviableScheduleException extends Exception {
    private AGError $error;

    public function __construct(AGError $error) {
        parent::__construct(message: $error->mensagem(), code: 0);

        $this->error = $error;
    }

    public function getError(): AGError {
        return $this->error;
    }

    public function toArray(): array {
        return $this->error->toArray();
    }
}
