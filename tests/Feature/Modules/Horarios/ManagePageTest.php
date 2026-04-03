<?php

use App\Models\Horario;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected when accessing horarios manage page', function () {
    $horario = Horario::factory()->create();

    get(route('horarios.manage', $horario))
        ->assertRedirect(route('login'));
});

test('authenticated users can access horarios manage page tabs', function () {
    $user = User::create([
        'name' => 'Teste Manage',
        'email' => 'manage+' . Str::uuid() . '@example.com',
        'password' => 'password123',
    ]);

    $horario = Horario::factory()->create();

    actingAs($user);

    $tabs = ['overview', 'constraints', 'config', 'aulas', 'restricoes', 'algoritmo', 'diagnostico'];

    foreach ($tabs as $tab) {
        get(route('horarios.manage', $horario) . '?tab=' . $tab)
            ->assertOk();
    }
});
