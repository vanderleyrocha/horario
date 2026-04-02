<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Exceptions;

use RuntimeException;

final class ScheduleConstraintNotFoundException extends RuntimeException
{
    public static function forContext(int $constraintId, int $horarioId): self
    {
        return new self(sprintf(
            'Constraint %d nao foi encontrada no contexto do horario %d.',
            $constraintId,
            $horarioId,
        ));
    }
}
