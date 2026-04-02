<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Validators;

use App\Modules\Horarios\Domain\Constraints\Exceptions\InvalidScheduleConstraintException;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\ConstraintTargetGroup;

abstract class AbstractConstraintValidator implements ConstraintValidator
{
    /**
     * @param list<string> $keys
     */
    protected function requireKeys(array $payload, array $keys): void
    {
        $errors = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                $errors[] = sprintf('Payload requer a chave "%s".', $key);
            }
        }

        if ($errors !== []) {
            throw InvalidScheduleConstraintException::fromErrors($errors);
        }
    }

    protected function requireTargetGroup(array $payload, string $key): ConstraintTargetGroup
    {
        if (! array_key_exists($key, $payload) || ! is_array($payload[$key])) {
            throw InvalidScheduleConstraintException::single(sprintf('Payload requer o grupo "%s".', $key));
        }

        $group = $payload[$key];

        if (! array_key_exists('lesson_ids', $group) || ! is_array($group['lesson_ids'])) {
            throw InvalidScheduleConstraintException::single(sprintf('Payload requer "%s.lesson_ids" como lista.', $key));
        }

        if (! array_is_list($group['lesson_ids'])) {
            throw InvalidScheduleConstraintException::single(sprintf('Campo "%s.lesson_ids" deve ser uma lista.', $key));
        }

        $lessonIds = [];

        foreach ($group['lesson_ids'] as $index => $lessonId) {
            if (! is_int($lessonId)) {
                throw InvalidScheduleConstraintException::single(sprintf('Campo "%s.lesson_ids.%d" deve ser inteiro.', $key, $index));
            }

            $lessonIds[] = $lessonId;
        }

        try {
            return new ConstraintTargetGroup($lessonIds);
        } catch (\InvalidArgumentException $exception) {
            throw InvalidScheduleConstraintException::single($exception->getMessage());
        }
    }

    protected function optionalTargetGroup(array $payload, string $key): ?ConstraintTargetGroup
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        if (! is_array($payload[$key])) {
            throw InvalidScheduleConstraintException::single(sprintf('Payload requer o grupo "%s".', $key));
        }

        return $this->requireTargetGroup($payload, $key);
    }

    /**
     * @return list<int>
     */
    protected function requireOptionalIntList(array $payload, string $key): array
    {
        if (! array_key_exists($key, $payload)) {
            return [];
        }

        if (! is_array($payload[$key])) {
            throw InvalidScheduleConstraintException::single(sprintf('Campo "%s" deve ser uma lista.', $key));
        }

        if (! array_is_list($payload[$key])) {
            throw InvalidScheduleConstraintException::single(sprintf('Campo "%s" deve ser uma lista indexada.', $key));
        }

        $values = [];

        foreach ($payload[$key] as $index => $value) {
            if (! is_int($value)) {
                throw InvalidScheduleConstraintException::single(sprintf('Campo "%s.%d" deve ser inteiro.', $key, $index));
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * @template T of \BackedEnum
     * @param class-string<T> $enumClass
     * @return T
     */
    protected function requireEnumValue(array $payload, string $key, string $enumClass): \BackedEnum
    {
        if (! array_key_exists($key, $payload) || ! is_string($payload[$key])) {
            throw InvalidScheduleConstraintException::single(sprintf('Campo "%s" deve ser uma string valida.', $key));
        }

        $enum = $enumClass::tryFrom($payload[$key]);

        if ($enum === null) {
            throw InvalidScheduleConstraintException::single(sprintf('Campo "%s" recebeu valor invalido: %s.', $key, $payload[$key]));
        }

        return $enum;
    }
}
