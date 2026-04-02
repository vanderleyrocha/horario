<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Validators;

interface ConstraintValidator
{
    public function validate(array $payload): array;
}
