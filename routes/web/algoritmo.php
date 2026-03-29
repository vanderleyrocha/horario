<?php

use App\Models\Horario;
use App\Modules\AG\UI\Http\Controllers\ExecutionLogViewerController;
use App\Modules\AG\UI\Livewire\ExecutionCenter;
use App\Modules\AG\UI\Livewire\ExecutionDashboard;
use App\Modules\AG\UI\Livewire\ExecutionMetricsStream;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/algoritmo/{horario}', ExecutionCenter::class)->name('algoritmo.center');
    Route::get('/algoritmo/index/{horario}', fn (Horario $horario) => redirect()->route('algoritmo.center', $horario))->name('algoritmo.index');

    Route::get('/algoritmo/execution/{execution}', ExecutionDashboard::class)->name('algoritmo.execution');
    Route::get('/algoritmo/execution/{execution}/logs', ExecutionLogViewerController::class)->name('algoritmo.execution.logs');

    Route::get('/algoritmo/execution/{execution}/stream', ExecutionMetricsStream::class)->name('algoritmo.stream');
    Route::get('/algoritmo/execution/{execution}/metrics', ExecutionMetricsStream::class)->name('algoritmo.metrics');
});
