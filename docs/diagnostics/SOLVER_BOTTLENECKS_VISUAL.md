# 📊 Visualização Executiva - Análise do Solver

## Timeline de Execução Real

```
19:12:26 ┌─────────────────────────────────────────────────────────────────────┐
         │ JOB INICIADO                                                         │
         │ ID: 3 | GenerateScheduleAction::execute() | RunGeneticAlgorithm     │
         └─────────────────────────────────────────────────────────────────────┘
           │
           ├─ 19:12:27 ┌─ FASE 1: População Inicial –  queue_size: 385 aulas ─┐
           │           │  Ilha 1 com 20 indivíduos                            │
           │           │  Usando GRASP (alpha=0.3169)                         │
           │           │  ⚠️ PROBLEMA: hardest_lessons têm 35 slots (quase   │
           │           │     toda semana) - Classe 211 MUITO restrita        │
           │           └────────────────────────────────────────────────────┘
           │           (1m 27s depois)
           │
           ├─ 19:13:19 ┌─ FASE 1 (cont): Ilha 2 com 20 indivíduos ─────────┐
           │           │ ⚠️ Pop. inicial SEM viáveis: hard_penalty = 12.0   │
           │           │ (Todas as 40 soluções começam inviáveis!)         │
           │           └────────────────────────────────────────────────────┘
           │           (13s depois)
           │
           ├─ 19:13:32 ┌─ FASE 2: Início da Evolução com ALNS ──────────────┐
           │           │ Motor de orquestração síncrona das ilhas            │
           │           │ fitness base: 2.7173 (com hard violations)          │
           │           └────────────────────────────────────────────────────┘
           │
           ├─ 19:13:45 │ ALNS #1: RandomDestroy + LNSRepair                 │
           │    ↓      │   → 2.7173 → 2.7173 (SEM MELHORIA)                 │
           │ 5m 32s    │   ❌ rejected_no_score_improvement                  │
           ├─ 19:18:21 │ ALNS #2: RandomDestroy + RegretInsertion           │
           │    ↓      │   → 2.7173 → 0.5871 (PIOROU HARD PENALTY!)         │
           │ 4m 39s    │   ❌ rejected_hard_penalty_worsened                 │
           ├─ 19:19:00 │ ALNS #3: RandomDestroy + LNSRepair                 │
           │    ↓      │   → 2.7173 → 2.7173 (SEM MELHORIA)                 │
           │ 3m 8s     │   ❌ rejected_no_score_improvement                  │
           ├─ 19:22:12 │ ALNS #4-8: Tentativas variadas...                  │
           │    ↓      │   Todas as 5: 100% REJEITADAS                      │
           │ ~5m       │   ⚠️ ConflictDestroy, ClusterDestroy               │
           │           │   Todos: "no improvement" ou "hard penalty worsened"│
           │           │                                                     │
           ├─ 19:29:34 │ ALNS #9: LastUpdate                                │
           │    ↓      │                                                     │
           │ 4m 50s    │                                                     │
           │           └────────────────────────────────────────────────────┘
           │
           └─ 19:30:25 ┌─ Tentativa de Reparo Final (3x) ────────────────────┐
                       │ ❌ Repair Passes: []  (VAZIO!)                      │
                       │ ❌ Relocations: 0                                   │
                       │ ❌ Swaps: 0                                         │
                       │ ❌ Local Rebuilds: 0                                │
                       │                                                    │
                       │ ❌ FALHA FINAL                                      │
                       │ hard_penalty = 12.0 (INVIÁVEL)                     │
                       │ soft_penalty = 54.0                                 │
                       └────────────────────────────────────────────────────┘

TOTAL: 18 minutos 59 segundos
RESULTADO: ❌ Nenhuma solução viável encontrada
```

---

## 🔴 Análise de Gargalos - Os 5 Principais Problemas

### Problema 1: População Inicial Ruim (CRÍTICO)

```
Diagnóstico da População Inicial:
┌─────────────────────────────────────────────┐
│ Queue Size: 385 aulas para alocar           │
│ GRASP Alpha: 0.3169 (RCL tamanho mediano)   │
│                                             │
│ Classe 211 - Restrições SEVERAS:            │
│  • Hardest Lessons: 10 aulas críticas       │
│  • Candidate Slots: ~35 por aula (toda     │
│    semana com poucas restrições)            │
│  • Weekly Occurrences: 1-2 por aula         │
│  • Professor/Class Available Days: 5/5      │
│                                             │
│ ⚠️ RESULTADO DA GRASP:                       │
│  • Hard Penalty: 12.0  (inviável!)          │
│  • Soft Penalty: ? (não registrado)          │
│  • Score: 2.7173 (< 50.0 = INVIÁVEL)       │
│                                             │
│ ❌ CONCLUSÃO:                                │
│  Todas as 40 soluções iniciais com hard    │
│  violations → algoritmo começou já perdido │
└─────────────────────────────────────────────┘

Por que aconteceu?
├─ GRASP não força viabilidade
├─ Classe 211 tem restrições conflitantes
├─ Alpha=0.3169 deixa espaço pra soluções ruins
└─ Sem gate de qualidade: aceita tudo <= threshold
```

### Problema 2: ALNS Totalmente Inefetivo (CRÍTICO)

```
Taxa de Sucesso: 0/9 = 0%

Padrão de Rejeições:

TIPO A: "no_score_improvement"  ← 4 vezes
┌──────────────────────────┐
│ RandomDestroy            │
│ LNSRepair /              │
│ RegretInsertion          │
│                          │
│ Antes: 2.7173            │
│ Depois: 2.7173  ← IGUAL  │
│                          │
│ Causa: Destruct remove   │
│ genes válidos, Repair    │
│ reinsert no mesmo lugar  │
│ → Ciclo infinito!        │
└──────────────────────────┘

TIPO B: "hard_penalty_worsened"  ← 5 vezes
┌──────────────────────────┐
│ RandomDestroy            │
│ ConflictDestroy          │
│ ClusterDestroy           │
│ + RegretInsertion        │
│                          │
│ Antes: 2.7173            │
│ Depois: 0.5871 ← PIOROU! │
│         -2.13  ← Δ       │
│                          │
│ Causa: Destruir genes    │
│ leva a mais hard         │
│ violations no repair     │
└──────────────────────────┘

⚠️ Nenhum operador funcionou!
   Acceptance criteria muito restritiva
   Não consegue escapar de inviáveis
```

### Problema 3: Tempos Extremamente Longos Por Iteração

```
Timeline com Tempos Observados:

ALNS #1 (RandomDestroy + LNSRepair)
  19:13:45 → 19:18:21 = 5m 32s  ⚠️ MUITO LONGO

ALNS #2 (Random + Regret)
  19:18:21 → 19:19:00 = 39s     ✓ Ok (Regret é rápido)

ALNS #3 (Random + LNS)
  19:19:00 → 19:22:08 = 3m 8s   ⚠️ Longo

ALNS #4 (Random + Regret)
  19:22:08 → 19:22:12 = 4s      ✓ Ultra rápido (?)

ALNS #5 (Conflict + Regret)
  19:22:12 → 19:26:21 = 4m 9s   ⚠️ MUITO LONGO

ALNS #6-9: Mais ciclos...

Possíveis Causas:
├─ Evaluation fitness: ~1-2s por cromossomo
├─ Multiple evaluations por iteração (destroy + repair + delta)
├─ Graph dependency updates
├─ Database queries para penalties
└─ Sem cache de estados intermediários

💡 OTIMIZAÇÃO: Reuse fitness evaluations, cache intermediate states
```

### Problema 4: Repair Final Sem Ações Executadas

```
Final Repair Attempt (3x):
┌────────────────────────────────────────┐
│ Chromosome Hard Penalty: 12.0           │
│ Chromosome Soft Penalty: 54.0           │
│                                        │
│ Repair Passes: []        ← ❌ NENHUM!  │
│ Relocations: 0           ← ❌ NENHUM!  │
│ Swaps: 0                 ← ❌ NENHUM!  │
│ Local Rebuilds: 0        ← ❌ NENHUM!  │
│                                        │
│ Hard Penalty Before: null  ← Não mudou │
│ Hard Penalty After: null   ← Não mudou │
│                                        │
│ Resultado: 3 tentativas, 3 falhas.    │
└────────────────────────────────────────┘

Por que não conseguiu reparar?

Cenário A: Neighborhood Vazio
  Gene A tem violação com Gene B
  → Repair tenta mover Gene A
  → Todos os slots viáveis já têm conflitos
  → Nenhum move possível

Cenário B: Condição de Saída Prematura
  Repair começa mas encontra obstacle
  → Abandona sem múltiplas estratégias
  → Deveria tentar: relocate → swap → rebuild

Cenário C: Repair Strategy Limitada
  Quando passes=[], significa:
  → Nenhuma pass foi executada
  → Ou loop não entrou
  → Ou strategy não se aplica ao problema
```

### Problema 5: Sem Early Termination

```
Cronograma Idealizado vs Real:

CENÁRIO 1: Com Early Termination (PROPOSTO)
─────────────────────────────────────────────
19:12:26 Init
19:13:32 Gen 1:  hard_penalty=12.0, continue
         Gen 2:  hard_penalty=11.5, continue
         ...
19:15:20 Gen 15: hard_penalty=8.0, continue
19:17:14 Gen 20: hard_penalty=4.2, TRIGGER RESTART
           └─ Restarts com nova população
19:18:45 Gen 1:  hard_penalty=2.0, continue
19:20:10 Gen 10: hard_penalty=0.0, found viable!
         ✅ SUCESSO - 7m 44s (~60% MENOS)

CENÁRIO 2: Atual (SEM Early Termination)
─────────────────────────────────────────────
19:12:26 Init
19:13:32 Gen 1: hard_penalty=12.0, evaluate
19:13:45 Gen 2: ALNS apply...  (5m 32s)
19:19:17 Gen 3: ALNS apply...  (continue)
         ...
19:29:34 Gen 9: ALNS apply...  (4m 50s)
19:30:25 FINAL REPAIR (3x falhas)
         ❌ FALHA - 18m 59s (100% mais longo!)

Desperdício: ~11m em ciclos improdutivos
```

---

## 💡 Estratégia de Melhoria - Visão Geral

```
   ANTES                           DEPOIS
   ─────                           ──────

1. Pop Inicial
   ├─ hard_penalty: 12.0          ├─ hard_penalty: 0.0
   ├─ 40 inviáveis                ├─ 40 viáveis
   └─ 1m 40s                      └─ 3-4m (mais busca)
                                     ✅ Troca: mais tempo pela qualidade

2. ALNS Iterations
   ├─ Taxa sucesso: 0/9           ├─ Taxa sucesso: 7-8/9
   ├─ Todos rejeitados             ├─ Maioria aceita
   ├─ Tempo: ~16m                 ├─ Tempo: ~3-5m (early term ou restart)
   └─ No improvement               └─ Progresso constante
                                     ✅ Melhor aceitation + repair forte

3. Repair Final
   ├─ Passes: []                  ├─ Passes: 3-5
   ├─ Moves: 0                    ├─ Moves: 15-30
   └─ Falha                        └─ Sucesso (ou bem perto)
                                     ✅ Múltiplas estratégias de repair

4. Early Termination
   ├─ Não existe                  ├─ Se viável encontrado
   ├─ Sem restart                 ├─ Se preso em hard, restart
   └─ Sempre 300 gens             └─ ~50-100 gens média
                                     ✅ -60-70% tempo

RESULTADO FINAL:
┌────────────────────────────────┐
│ Tempo:    18m → 5-8m (↓60%)    │
│ Viáveis:  0%  → 60-80% (↑∞)    │
│ Fitness:  2.71→ [50,90]        │
│ Passes:   0   → 3-5 avg        │
│ Moves:    0   → 15-30 avg      │
└────────────────────────────────┘
```

---

## 🛠️ Melhoria Por Componente

### Componente: InitialPopulationBuilder.php

```
ANTES:
─────
GRASP_ALPHA_RANGE = [0.15, 0.45]     ← RCL grande (muita aleatoriedade)
GRASP_ATTEMPTS = 12                  ← Poucas tentativas
GRASP_QUALITY_GATE = sem gate        ← Aceita tudo

DEPOIS:
──────
GRASP_ALPHA_RANGE = [0.15, 0.25]     ← RCL menor (mais greedy)
GRASP_ATTEMPTS = 25                  ← Mais tentativas (2x+)
GRASP_QUALITY_GATE = 50.0            ← Rejeita inviáveis!

if ($chromosome->fitness() < 50.0) {
    continue;  // Reject, try again
}

Benefício:
✅ Força população viável
✅ Dá espaço pro GA evoluir
```

### Componente: ALNSEngine.php

```
ANTES:
─────
accept() {
    if (improvement > 0) {
        $reward = high
        return true
    }
    return false  // Reject tudo
}

DEPOIS:
──────
accept() {
    // ESCAPE MOVE: de inviável pra viável sempre!
    if ($candidate->isViable() && !$current->isViable()) {
        return true    ← ✅ Sempre escape!
    }

    // Hard violation escape: reduz hard penalty mesmo se soft piora
    if (!$candidate->isViable() && !$current->isViable()) {
        $hardDelta = $candidate->hardPenalty() - $current->hardPenalty()
        if ($hardDelta < -0.5) {
            return true  ← ✅ Escape hard violations
        }
    }

    // Simulated annealing pra mover em espaço contíguo
    return simulatedAnnealing()
}

Benefício:
✅ Sai de valleys
✅ Progresso mesmo em inviáveis
```

### Componente: RepairOperators/HardConstraintRepair.php

```
ANTES:
─────
repair() {
    // Apenas tenta relocate
    // Se falha, retorna sem mover
    passes = 0
}

DEPOIS:
──────
repair() {
    while (hasViolations && iterations < 100) {

        // Estratégia 1: Relocate pra outro slot
        if (tryRelocate()) continue;

        // Estratégia 2: Swap com conflitante
        if (trySwap()) continue;

        // Estratégia 3: Remove + reinsertar
        if (tryRemoveAndReinsert()) continue;

        // Estratégia 4: Local rebuild da vizinhança
        if (tryLocalRebuild()) continue;

        passes++
    }
}

Benefício:
✅ Múltiplas estratégias
✅ passes > 0
✅ relocations, swaps, rebuilds incrementam
```

### Componente: RunGeneticAlgorithm.php

```
ANTES:
─────
for (gen = 0; gen < maxGenerations; gen++) {
    evolve()
    // Nada mais
}
// Sempre 300 gerações completas

DEPOIS:
──────
for (gen = 0; gen < maxGenerations; gen++) {
    evolve()

    // ✅ Check 1: Early termination
    if (best->isViable()) {
        return best;  // ← Se achou solução, já era!
    }

    // ✅ Check 2: Restart se preso
    if (nGenerationsWithoutImprovement > 15) {
        restartWithNewPopulation()
        resetCounter()
    }

    // ✅ Check 3: Monitor degradação
    if (fitnessChange < -5.0) {
        switchALNSOperators()
    }
}

Benefício:
✅ Termina mais cedo
✅ Detecta travas
✅ Restart automático
```

---

## 📉 Gráficos Conceituais

### Curva de Fitness Esperada: Antes vs Depois

```
FITNESS
   100 │                                    ✅ DEPOIS
       │                                  /
    80 │                                /
       │                              /
    60 │ ← Threshold viável        /
       │                        / ← Early term aqui
    50 │   ────────────────────  (encontrou viável)
       │                  ↓
    40 │ ← Preso aqui  restart aqui
       │  (ANTES)      ✅ Recomeça
    30 │
       │
    20 │
       │
    10 │ (hard violations)
       │
     0 └─────────────────────────────────────
       0    5   10   15   20   25   30   35   40
                      GERAÇÃO

ANTES: Linea horizontal em ~2.7 (inviável), sem progresso
DEPOIS: Curva ascendente, cruza viável em ~20 ger, termina ou restart em ~30

Tempo: 18m (300 ger) → 5-10m (~50-100 ger)
```

### Ciclo ALNS: Antes vs Depois

```
═══════════════════════════════════════════════════════════════════

ANTES (INEFETIVO):
─────────────────

Gen N: fitness = 2.7 (inviável)
  │
  ├─ Destroy: Remove 15% genes
  │    └─ Chromosome: 50% filled, 12 hard violations
  │
  ├─ Repair: Insert greedy
  │    └─ Chromosome: 100% filled, 12 hard violations (mesma positio!)
  │
  ├─ Evaluate: fitness = 2.7 (IGUAL!)
  │    └─ "no_score_improvement"
  │
  └─ Accept? ❌ REJECTED

   [5m 32s wasted, zero progress]


DEPOIS (EFETIVO):
────────────────

Gen N: fitness = 11.5 (inviável, hard=11)
  │
  ├─ Destroy: Remove 40% genes (aggressive)
  │    └─ Chromosome: 60% filled, creates new slots
  │
  ├─ Repair: Multi-strategy
  │    Trial 1: Relocate (fails, 2 moved)
  │    Trial 2: Swap (success, 5 swapped)
  │    Trial 3: Local rebuild (success, 3 relocated)
  │    └─ Chromosome: 100% filled, 8 hard violations, 12+ soft reduced
  │
  ├─ Evaluate: fitness = 8.2 (MELHOROU!)
  │    Δ fitness = -3.3
  │    Δ hard = -3
  │    "viável_is_better_than_inviável"
  │
  └─ Accept? ✅ ACCEPTED

   [2m 30s invested, real progress: -3 hard violations]


═══════════════════════════════════════════════════════════════════
```

---

## 🎯 Resumo: Próximos Passos

1. **Hoje**: ✅ Análise completa (este documento)
2. **Amanhã**: Implementar Prioridade 1 (GRASP + acceptance criteria)
3. **Amanhã**: Implementar Prioridade 2 (HardConstraintRepair)
4. **Dia 3**: Implementar Prioridade 3 (early termination + restart)
5. **Dia 3**: Testes e validação

**Estimativa de Melhoria**: 60-70% redução de tempo, 60-80% taxa de viáveis.
