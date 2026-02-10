<?php

test('soma de dois números', function () {
    $result = 10 + 5;

    // Uso da API de Expectation do Pest
    expect($result)->toBe(15);
});