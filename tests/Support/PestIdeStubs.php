<?php

declare(strict_types=1);

use Pest\Expectation;

if (! function_exists('expect')) {
    /**
     * Stub para análise estática quando o editor não resolve as funções globais do Pest.
     */
    function expect(mixed $value = null): Expectation
    {
        return new Expectation($value);
    }
}

if (! function_exists('it')) {
    /**
     * Stub para análise estática quando o editor não resolve as funções globais do Pest.
     */
    function it(string $description, ?\Closure $closure = null): mixed
    {
        return test(sprintf('it %s', $description), $closure);
    }
}
