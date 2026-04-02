<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Exceptions;

use InvalidArgumentException;

final class InvalidScheduleConstraintException extends InvalidArgumentException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function single(string $message): self
    {
        return new self($message, [$message]);
    }

    /**
     * @param list<string> $errors
     */
    public static function fromErrors(array $errors): self
    {
        $messages = array_values(array_filter($errors, static fn (string $error): bool => trim($error) !== ''));

        return new self(
            implode(' ', $messages),
            $messages,
        );
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
