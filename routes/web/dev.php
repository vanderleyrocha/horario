<?php

use App\Http\Controllers\Dev\TesteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/teste/alocacoes', [TesteController::class, 'alocacoes'])->name('teste.alocacoes');
});

