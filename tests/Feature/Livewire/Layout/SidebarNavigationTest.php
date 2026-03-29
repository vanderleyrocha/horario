<?php

use App\Livewire\Layout\Sidebar;
use Livewire\Livewire;

it('organizes the sidebar around the horarios module context', function () {
    Livewire::test(Sidebar::class)
        ->assertSee('Geral')
        ->assertSee('Modulo Horarios')
        ->assertSee('Cadastros e planejamento do solver')
        ->assertSeeInOrder([
            'Dashboard',
            'Horarios',
            'Professores',
            'Turmas',
            'Disciplinas',
        ]);
});
