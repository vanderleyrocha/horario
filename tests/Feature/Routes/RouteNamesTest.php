<?php

use Illuminate\Support\Facades\Route;

it('has required named routes for horarios and algoritmo', function () {
    expect(Route::has('horarios.index'))->toBeTrue();
    expect(Route::has('horarios.manage'))->toBeTrue();
    expect(Route::has('algoritmo.index'))->toBeTrue();
});

