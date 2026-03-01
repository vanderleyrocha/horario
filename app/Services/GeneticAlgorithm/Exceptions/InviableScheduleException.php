<?php

namespace App\Services\GeneticAlgorithm\Exceptions;

use Exception;
use App\Services\GeneticAlgorithm\Support\AGError;

final class InviableScheduleException extends Exception {
    private AGError $error;

    public function __construct(AGError $error) {
        parent::__construct($error->mensagem);
        $this->error = $error;
    }

    public function getError(): AGError {
        return $this->error;
    }
}
