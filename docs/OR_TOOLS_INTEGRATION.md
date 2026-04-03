# Integração OR-Tools + Laravel — Projeto Horário

> **Status**: Proposta arquitetural — não implementada
> **Contexto**: Extensão do solver híbrido documentado em [AG_ARCHITECTURE.md](AG_ARCHITECTURE.md)
> **Objetivo**: Usar Google OR-Tools como construtor adicional de sementes viáveis para a população inicial do AG, sem substituir nenhuma outra etapa do pipeline

---

## Índice

1. [Motivação](#1-motivação)
2. [Princípio central](#2-princípio-central)
3. [Passo 1 — Definir a fronteira arquitetural](#passo-1--definir-a-fronteira-arquitetural)
4. [Passo 2 — Usar sidecar, não binding nativo](#passo-2--usar-sidecar-não-binding-nativo)
5. [Passo 3 — Introduzir portfólio de construtores iniciais](#passo-3--introduzir-portfólio-de-construtores-iniciais)
6. [Passo 4 — Modelar o snapshot de entrada](#passo-4--modelar-o-snapshot-de-entrada)
7. [Passo 5 — Definir o escopo do modelo CP-SAT](#passo-5--definir-o-escopo-do-modelo-cp-sat)
8. [Passo 6 — Definir o contrato de retorno do sidecar](#passo-6--definir-o-contrato-de-retorno-do-sidecar)
9. [Passo 7 — Converter seed para cromossomo local](#passo-7--converter-seed-para-cromossomo-local)
10. [Passo 8 — Preservar o quality gate sem exceções](#passo-8--preservar-o-quality-gate-sem-exceções)
11. [Passo 9 — Orquestrar na camada de aplicação](#passo-9--orquestrar-na-camada-de-aplicação)
12. [Passo 10 — Controlar por flags em config/ag.php](#passo-10--controlar-por-flags-em-configagphp)
13. [Passo 11 — Fallback duro obrigatório](#passo-11--fallback-duro-obrigatório)
14. [Passo 12 — Telemetria separada para seeds OR-Tools](#passo-12--telemetria-separada-para-seeds-or-tools)
15. [Passo 13 — Proteger a diversidade inicial](#passo-13--proteger-a-diversidade-inicial)
16. [Passo 14 — Não replicar todas as constraints no primeiro corte](#passo-14--não-replicar-todas-as-constraints-no-primeiro-corte)
17. [Passo 15 — Rollout em três fases](#passo-15--rollout-em-três-fases)
18. [Passo 16 — Definir testes antes da adoção total](#passo-16--definir-testes-antes-da-adoção-total)
19. [Desenho final do fluxo](#desenho-final-do-fluxo)
20. [Estrutura de arquivos proposta](#estrutura-de-arquivos-proposta)
21. [Referências](#referências)

---

## 1. Motivação

O principal gargalo do solver hoje é a população inicial. Execuções com problemas saturados passam até 30 minutos em tentativas GRASP que geram cromossomos inviáveis ou com `hard_penalty` alto, conforme documentado em [docs/diagnostics/SOLVER_LOG_ANALYSIS_2026-03-29.md](diagnostics/SOLVER_LOG_ANALYSIS_2026-03-29.md).

O Google OR-Tools com CP-SAT é um solver de satisfação de restrições especializado na detecção de soluções viáveis para problemas de scheduling. Usado como gerador de sementes, ele pode:

- Produzir cromossomos candidatos com `hard_penalty` zero ou muito baixo desde o início
- Reduzir o número de tentativas GRASP rejeitadas pelo quality gate
- Diminuir o tempo gasto em repair antes da evolução
- Fornecer um ponto de partida mais qualificado para o ALNS e os operadores evolutivos

A hipótese central é que **uma população inicial com mais sementes viáveis permite que o AG foque mais em otimizar qualidade _(soft)_ do que em reparar viabilidade _(hard)_**.

---

## 2. Princípio central

**OR-Tools sugere. O solver do projeto valida, repara e evolui.**

OR-Tools nunca entra no domínio como fonte de verdade de fitness, de score lexicográfico ou de viabilidade. A avaliação final de todo cromossomo — inclusive os gerados por OR-Tools — continua sendo feita pelo `FitnessEvaluator` em PHP, com as regras hard e soft já existentes.

Isso garante que:
1. O score lexicográfico `[0, 50)` inviável / `[50, 100]` viável permanece a única hierarquia aceita
2. O quality gate continua sendo a barreira de aceite de qualquer seed
3. Divergências entre o modelo CP-SAT e o domínio real são naturalmente corrigidas pelo repair local
4. Constraints customizadas que não foram modeladas no OR-Tools são detectadas e penalizadas normalmente

---

## Passo 1 — Definir a fronteira arquitetural

### Responsabilidades de cada camada

| Responsabilidade | Dono |
|---|---|
| Otimização, fitness, score lexicográfico | Solver PHP / `FitnessEvaluator` |
| Geração de seed candidata de baixo custo | OR-Tools (via sidecar) |
| Aceite, repair e quality gate da seed | `ScheduleProblem` |
| Telemetria e ciclo de vida da execução | `GerarHorarioJob` + `ExecutionMetricsRecorder` |
| Configuração de quando usar OR-Tools | `config/ag.php` + `RunGeneticAlgorithm` |
| Ciclo de evolução, ALNS, ilhas | Inalterado |

### Regras de fronteira

1. `ScheduleProblem` é a **única** peça que decide se uma seed é aceita ou rejeitada.
2. OR-Tools **não** recebe modelos Eloquent. Recebe apenas um snapshot serializado imutável.
3. OR-Tools **não** conhece o score lexicográfico. Apenas tenta satisfazer as constraints que recebeu.
4. Uma seed retornada pelo sidecar **nunca** bypassa o quality gate, repair ou fail-fast.
5. Falha do sidecar **nunca** derruba a execução. O fallback para GRASP é obrigatório.

---

## Passo 2 — Usar sidecar, não binding nativo

### Por que não binding PHP direto

Não existe binding oficial do OR-Tools para PHP. As alternativas de FFI ou subprocess PHP são frágeis, difíceis de versionar e não toleram bem timeouts e isolamento de memória. Para este projeto — que já usa `spatie/async` e `queue:listen` — a abordagem de sidecar é mais alinhada com o padrão existente.

### Arquitetura do sidecar

```
[RunGeneticAlgorithm]
        │
        │ serializa snapshot JSON
        ▼
[OrToolsSeedBridge]  ──────────────────►  [sidecar Python]
        │                                     │  import ortools
        │                                     │  from ortools.sat.python import cp_model
        │  retorna seeds JSON                 │  model = cp_model.CpModel()
        ◄─────────────────────────────────────│  solver = cp_model.CpSolver()
        │                                     │  solver.parameters.max_time_in_seconds = N
        │
        │ converte para Cromossomo[]
        ▼
[ScheduleProblem::injectExternalSeeds()]
```

### Onde o sidecar vive

```
sidecar/
    or_tools/
        solver.py          # ponto de entrada
        model_builder.py   # constrói o model CP-SAT a partir do snapshot
        seed_exporter.py   # converte solution para JSON de seed
        requirements.txt   # ortools, grpcio (se necessário)
        README.md
```

> O sidecar pode ser chamado por `proc_open` ou `Symfony Process`. O `OrToolsSeedBridge` em PHP é o único responsável por essa comunicação. Consulte o `Passo 11` para o fallback obrigatório.

---

## Passo 3 — Introduzir portfólio de construtores iniciais

### Estado atual

Hoje toda a construção inicial converge para `ScheduleProblem::createIndividual()`, que tenta em ordem:
1. Seed aceita da execução (`lastAcceptedInitialSeed`)
2. Seed histórica de execuções anteriores
3. GRASP com até N tentativas

### Estado desejado

Adicionar um quarto construtor para seeds externas, consumido antes do GRASP:

```
ScheduleProblem::createIndividual()
    │
    ├── 1. Seed aceita da execução        [existente]
    ├── 2. Seed histórica                 [existente]
    ├── 3. Seed externa (OR-Tools)        ◄── NOVO
    └── 4. GRASP                          [existente, com fallback garantido]
```

### Interface proposta

```php
// app/Modules/AG/Domain/Contracts/ExternalSeedProviderInterface.php

interface ExternalSeedProviderInterface
{
    /**
     * Retorna até $count cromossomos candidatos, ou [] em caso de falha.
     *
     * @param  ScheduleData  $data
     * @param  int           $count
     * @param  int           $timeLimitSeconds
     * @return Cromossomo[]
     */
    public function provide(ScheduleData $data, int $count, int $timeLimitSeconds): array;
}
```

### Implementação concreta

```php
// app/Modules/AG/Infrastructure/OrTools/OrToolsSeedProvider.php

final class OrToolsSeedProvider implements ExternalSeedProviderInterface
{
    public function __construct(
        private readonly OrToolsSeedBridge $bridge,
        private readonly SeedImporter $importer,
    ) {}

    public function provide(ScheduleData $data, int $count, int $timeLimitSeconds): array
    {
        try {
            $snapshot = SnapshotExporter::fromScheduleData($data);
            $response = $this->bridge->call($snapshot, $count, $timeLimitSeconds);
            return $this->importer->import($response, $data);
        } catch (\Throwable $e) {
            Log::warning('or_tools.seed_provider.failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
```

### Injeção no ScheduleProblem

O `ScheduleProblem` recebe `?ExternalSeedProviderInterface $externalSeedProvider` como parâmetro opcional no construtor. Quando `null`, o comportamento atual é preservado integralmente.

```php
public function __construct(
    private readonly ScheduleData $data,
    private readonly EvaluationContextBuilder $contextBuilder,
    private readonly FitnessEvaluator $fitnessEvaluator,
    private readonly GreedyRepairOperator $repairOperator,
    private readonly ?ProgressReporterInterface $progress = null,
    private readonly ?int $executionId = null,
    private readonly ?ExternalSeedProviderInterface $externalSeedProvider = null, // ◄── NOVO
) {}
```

---

## Passo 4 — Modelar o snapshot de entrada

### O que deve ser serializado

O snapshot é gerado a partir do `ScheduleData` já montado por `RunGeneticAlgorithm` — **depois** do build e do carregamento de constraints. Nunca inclua modelos Eloquent.

```json
{
  "meta": {
    "execution_id": 42,
    "horario_id": 3,
    "generated_at": "2026-04-02T10:00:00Z",
    "time_limit_seconds": 30,
    "seed_count_requested": 5,
    "diversity_random_seeds": [1001, 2002, 3003, 4004, 5005]
  },
  "slots": [
    { "id": "seg_1", "day": "seg", "period": 1 }
  ],
  "lessons": [
    {
      "id": "aula_123",
      "disciplina_id": 10,
      "turma_id": 5,
      "professor_id": 7,
      "carga": 2,
      "requires_consecutive": true,
      "preferred_slots": ["seg_1", "seg_2"]
    }
  ],
  "teachers": [
    {
      "id": 7,
      "available_slots": ["seg_1", "seg_2", "ter_1"]
    }
  ],
  "classes": [
    {
      "id": 5,
      "available_slots": ["seg_1", "seg_2", "ter_1"]
    }
  ],
  "constraints": [
    { "type": "SYNC_SAME_TIMESLOT", "lesson_ids": ["aula_123", "aula_456"] },
    { "type": "MUTUAL_EXCLUSION",   "lesson_ids": ["aula_789", "aula_012"] },
    { "type": "TIME_PLACEMENT",     "lesson_id": "aula_123", "allowed_slots": ["seg_1"] }
  ]
}
```

### Regras para o snapshot

1. Todos os IDs são strings para garantir compatibilidade JSON bidirecional.
2. O snapshot é imutável após a serialização.
3. Constraints customizadas estão incluídas, mas o sidecar pode ignorar as que ainda não modela — o solver PHP as detectará.
4. `diversity_random_seeds` serve para garantir diversidade entre as sementes geradas: cada seed é produzida com um seed diferente no `CpSolver`.

### Classe exportadora

```php
// app/Modules/AG/Infrastructure/OrTools/SnapshotExporter.php

final class SnapshotExporter
{
    public static function fromScheduleData(ScheduleData $data): array
    {
        // monta o array a partir dos value objects do ScheduleData
        // retorna array pronto para json_encode()
    }
}
```

---

## Passo 5 — Definir o escopo do modelo CP-SAT

### O que modelar no primeiro corte

O objetivo do modelo CP-SAT **não** é reproduzir o fitness completo do PHP. É encontrar alocações que satisfazem as constraints hard essenciais em tempo curto.

**Incluir obrigatoriamente no modelo CP-SAT:**

| Restricão | Tipo | Justificativa |
|---|---|---|
| Professor em dois slots simultâneos | Hard | Principal causa de `hard_penalty` |
| Turma em dois slots simultâneos | Hard | Principal causa de `hard_penalty` |
| Carga de trabalho total por professor | Hard | Detectável com sum constraint |
| Indisponibilidade de professor | Hard | Simples: slots proibidos |
| Indisponibilidade de turma | Hard | Simples: slots proibidos |
| `SYNC_SAME_TIMESLOT` ativo | Hard | Já serializado no snapshot |
| `MUTUAL_EXCLUSION` ativo | Hard | Já serializado no snapshot |
| `TIME_PLACEMENT` ativo | Hard | Já serializado no snapshot |
| Carga mínima alocada | Hard | 100% das aulas deve ser alocada |

**Deixar para o PHP na primeira versão:**

| Restrição | Razão |
|---|---|
| Score lexycográfico completo | Domínio do PHP |
| `MandatoryBlockViolationRule` complexa | Risco de divergência |
| `WindowPenaltyRule` | Soft, solver PHP detecta |
| `DistributionRule` | Soft, solver PHP detecta |
| Consecutividade de blocos | Opcional, adicionar em v2 |

### Limite de tempo por seed

O CP-SAT deve receber um `max_time_in_seconds` configurável. O raciocínio:

- OR-Tools **não** precisa encontrar a solução ótima. Precisa encontrar uma solução **viável rapidamente**.
- Um valor entre **5 e 30 segundos** por seed é razoável para problemas de porte médio (150–400 aulas).
- O `CpSolver.parameters.max_time_in_seconds` garante que o sidecar nunca bloqueia o processo PHP por muito tempo.

---

## Passo 6 — Definir o contrato de retorno do sidecar

### Formato de resposta

```json
{
  "status": "ok",
  "seeds": [
    {
      "seed_id": "ortools_seed_1001",
      "strategy": "cp_sat_feasible",
      "random_seed_used": 1001,
      "time_seconds": 4.2,
      "solver_status": "FEASIBLE",
      "allocations": [
        { "lesson_id": "aula_123", "slot_id": "seg_1" },
        { "lesson_id": "aula_456", "slot_id": "ter_2" }
      ],
      "meta": {
        "hard_conflicts_estimated": 0,
        "relaxations_applied": [],
        "objective_value": 0
      }
    }
  ],
  "fallback_reason": null,
  "sidecar_version": "1.0.0",
  "elapsed_total_seconds": 18.5
}
```

### Status possíveis

| `solver_status` | Significado |
|---|---|
| `FEASIBLE` | Solução viável encontrada dentro do time limit |
| `OPTIMAL` | Solução ótima encontrada (raro, use como bonus) |
| `INFEASIBLE` | Problema declarado inviável pelo CP-SAT |
| `UNKNOWN` | Time limit esgotado sem solução |
| `MODEL_INVALID` | Snapshot com dados inconsistentes |

### Regras de tratamento no lado PHP

1. Seeds com `solver_status` diferente de `FEASIBLE` ou `OPTIMAL` são descartadas silenciosamente.
2. Seeds válidas passam pelo importador antes de qualquer avaliação.
3. Nenhuma seed substitui o GRASP se não passar pelo quality gate.

---

## Passo 7 — Converter seed para cromossomo local

### Por que a conversão é necessária

O `Cromossomo` em PHP mantém índices auxiliares críticos para o funcionamento do delta fitness, do repair e dos operadores de mutação. Uma lista de pares `(lesson_id, slot_id)` precisar ser convertida para a estrutura interna antes de entrar no solver.

### Classe importadora

```php
// app/Modules/AG/Infrastructure/OrTools/SeedImporter.php

final class SeedImporter
{
    /**
     * @param  array<mixed>  $response  Retorno JSON do sidecar
     * @return Cromossomo[]             Lista de cromossomos locais, possivelmente vazia
     */
    public function import(array $response, ScheduleData $data): array
    {
        $chromosomes = [];

        foreach ($response['seeds'] ?? [] as $seed) {
            if (! in_array($seed['solver_status'] ?? '', ['FEASIBLE', 'OPTIMAL'], true)) {
                continue;
            }

            try {
                $genes = $this->buildGenes($seed['allocations'], $data);
                $chromosomes[] = new Cromossomo($genes);
            } catch (\Throwable $e) {
                Log::warning('or_tools.seed_import.failed', [
                    'seed_id' => $seed['seed_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $chromosomes;
    }

    private function buildGenes(array $allocations, ScheduleData $data): array
    {
        // Para cada alocação, mapeia lesson_id + slot_id para Gene
        // usando os value objects de ScheduleData como referência
    }
}
```

### Pipeline de aceite de cada seed importada

```
SeedImporter::import()
        │
        ▼
Cromossomo[] (sem avaliação ainda)
        │
        ▼ ScheduleProblem::tryInjectExternalSeed()
        ├── repair local (GreedyRepairOperator)
        ├── fail-fast check
        ├── evaluateInitialPopulationQualityGate()
        │       ├── PASS ──► aceita como lastAcceptedInitialSeed
        │       └── FAIL ──► descartada, tenta próxima
        └── telemetria registrada em ambos os casos
```

---

## Passo 8 — Preservar o quality gate sem exceções

Esta é uma **restrição arquitetural** do projeto. Veja [projeto-horario-expert.agent.md](.github/agents/projeto-horario-expert.agent.md):

> NUNCA sugira remover o `Quality Gate` da construção inicial sem uma alternativa de `fail-fast`.

### Por que seeds OR-Tools **também** precisam do quality gate

1. O modelo CP-SAT não implementa todas as regras de fitness do PHP.
2. Constraints customizadas não totalmente modeladas podem resultar em seeds com `hard_penalty` residual.
3. O repair local pode introduzir novas violações.
4. O quality gate é a única barreira confiável entre a construção inicial e a evolução.

### O que o quality gate verifica em toda seed, sem exceção

```
hard_penalty         ≤ thresholds['max_hard_penalty']
hard_conflict_ratio  ≤ thresholds['max_hard_conflict_allocations']
score                ≥ viable_score_threshold (fase relaxada: 0.0, fase estrita: 50.0)
```

Esses thresholds já existem no `ScheduleProblem` e são adaptativos por tentativa. As seeds OR-Tools **não recebem thresholds mais permissivos** do que o GRASP na mesma posição da população.

---

## Passo 9 — Orquestrar na camada de aplicação

### Onde a decisão é tomada

A camada correta é `RunGeneticAlgorithm`, porque é ali que:
1. `ScheduleData` já foi montado
2. Constraints customizadas já foram carregadas
3. `GeneticAlgorithmConfigDTO` já está disponível
4. A decisão de usar OR-Tools não polui nem o domínio nem o job

### Responsabilidade de cada camada

```
GerarHorarioJob
    └── não sabe que OR-Tools existe

RunGeneticAlgorithm
    ├── verifica config('ag.or_tools.enabled')
    ├── instancia OrToolsSeedProvider se habilitado
    └── injeta no ScheduleProblem

ScheduleProblem
    ├── usa o provider se disponível
    ├── aplica quality gate
    └── decide aceite ou rejeição

OrToolsSeedProvider / Bridge / Importer
    └── infraestrutura: comunicação, importação, fallback
```

### Trecho de integração em RunGeneticAlgorithm

```php
$externalSeedProvider = null;

if (config('ag.or_tools.enabled', false)) {
    $externalSeedProvider = new OrToolsSeedProvider(
        bridge: new OrToolsSeedBridge(
            scriptPath: config('ag.or_tools.sidecar_path'),
            timeLimitSeconds: config('ag.or_tools.seed_time_limit_seconds', 20),
        ),
        importer: new SeedImporter(),
    );
}

$problem = new ScheduleProblem(
    data: $scheduleData,
    contextBuilder: new EvaluationContextBuilder(),
    fitnessEvaluator: $fitnessEvaluator,
    repairOperator: $repairOperator,
    progress: $progress,
    executionId: $executionId,
    externalSeedProvider: $externalSeedProvider, // null = comportamento atual preservado
);
```

---

## Passo 10 — Controlar por flags em config/ag.php

### Parâmetros propostos

```php
// Adição a config/ag.php

'or_tools' => [
    'enabled'                 => env('AG_OR_TOOLS_ENABLED', false),
    'sidecar_path'            => env('AG_OR_TOOLS_SIDECAR_PATH', base_path('sidecar/or_tools/solver.py')),
    'population_fraction'     => (float) env('AG_OR_TOOLS_POPULATION_FRACTION', 0.20),
    'seed_time_limit_seconds' => (int)   env('AG_OR_TOOLS_SEED_TIME_LIMIT', 20),
    'max_seeds_per_execution' => (int)   env('AG_OR_TOOLS_MAX_SEEDS', 10),
    'allow_grasp_fallback'    => env('AG_OR_TOOLS_ALLOW_GRASP_FALLBACK', true),
    'log_channel'             => env('AG_OR_TOOLS_LOG_CHANNEL', 'daily'),
],
```

### Variáveis de ambiente correspondentes (.env.example)

```dotenv
AG_OR_TOOLS_ENABLED=false
AG_OR_TOOLS_SIDECAR_PATH=sidecar/or_tools/solver.py
AG_OR_TOOLS_POPULATION_FRACTION=0.20
AG_OR_TOOLS_SEED_TIME_LIMIT=20
AG_OR_TOOLS_MAX_SEEDS=10
AG_OR_TOOLS_ALLOW_GRASP_FALLBACK=true
```

### Compatibilidade com o modelo de ilhas

A fração de seeds OR-Tools é aplicada **por ilha**. Com `population_fraction = 0.20` e uma ilha de 50 indivíduos, o sidecar é chamado para produzir no máximo 10 seeds por ilha. As demais 40 continuam vindo de GRASP e seeds históricas.

> **Atenção**: a chave `ag.islands` já é usada explicitamente em mais de uma camada. Ao calcular o total de seeds solicitadas o sistema deve multiplicar por `config('ag.islands')` para não sobrecarregar o sidecar com muitas chamadas paralelas.

---

## Passo 11 — Fallback duro obrigatório

### Situações de fallback

Qualquer das condições abaixo deve acionar fallback silencioso para GRASP:

1. Sidecar Python não encontrado no path configurado
2. Timeout do processo externo
3. Resposta JSON inválida ou malformada
4. Nenhuma seed com `solver_status` aceitável no retorno
5. Todas as seeds importadas rejeitadas pelo quality gate
6. Exceção não tratada em qualquer ponto do pipeline OR-Tools

### Implementação no bridge

```php
// app/Modules/AG/Infrastructure/OrTools/OrToolsSeedBridge.php

final class OrToolsSeedBridge
{
    public function call(array $snapshot, int $count, int $timeLimitSeconds): array
    {
        try {
            $process = new Process([
                'python3', $this->scriptPath,
                '--input', json_encode($snapshot),
                '--count', (string) $count,
                '--time-limit', (string) $timeLimitSeconds,
            ]);

            $process->setTimeout($timeLimitSeconds + 10); // margem de segurança
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('or_tools.bridge.process_failed', [
                    'exit_code' => $process->getExitCode(),
                    'stderr'    => $process->getErrorOutput(),
                ]);
                return ['seeds' => [], 'status' => 'process_failed'];
            }

            $response = json_decode($process->getOutput(), true);
            return is_array($response) ? $response : ['seeds' => [], 'status' => 'invalid_json'];

        } catch (\Throwable $e) {
            Log::warning('or_tools.bridge.exception', ['error' => $e->getMessage()]);
            return ['seeds' => [], 'status' => 'exception'];
        }
    }
}
```

### Garantia no ScheduleProblem

```php
private function tryCreateIndividualFromExternalSeed(): ?Cromossomo
{
    if ($this->externalSeedProvider === null) {
        return null;
    }

    $seeds = $this->externalSeedProvider->provide(
        $this->data,
        count: 1,
        timeLimitSeconds: config('ag.or_tools.seed_time_limit_seconds', 20),
    );

    foreach ($seeds as $candidate) {
        $candidate = $this->repairWithTelemetry($candidate, /* ... */);
        $qualityGate = $this->evaluateInitialPopulationQualityGate($candidate, /* ... */);

        if ($qualityGate['passes']) {
            $this->lastInitialPopulationSource = 'or_tools';
            return $candidate;
        }
    }

    return null; // fallback para GRASP
}
```

---

## Passo 12 — Telemetria separada para seeds OR-Tools

### Métricas a registrar

```php
Log::info('or_tools.population.attempt', [
    'execution_id'                  => $this->executionId,
    'individual_index'              => $i,
    'seeds_requested'               => $count,
    'seeds_returned'                => count($rawSeeds),
    'seeds_imported'                => count($importedChromosomes),
    'seeds_quality_gate_passed'     => $passed,
    'seeds_quality_gate_rejected'   => $rejected,
    'fallback_to_grasp'             => $fallback,
    'average_seed_time_seconds'     => $avgTime,
    'or_tools_total_elapsed'        => $elapsed,
]);
```

### Métricas de comparação para validar a integração

Ao final do `initializePopulation`, calcular e registrar a diferença entre seeds de origem OR-Tools e seeds de origem GRASP:

```php
Log::info('or_tools.population.summary', [
    'total_or_tools'  => $orToolsCount,
    'total_grasp'     => $graspCount,
    'avg_score_or_tools' => $avgScoreOrTools,
    'avg_score_grasp'    => $avgScoreGrasp,
    'avg_hard_penalty_or_tools' => $avgHardOrTools,
    'avg_hard_penalty_grasp'    => $avgHardGrasp,
    'diversity_initial_population' => $diversity,
]);
```

Esses dados devem ser persistidos no `ScheduleGenerationMetric` se possível para análise posterior.

---

## Passo 13 — Proteger a diversidade inicial

O risco mais subestimado da integração é transformar a população inicial em um conjunto de soluções muito parecidas, o que penaliza a evolução e o ALNS.

### Estratégias de diversidade

1. **Múltiplos `random_seed` no CP-SAT**: o snapshot envia `diversity_random_seeds`, e o sidecar gera cada seed com um valor diferente. Seeds idênticas por estrutura têm baixíssima probabilidade.

2. **Fração máxima via `population_fraction`**: nunca colocar mais de 30% da população vindas de OR-Tools na configuração inicial. O resto deve vir de GRASP, seeds históricas e diversidade natural.

3. **Leve relaxação aleatória entre seeds**: o sidecar pode variar pequenos pesos de objetivos soft na RNG auxiliar para produzir soluções diferentes estruturalmente.

4. **Verificação de assinatura duplicada**: o `GeneticAlgorithmEngine` já rejeita cromossomos com assinatura idêntica. Seeds OR-Tools passam pelo mesmo filtro.

5. **Monitoramento de entropia**: se a entropia inicial for menor que `config('ag.termination_min_entropy')`, registrar warning para diagnóstico.

### Valor de `population_fraction` por cenário

| Cenário | Fração recomendada |
|---|---|
| Problema pequeno (< 100 aulas) | 0.10 (10%) |
| Problema médio (100–250 aulas) | 0.20 (20%) |
| Problema grande (> 250 aulas) | 0.25–0.30 (25–30%) |
| Validação inicial da integração | 0.10 (conservador) |

---

## Passo 14 — Não replicar todas as constraints no primeiro corte

### Prioridade de modelagem do sidecar

**Versão 1.0 do sidecar:**
- [x] Conflito de professor (dois slots simultâneos)
- [x] Conflito de turma (dois slots simultâneos)
- [x] Carga total por professor
- [x] Disponibilidade de professor e turma
- [x] `SYNC_SAME_TIMESLOT`
- [x] `MUTUAL_EXCLUSION`
- [x] `TIME_PLACEMENT`
- [x] Alocação completa (todas as aulas devem ter slot)

**Versão 2.0 (evolução futura):**
- [ ] Consecutividade de blocos (`requires_consecutive`)
- [ ] Distribuição de aulas por dia
- [ ] Janelas (gaps) entre aulas
- [ ] Preferência de horário

**Nunca no sidecar (responsabilidade exclusiva do PHP):**
- [ ] Score lexicográfico `[0,100]`
- [ ] Cálculo final de `hard_penalty` e `soft_penalty`
- [ ] Avaliação de qualidade do candidato
- [ ] Aceite ou rejeição de seed
- [ ] Fitness sharing e pressão seletiva

### Por que não modelar tudo de uma vez

Tentar replicar o fitness completo no CP-SAT cria dois pontos de verdade que divergem com o tempo. Sempre que uma constraint PHP é atualizada, o sidecar precisaria ser atualizado também — e isso é custo de manutenção contínuo. O repair do PHP é o mecanismo oficial de convergência. O CP-SAT só precisa entregar viabilidade básica; o solver PHP faz o resto.

---

## Passo 15 — Rollout em três fases

### Fase 1 — Sidecar desligado por padrão

- `AG_OR_TOOLS_ENABLED=false` no `.env.example`
- `population_fraction = 0.0` como default
- Toda a integração ativa, mas inerte
- Testes rodando normalmente
- Documentação atualizada

**Critério de saída**: sidecar em fase de homologação local, retornando seeds válidas para um problema real

### Fase 2 — Piloto controlado (10%)

- `AG_OR_TOOLS_ENABLED=true` apenas no ambiente de desenvolvimento
- `AG_OR_TOOLS_POPULATION_FRACTION=0.10`
- `AG_OR_TOOLS_SEED_TIME_LIMIT=15`
- Telemetria habilitada e acompanhada
- Comparativo GRASP x OR-Tools ativo nos logs

**Critérios de saída para Fase 3:**
- [ ] Sementes OR-Tools têm `avg_hard_penalty` ≤ sementes GRASP
- [ ] Taxa de `quality_gate_passed` com seeds OR-Tools ≥ taxa atual do GRASP
- [ ] Diversidade inicial (`diversity_initial_population`) não decaiu
- [ ] Sem exceções não tratadas no sidecar
- [ ] Job não falhou por causa da integração

### Fase 3 — Produção parcial (20–30%)

- `AG_OR_TOOLS_POPULATION_FRACTION=0.20` a `0.30`
- Habilitado em produção por feature flag
- Comparativo de qualidade de execução completa (não só população inicial)
- Acompanhamento de `best_fitness` final por execução

**Métricas para validar Fase 3:**
- [ ] Redução no tempo da fase de população inicial
- [ ] Aumento na taxa de execuções que encontram solução viável
- [ ] Sem degradação no `best_fitness` médio final

---

## Passo 16 — Definir testes antes da adoção total

### Testes necessários antes de qualquer merge

#### 1. Equivalência do snapshot

```php
it('exports a snapshot with all required keys', function () {
    $scheduleData = buildTestScheduleData();
    $snapshot = SnapshotExporter::fromScheduleData($scheduleData);

    expect($snapshot)->toHaveKeys(['meta', 'slots', 'lessons', 'teachers', 'classes', 'constraints']);
    expect($snapshot['meta'])->toHaveKey('time_limit_seconds');
    expect($snapshot['lessons'])->not->toBeEmpty();
});
```

#### 2. Import robusto do retorno

```php
it('imports a valid sidecar response into Cromossomo[]', function () {
    $response = buildFakeSidecarResponse(seedCount: 3);
    $importer = new SeedImporter();
    $chromosomes = $importer->import($response, buildTestScheduleData());

    expect($chromosomes)->toHaveCount(3);
    expect($chromosomes[0])->toBeInstanceOf(Cromossomo::class);
});

it('silently discards seeds with INFEASIBLE status', function () {
    $response = buildFakeSidecarResponse(seedCount: 2, status: 'INFEASIBLE');
    $chromosomes = (new SeedImporter())->import($response, buildTestScheduleData());

    expect($chromosomes)->toHaveCount(0);
});
```

#### 3. Qualidade gate preservado

```php
it('does not bypass quality gate for external seeds', function () {
    $badChromosome = buildChromossomoWithHardViolations();
    $provider = new FakeExternalSeedProvider([$badChromosome]);
    $problem = buildScheduleProblemWith(externalSeedProvider: $provider);

    $individual = $problem->createIndividual();

    // O resultado deve ter passado pelo quality gate
    $result = $problem->evaluate($individual);
    expect($result->hardPenalty())->toBeLessThanOrEqual(
        ScheduleProblem::INITIAL_QUALITY_GATE_BASE_HARD_PENALTY
    );
});
```

#### 4. Fallback para GRASP quando sidecar falha

```php
it('falls back to GRASP when sidecar throws exception', function () {
    $failingProvider = new FailingExternalSeedProvider();
    $problem = buildScheduleProblemWith(externalSeedProvider: $failingProvider);

    // Não deve lançar exceção, deve construir via GRASP
    $individual = $problem->createIndividual();
    expect($individual)->toBeInstanceOf(Cromossomo::class);

    // Confirma que veio do fallback
    expect($problem->lastInitialPopulationSource())->not->toBe('or_tools');
});
```

#### 5. Isolamento entre ilhas

```php
it('does not share state between islands via ExternalSeedProvider', function () {
    // Verifica que o provider não usa estado global
    $provider = new OrToolsSeedProvider(/* ... */);

    $problem1 = buildScheduleProblemWith(externalSeedProvider: $provider);
    $problem2 = buildScheduleProblemWith(externalSeedProvider: $provider);

    // Ambos devem funcionar independentemente
    $ind1 = $problem1->createIndividual();
    $ind2 = $problem2->createIndividual();

    expect($ind1->signature())->not->toBe($ind2->signature());
});
```

---

## Desenho final do fluxo

```
GerarHorarioJob
    │
    ▼
GenerateScheduleAction
    │
    ▼
RunGeneticAlgorithm
    │
    ├─► ScheduleDataBuilder::build()             [existente]
    ├─► LoadActiveScheduleConstraintsAction      [existente]
    │
    ├─► [SE config('ag.or_tools.enabled')]
    │       └─► new OrToolsSeedProvider(bridge, importer)
    │
    └─► new ScheduleProblem(..., externalSeedProvider: $provider)
            │
            ▼
        createIndividual() — portfólio de construtores
            │
            ├─ 1. tryCreateIndividualFromAcceptedSeed()   [existente]
            ├─ 2. tryCreateIndividualFromHistoricalSeed() [existente]
            ├─ 3. tryCreateIndividualFromExternalSeed()   ◄── NOVO
            │       │
            │       ├─ OrToolsSeedProvider::provide()
            │       │      └─ OrToolsSeedBridge::call()
            │       │             └─ [sidecar Python + CP-SAT]
            │       │                      └─ retorna seeds JSON
            │       │
            │       ├─ SeedImporter::import()             → Cromossomo[]
            │       ├─ GreedyRepairOperator::repair()
            │       ├─ evaluateInitialPopulationFailFast()
            │       ├─ evaluateInitialPopulationQualityGate()
            │       └─ aceita ou descarta → fallback GRASP
            │
            └─ 4. GRASP (tentativas 1..N)                [existente]
```

---

## Estrutura de arquivos proposta

```
app/Modules/AG/
    Domain/
        Contracts/
            ExternalSeedProviderInterface.php   ◄── NOVO
    Infrastructure/
        OrTools/
            OrToolsSeedBridge.php              ◄── NOVO
            OrToolsSeedProvider.php            ◄── NOVO
            SeedImporter.php                   ◄── NOVO
            SnapshotExporter.php               ◄── NOVO

sidecar/
    or_tools/
        solver.py                              ◄── NOVO
        model_builder.py                       ◄── NOVO
        seed_exporter.py                       ◄── NOVO
        requirements.txt                       ◄── NOVO
        README.md                              ◄── NOVO

config/
    ag.php                                     ← adicionar bloco 'or_tools'

tests/
    Unit/
        Modules/
            AG/
                OrTools/
                    SnapshotExporterTest.php   ◄── NOVO
                    SeedImporterTest.php        ◄── NOVO
                    OrToolsSeedBridgeTest.php   ◄── NOVO
                    FallbackToGraspTest.php     ◄── NOVO
```

---

## Referências

- [docs/AG_ARCHITECTURE.md](AG_ARCHITECTURE.md) — Arquitetura completa do solver híbrido
- [docs/AG_INDEX.md](AG_INDEX.md) — Índice e mapa de classes do solver
- [docs/AG_VISUAL_FLOWS.md](AG_VISUAL_FLOWS.md) — Fluxos visuais de execução
- [docs/AG_PRACTICAL_EXAMPLES.md](AG_PRACTICAL_EXAMPLES.md) — Exemplos de código
- [docs/diagnostics/SOLVER_LOG_ANALYSIS_2026-03-29.md](diagnostics/SOLVER_LOG_ANALYSIS_2026-03-29.md) — Diagnóstico que motivou esta proposta
- [docs/diagnostics/EXECUTIVE_SUMMARY.md](diagnostics/EXECUTIVE_SUMMARY.md) — Resumo executivo dos gargalos
- [docs/custom-constraints-spec.md](custom-constraints-spec.md) — Constraints customizadas a incluir no snapshot
- [config/ag.php](../config/ag.php) — Configuração atual do solver
- [.github/agents/projeto-horario-expert.agent.md](../.github/agents/projeto-horario-expert.agent.md) — Restrições arquiteturais do agente especialista
