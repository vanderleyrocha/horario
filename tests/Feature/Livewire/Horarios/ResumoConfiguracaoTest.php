<?php

use App\Livewire\Horarios\ResumoConfiguracao;
use App\Models\Alocacao;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\Horario;
use App\Models\Turma;
use App\Models\Professor;
use Illuminate\Support\Collection;
use Livewire\Livewire;

it('calcula e estrutura a propriedade aulasPorTurma corretamente', function () {
    // 1. Arrange (Preparação)
    $horario = Horario::factory()->create();
    $turmaA = Turma::factory()->create(['nome' => 'Turma A']);
    $disciplina = Disciplina::factory()->create(['nome' => 'Matemática']);
    $professor = Professor::factory()->create();
    
    // Cria uma Aula associada à Turma A e ao Horário
    $aula = Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turmaA->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
    ]);

    // Cria duas alocações para essa aula
    // CORREÇÃO: Usar strings ('segunda') em vez de inteiros (1)
    Alocacao::factory()->create([
        'aula_id' => $aula->id,
        'horario_id' => $horario->id,
        'turma_id' => $turmaA->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'dia_semana' => 'segunda', 
        'tempo' => 1,
        'duracao_tempos' => 1
    ]);
    
    Alocacao::factory()->create([
        'aula_id' => $aula->id,
        'horario_id' => $horario->id,
        'turma_id' => $turmaA->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'dia_semana' => 'segunda',
        'tempo' => 2,
        'duracao_tempos' => 1
    ]);

    // 2. Act & Assert (Ação e Verificação)
    Livewire::test(ResumoConfiguracao::class, ['horario' => $horario])
        ->assertOk()
        ->assertViewHas('aulasPorTurma', function ($collection) use ($turmaA, $disciplina) {
            
            expect($collection)->toBeInstanceOf(Collection::class);
            
            // Pega os dados da primeira turma
            $dadosTurma = $collection->first();

            // Verifica a estrutura
            expect($dadosTurma)
                ->toHaveKeys(['turma', 'horario_detalhado', 'total_aulas', 'total_tempos', 'disciplinas'])
                ->and($dadosTurma['turma']->id)->toBe($turmaA->id)
                ->and($dadosTurma['total_aulas'])->toBe(2)
                ->and($dadosTurma['disciplinas']->first()->id)->toBe($disciplina->id);

            // Verifica grade [dia][tempo]
            // Nota: O índice agora é 'segunda' (string) ou o valor que seu código usa para agrupar.
            // Se o seu código PHP converte 'segunda' para 1 internamente, ajuste aqui.
            // Assumindo que ele mantém o valor do banco ('segunda'):
            expect($dadosTurma['horario_detalhado'])
                ->toHaveKey('segunda') 
                ->and($dadosTurma['horario_detalhado']['segunda'])->toHaveKeys([1, 2]);

            return true;
        });
});

it('filtra alocacoes orfãs que não possuem aula associada', function () {
    $horario = Horario::factory()->create();
    
    // Cenário Válido: Alocação com Aula e Turma
    $turma = Turma::factory()->create();
    $aulaValida = Aula::factory()->create(['horario_id' => $horario->id, 'turma_id' => $turma->id]);
    
    // Cria alocação válida
    Alocacao::factory()->create([
        'aula_id' => $aulaValida->id, 
        'horario_id' => $horario->id,
        'turma_id' => $turma->id,
        'dia_semana' => 'segunda', // CORREÇÃO: String
        'tempo' => 1
    ]);

    // Cenário Inválido (Órfão): Alocação sem Aula (aula_id = null)
    // Isso simula um erro de integridade lógica onde a alocação existe mas a aula sumiu
    // Precisamos passar os IDs manualmente porque a factory tentaria pegar da aula_id (que é null)
    Alocacao::factory()->create([
        'aula_id' => null, // <--- Permitido pelo banco (nullable)
        'horario_id' => $horario->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $aulaValida->disciplina_id,
        'professor_id' => $aulaValida->professor_id,
        'dia_semana' => 'terca',
        'tempo' => 1
    ]);

    Livewire::test(ResumoConfiguracao::class, ['horario' => $horario])
        ->assertViewHas('aulasPorTurma', function ($collection) use ($turma) {
            
            // Deve ter apenas 1 grupo (da turma válida) e ignorar o erro ou tratar corretamente
            // O count exato depende de como seu código agrupa. 
            // Se agrupa por turma, a turma ainda existe, mas a alocação inválida deve ser ignorada nos cálculos internos?
            // Ou o teste verifica apenas que não quebra (crash).
            
            expect($collection)->not->toBeEmpty();
            
            // Se o seu código filtra alocações sem aula:
            $dados = $collection->first();
            
            // Se a lógica estiver correta, 'total_aulas' deve ser 1 (apenas a válida) e não 2
            expect($dados['total_aulas'])->toBe(1);
            
            return true;
        });
});

it('retorna coleção vazia se não houver aulas', function () {
    $horario = Horario::factory()->create();

    Livewire::test(ResumoConfiguracao::class, ['horario' => $horario])
        ->assertViewHas('aulasPorTurma', function ($collection) {
            return $collection->isEmpty();
        });
});