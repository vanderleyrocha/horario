<?php

use App\Models\Horario;
use App\Models\User;
use Illuminate\Support\Str;

it('keeps direct horarios module CRUD pages accessible after view consolidation', function () {
    $user = User::create([
        'name' => 'Teste CRUD Horarios',
        'email' => 'crud-horarios+'.Str::uuid().'@example.com',
        'password' => 'password123',
    ]);

    $horario = Horario::factory()->create();

    $this->actingAs($user);

    $this->get(route('professores.index'))->assertOk();
    $this->get(route('turmas.index'))->assertOk();
    $this->get(route('disciplinas.index'))->assertOk();
    $this->get(route('aulas.index', ['horario_id' => $horario->id]))->assertOk();
});
