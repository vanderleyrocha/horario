<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\UserManager;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/users', UserManager::class)->name('users.index');
    Route::get('/perfil', fn () => 'Em desenvolvimento')->name('profile');
    Route::get('/configuracoes', fn () => 'Em desenvolvimento')->name('configuracoes');
});

Route::get('/home', function () {
    /** @var \Illuminate\Contracts\Auth\Guard $auth */
    $auth = auth();

    return $auth->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

