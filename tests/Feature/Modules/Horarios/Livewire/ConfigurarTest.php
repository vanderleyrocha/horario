<?php

use App\Models\ConfiguracaoHorario;
use App\Models\Horario;
use App\Modules\Horarios\UI\Livewire\Configurar;
use Livewire\Livewire;

it('persists the main genetic algorithm settings into horario configuracao', function () {
    $horario = Horario::factory()->create();

    ConfiguracaoHorario::create([
        'horario_id' => $horario->id,
        'nome_escola' => 'Escola Teste',
        'aulas_por_dia' => 5,
        'dias_semana' => 5,
        'horario_inicio' => '07:00',
        'horario_fim' => '12:00',
        'duracao_aula_minutos' => 50,
        'duracao_intervalo_minutos' => 15,
        'horarios_intervalos' => [2],
        'duracoes_intervalos' => [15],
        'permitir_janelas' => false,
        'agrupar_disciplinas' => true,
        'max_aulas_seguidas' => 3,
    ]);

    Livewire::test(Configurar::class, ['horario' => $horario])
        ->set('populacao', 240)
        ->set('geracoes', 900)
        ->set('taxa_mutacao', 0.12)
        ->set('taxa_crossover', 0.82)
        ->set('taxa_elitismo', 0.08)
        ->set('taxa_mutacao_min', 0.02)
        ->set('taxa_mutacao_max', 0.4)
        ->set('limite_estagnacao', 35)
        ->set('target_fitness', 97.5)
        ->set('max_generations_without_improvement', 120)
        ->call('salvarConfiguracaoAlgoritmoGenetico')
        ->assertHasNoErrors();

    $horario->refresh();
    $horario->load('configuracaoHorario');

    expect($horario->configuracao)
        ->toMatchArray([
            'populacao' => 240,
            'geracoes' => 900,
            'taxa_mutacao' => 0.12,
            'taxa_crossover' => 0.82,
            'taxa_elitismo' => 0.08,
            'taxa_mutacao_min' => 0.02,
            'taxa_mutacao_max' => 0.4,
            'limite_estagnacao' => 35,
            'target_fitness' => 97.5,
            'geracoes_sem_melhoria' => 120,
        ]);

    expect($horario->configuracaoHorario->elitism_count)->toBe(19)
        ->and($horario->configuracaoHorario->target_fitness)->toBe(97.5)
        ->and($horario->configuracaoHorario->max_generations_without_improvement)->toBe(120);
});
