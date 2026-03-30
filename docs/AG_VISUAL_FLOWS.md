# Diagramas Visuais - Algoritmo Genético

## 🎯 Fluxo Completo do Algoritmo Genético

```
START
  ↓
┌─────────────────────────────────────────────────┐
│ RunGeneticAlgorithm::execute(Horario)          │
│ ├─ Carrega ConfigDTO                           │
│ ├─ Cria ScheduleProblem                        │
│ ├─ Instancia FitnessEvaluator (9 regras)      │
│ └─ Cria 2 Islands com GeneticAlgorithmEngine   │
└──────────────┬──────────────────────────────────┘
               ↓
        ╔═══════════════════════╗
        ║ PARA CADA ILHA:       ║
        ║ (Paralelamente)       ║
        ╚═════════┬═════════════╝
                  ↓
    ┌─────────────────────────────────┐
    │ InitializePopulation            │
    │ (populationSize = 50)           │
    │                                 │
    │ Para cada indivíduo:            │
    │  └─ createIndividual() via GRASP│
    │    ├─ Fila de alocação         │
    │    ├─ RCL (α ∈ [0.15, 0.45])   │
    │    ├─ Quality gate check        │
    │    └─ Repair se inviável       │
    │                                 │
    │ Result: population[50] com genes│
    └────────┬────────────────────────┘
             ↓
    ╔════════════════════════════════╗
    ║ FOR generation = 1 TO MaxGen:  ║
    ╚════════┬═══════════════════════╝
             ↓
    ┌────────────────────────────────────┐
    │ Check Termination                  │
    │ - Max generations?                 │
    │ - Target fitness?                  │
    │ - No improvement > X gen?          │
    │ - Variância < threshold?           │
    │ - Diversidade colapso?             │
    └────────┬─────────────────────────-─┘
             ↓ ┌─ YES → BREAK
             ├─┘
             │ ┌─ NO
             └─┘
             ↓
    ┌──────────────────────────────┐
    │ 1. SELEÇÃO                   │
    │                              │
    │ parent1 = Tournament(k=3)    │
    │           + FitnessSharing   │
    │ parent2 = Tournament(k=3)    │
    │           + FitnessSharing   │
    └────────┬─────────────────────┘
             ↓
    ┌──────────────────────────────┐
    │ 2. CROSSOVER (80% chance)    │
    │                              │
    │ IF rand() < 0.80:            │
    │   offspring = ConflictGraph   │
    │              Crossover        │
    │              (parent1, 2)     │
    │ ELSE:                         │
    │   offspring = parent1.copy()  │
    └────────┬─────────────────────┘
             ↓
    ┌──────────────────────────────┐
    │ 3. MUTAÇÃO (2% chance)       │
    │                              │
    │ IF rand() < 0.02:            │
    │   type = selectType(          │
    │     diversity,               │
    │     landscape               │
    │   )                          │
    │   offspring = mutate(        │
    │     offspring,               │
    │     type                    │
    │   )                          │
    └────────┬─────────────────────┘
             ↓
    ┌──────────────────────────────┐
    │ 4. REPAIR (se inviável)      │
    │                              │
    │ IF isInfeasible(offspring):  │
    │   offspring = repair(        │
    │     offspring,               │
    │     repair_rules            │
    │   )                          │
    └────────┬─────────────────────┘
             ↓
    ┌──────────────────────────────┐
    │ 5. AVALIAÇÃO                 │
    │                              │
    │ context = buildContext()     │
    │ region = affectedGenes()     │
    │                              │
    │ fitness = evaluate(          │
    │   offspring,                 │
    │   previous,  ← Delta Fitness │
    │   region     ← Otimizado!    │
    │ )                            │
    │ offspring.fitness = score    │
    └────────┬─────────────────────┘
             ↓
    ┌──────────────────────────────┐
    │ 6. ALNS                      │
    │    (a cada 5 gerações)       │
    │                              │
    │ IF gen % 5 === 0:            │
    │   offspring = ALNS.improve(  │
    │     offspring,               │
    │     landscape_obs,           │
    │     intensity               │
    │   )                          │
    └────────┬─────────────────────┘
             ↓
    ┌──────────────────────────────┐
    │ 7. LANDSCAPE                 │
    │    (a cada 10 gerações)      │
    │                              │
    │ IF gen % 10 === 0:           │
    │   phenomenon =               │
    │     detectLandscape()        │
    │   adaptStrategy()            │
    └────────┬─────────────────────┘
             ↓
    ┌──────────────────────────────┐
    │ 8. SUBSTITUIÇÃO              │
    │                              │
    │ population =                 │
    │   replace(                   │
    │     population,              │
    │     offspring,               │
    │     elitism: 10%,            │
    │     niching                 │
    │   )                          │
    └────────┬─────────────────────┘
             ↓
    ┌──────────────────────────────┐
    │ 9. HIPERHEURÍSTICA           │
    │                              │
    │ IF hyperHeuristic != null:   │
    │   updateRewards(             │
    │     operator = mutation_type │
    │     improvement = Δfitness   │
    │   )                          │
    └────────┬─────────────────────┘
             ↓
    ┌──────────────────────────────┐
    │ 10. MIGRAÇÃO                 │
    │     (a cada 25 gerações)     │
    │                              │
    │ IF gen % 25 === 0:           │
    │   best_island1 →             │
    │      migrateToIsland2()      │
    │   best_island2 →             │
    │      migrateToIsland1()      │
    └────────┬─────────────────────┘
             ↓
    ┌──────────────────────────────┐
    │ 11. TELEMETRIA               │
    │                              │
    │ metrics.record({             │
    │   generation,                │
    │   best_fitness,              │
    │   avg_fitness,               │
    │   diversity,                 │
    │   entropy,                   │
    │   ...                        │
    │ })                           │
    └────────┬─────────────────────┘
             ↓
    ◇────────────────────────────────◇
    │ FIM DA GERAÇÃO                 │
    │ Loopa volta para geração+1 ↑  │
    ◇────────────────────────────────◇
             ↓
                (Quando terminar)
             ↓
    ┌──────────────────────────────┐
    │ Final Repair (3 tentativas)  │
    │                              │
    │ best_candidate = best        │
    │ FOR i = 1 TO 3:              │
    │   best_candidate = repair()  │
    │ RETURN best_candidate        │
    └────────┬─────────────────────┘
             ↓
           END (Melhor solução encontrada)
```

---

## 🔄 Fluxo do ALNS (Amplified Large Neighborhood Search)

```
ALNS::improve(solution)
  ↓
┌────────────────────────────────────────────────────┐
│ 1. SELEÇÃO DE OPERADORES (Roulette Wheel)        │
│                                                   │
│ destroy_op = selector.select(                    │
│   destroyOperators,                             │
│   scores_historia                               │
│ )                                                │
│                                                   │
│ repair_op = selector.select(                    │
│   repairOperators,                              │
│   scores_historia                               │
│ )                                                │
└────────┬───────────────────────────────────────-──┘
         ↓
┌────────────────────────────────────────────────────┐
│ 2. INTENSIDADE                                    │
│                                                   │
│ intensity = 0.35 (base)                          │
│                                                   │
│ SE landscape_pressure:                          │
│   intensity += 0.15                             │
│ SE basin_of_attraction_lock:                    │
│   intensity += 0.20                             │
│ SE phenomenon == 'deep_valley':                 │
│   intensity += 0.18                             │
│ SE phenomenon == 'local_minimum':               │
│   intensity += 0.12                             │
│ SE recent_success_rate < 0.35:                  │
│   intensity += 0.15                             │
│                                                   │
│ intensity = min(1.0, intensity)                 │
└────────┬───────────────────────────────────────-──┘
         ↓
┌────────────────────────────────────────────────────┐
│ 3. CONFIGURAR OPERADORES                          │
│                                                   │
│ destroy_op.configureDestroyIntensity(intensity)  │
│ repair_op.configureRepairIntensity(intensity)    │
│                                                   │
│ Exemplo:                                          │
│ RandomDestroy.destroyRatio =                     │
│   0.10 + (intensity * 0.35)                     │
│   → [0.10, 0.45] conforme intensity             │
└────────┬───────────────────────────────────────-──┘
         ↓
┌────────────────────────────────────────────────────┐
│ 4. DESTROY                                        │
│                                                   │
│ partial = destroy_op.destroy(solution)           │
│                                                   │
│ Resultado:                                        │
│ ├─ partial->assigned()    # Genes mantidos      │
│ └─ partial->unassigned()  # Genes removidos     │
└────────┬───────────────────────────────────────-──┘
         ↓
┌────────────────────────────────────────────────────┐
│ 5. REPAIR                                         │
│                                                   │
│ candidate = repair_op.repair(partial)            │
│                                                   │
│ RegretInsertion busca realocar com critério:     │
│ regret = 2º_best_cost - best_cost               │
│                                                   │
│ Resultado: novo Cromossomo com genes realocados │
└────────┬───────────────────────────────────────-──┘
         ↓
┌────────────────────────────────────────────────────┐
│ 6. AVALIAÇÃO                                      │
│                                                   │
│ improvement =                                    │
│   fitness(candidate) - fitness(solution)        │
│                                                   │
│ baseline_fitness = 76.5                         │
│ candidate_fitness = 78.3                        │
│ improvement = +1.8 ✅                           │
└────────┬───────────────────────────────────────-──┘
         ↓
┌────────────────────────────────────────────────────┐
│ 7. RECOMPENSA DOS OPERADORES                      │
│                                                   │
│ IF improvement > threshold:                      │
│   reward_level = σ++ (melhor)                  │
│ ELSE IF improvement > 0:                        │
│   reward_level = σ+ (bom)                      │
│ ELSE:                                            │
│   reward_level = σ- (falhou)                    │
│                                                   │
│ scores[destroy_op] += reward                    │
│ scores[repair_op] += reward                     │
│                                                   │
│ Próximas chamadas ao ALNS:                       │
│ Operadores com maior score têm maior            │
│ probabilidade de serem selecionados via Roulette│
└────────┬───────────────────────────────────────-──┘
         ↓
┌────────────────────────────────────────────────────┐
│ 8. ACEITAÇÃO                                      │
│                                                   │
│ IF improvement > 0:                             │
│   RETURN candidate  ✅ (aceitar)                 │
│ ELSE IF acceptance_criterion.accept(            │
│   candidate,                                    │
│   solution                                      │
│ ):                                               │
│   RETURN candidate  ✅ (aceitar com critério)   │
│ ELSE:                                            │
│   RETURN solution   ❌ (rejeitar)               │
└────────┬───────────────────────────────────────-──┘
         ↓
       END
```

---

## 🔬 Fluxo de Avaliação com Delta Fitness

```
evaluate(cromossomo, context)
  ↓
╔════════════════════════════════════╗
║ PASSO 1: VERIFICAR CACHE          ║
╚═════════┬═══════════════════════════╝
          ↓
    signature = cromossomo.signature()
    IF cache[signature]:
       RETURN cache[signature]  ✅ (Rápido!)
    ↓
╔════════════════════════════════════╗
║ PASSO 2: CALCULAR PENALTIES       ║
╚═════════┬═══════════════════════════╝
          ↓
    hardPenalty = 0.0
    softPenalty = 0.0
    ↓
    FOR EACH rule IN [9 rules]:
      penalty = rule.evaluate(context)
      weight = weights[rule]
      weighted = penalty * weight
      ↓
      IF rule.isHard():
        hardPenalty += weighted
      ELSE:
        softPenalty += weighted
    ↓
╔════════════════════════════════════╗
║ PASSO 3: SCORED LEXICOGRÁFICO      ║
╚═════════┬═══════════════════════════╝
          ↓
    IF hardPenalty > 0.0:
      # INVIÁVEL
      score = 49.999 / (1 + hardPenalty + softPenalty*0.10)
      score = min(49.999, score)
    ELSE:
      # VIÁVEL
      score = 50.0 + (50.0 / (1 + softPenalty))
    ↓
╔════════════════════════════════════╗
║ PASSO 4: CACHE & RETORNAR          ║
╚═════════┬═══════════════════════════╝
          ↓
    result = FitnessResult(
      score,
      hardPenalty,
      softPenalty
    )
    ↓
    cache[signature] = result
    ↓
    RETURN result


═══════════════════════════════════════════════════
DELTA FITNESS EVALUATION (Otimizado)
═══════════════════════════════════════════════════

evaluateDelta(cromossomo, region, previous)
  ↓
╔════════════════════════════════════╗
║ PASSO 1: IDENTIFICAR REGRAS        ║
║         AFETADAS                   ║
╚═════════┬═══════════════════════════╝
          ↓
    region = AffectedRegion {
      mutated_genes: [gene_5, gene_12, ...],
      affected_professors: [prof_1, prof_3, ...],
      affected_classes: [class_2A, ...],
      affected_days: [seg, ter, ...],
    }
    ↓
    affectedRules = dependencyGraph
                    .affectedRules(region)
    ↓
    # Exemplo: só TeacherConflict e WindowPenalty
    # foram afetadas pelas mutações
    ↓
╔════════════════════════════════════╗
║ PASSO 2: REAVALIAR APENAS          ║
║          AS REGRAS AFETADAS        ║
╚═════════┬═══════════════════════════╝
          ↓
    FOR EACH rule IN affectedRules:
      newPenalty = rule.evaluate(context)
      oldPenalty = previous[rule]
      delta = newPenalty - oldPenalty
      ↓
      IF rule.isHard():
        deltaHard += delta * weight
      ELSE:
        deltaSoft += delta * weight
    ↓
╔════════════════════════════════════╗
║ PASSO 3: CONSTRUIR NOVO RESULTADO  ║
║          BASEADO EM DELTA           ║
╚═════════┬═══════════════════════════╝
          ↓
    newHardPenalty = previous.hard + deltaHard
    newSoftPenalty = previous.soft + deltaSoft
    ↓
    newScore = computeLexicographicScore(
      newHardPenalty,
      newSoftPenalty
    )
    ↓
    RETURN FitnessResult(
      newScore,
      newHardPenalty,
      newSoftPenalty
    )

═══════════════════════════════════════════════════
OTIMIZAÇÃO: -70% AVALIAÇÕES!
═══════════════════════════════════════════════════

                    Tradicional          Delta Fitness
                    ───────────          ──────────────
Regras avaliadas:   9/9 (100%)          2-3/9 (22-33%)
Custo:              ⭐⭐⭐⭐⭐⭐        ⭐⭐ (3x mais rápido)
```

---

## 🏝️ Modelo de Ilhas

```
┌──────────────────────────────────────────────────────────────┐
│                    ILHA 1                                    │
│                    ─────────                                 │
│  Population: [50 cromossomos]                               │
│  Geração 1-25: Evolução local                               │
│  Melhor: fitness = 85.2                                     │
│                                                             │
│  Geração 25: MIGRAÇÃO                                       │
│  ├─ Recebe beste_island_2                                  │
│  └─ (integra à população)                                   │
│                                                             │
│  Geração 26-50: Evolução com novo indivíduo                │
│  ...                                                         │
└────────────────────┬───────────────────────────────────────┘
                     │
         ╔═══════════╩═══════════╗
         │   MIGRAÇÃO BIDIRECIONAL │
         │   A cada 25 gerações    │
         ╚═══════════╤═══════════╝
                     │
┌────────────────────▼───────────────────────────────────────┐
│                    ILHA 2                                   │
│                    ─────────                                │
│  Population: [50 cromossomos]                              │
│  Geração 1-25: Evolução local                              │
│  Melhor: fitness = 84.8                                    │
│                                                            │
│  Geração 25: MIGRAÇÃO                                      │
│  ├─ Recebe best_island_1                                  │
│  └─ (integra à população)                                  │
│                                                            │
│  Geração 26-50: Evolução com novo indivíduo               │
│  ...                                                        │
└────────────────────────────────────────────────────────────┘

Vantagens:
✅ Exploração paralela de 2 sub-espaços
✅ Diversidade mantida entre ilhas
✅ Comunicação de boas soluções via migração
✅ Possibilidade de paralelização real (2 threads)
✅ Reduz risco de convergência prematura

Trade-off:
❌ 2x indivíduos = 2x avaliações (mas paralelizável)
```

---

## 📊 Score Lexicográfico em Ação

```
┌─────────────────────────────────────────────────┐
│   POPULAÇÃO COM MÚLTIPLOS CROMOSSOMOS           │
└─────────────────────────────────────────────────┘

Cromossomo 1:
├─ Hard Penalties: 0.0 ✅
├─ Soft Penalties: 2.1
└─ Score = 50 + (50 / (1 + 2.1)) = 76.92 🏆 MELHOR
                                            VIÁVEL

Cromossomo 2:
├─ Hard Penalties: 0.0 ✅
├─ Soft Penalties: 4.2
└─ Score = 50 + (50 / (1 + 4.2)) = 59.26 ✅ VIÁVEL

Cromossomo 3:
├─ Hard Penalties: 1.5 ❌
├─ Soft Penalties: 7.0
└─ Score = 49.999 / (1 + 1.5 + 0.7) = 18.52 ❌ INVIÁVEL

Cromossomo 4:
├─ Hard Penalties: 6.0 ❌
├─ Soft Penalties: 8.5
└─ Score = 49.999 / (1 + 6.0 + 0.85) = 6.37 ❌ INVIÁVEL

═════════════════════════════════════════════════

ORDEM DE SELEÇÃO (Melhor → Pior):
1. Cromossomo 1 (76.92) ← Viável + qualidade boa
2. Cromossomo 2 (59.26) ← Viável + qualidade ruim
3. Cromossomo 3 (18.52) ← Inviável
4. Cromossomo 4 (6.37)  ← Inviável

═════════════════════════════════════════════════

PROPRIEDADE LEXICOGRÁFICA:

Qualquer cromossomo viável score>50
DOMINA SEMPRE
qualquer cromossomo inviável score<50

Mesmo que pareça que:
"Cromossomo 4 tem hard_penalty=6.0
 e Cromossomo 3 tem hard_penalty=1.5
 então Cromossomo 4 é '5x pior'"

NA VERDADE:
score_4 (6.37) << score_3 (18.52)
Então Cromossomo 3 é preferível.

MAS AINDA ASSIM:
score_3 (18.52) << score_2 (59.26)
Então Cromossomo 2 é MUITO preferível.

Esta é a hierarquia: Viabilidade > Qualidade
```

---

## 🎲 Processo GRASP Detalhado

```
ScheduleProblem::createIndividual()
  ↓
┌─────────────────────────────────────┐
│ PASSO 1: CONSTRUIR FILA DE AULAS   │
└────────┬────────────────────────────┘
         ↓
    queue = buildPlacementQueue()
    ↓
    # Ordena por dificuldade (restrições)
    ├─ Aula X (5 professores, 2 turmas)    👈 Mais difícil
    ├─ Aula Y (3 professores, 4 turmas)
    ├─ Aula Z (1 professor, 1 turma)      👈 Mais fácil
    └─ ...
    ↓
┌─────────────────────────────────────┐
│ PASSO 2: DEFINIR LIMITE ADAPTATIVO  │
└────────┬────────────────────────────┘
         ↓
    if (initial_population_quality < threshold):
      attemptLimit = 12  (máximo)
    elif (initial_population_quality > good_threshold):
      attemptLimit = 4   (mínimo)
    else:
      attemptLimit = 8   (médio)
    ↓
┌─────────────────────────────────────┐
│ PASSO 3: TENTAR REUTILIZAR SEED    │
└────────┬────────────────────────────┘
         ↓
    if (lastAcceptedInitialSeed != null):
      candidate = tryCreateFromSeed(queue)
      if (candidate != null):
        return candidate  ✅ SUCESSO!
    ↓
┌──────────────────────────────────────┐
│ PASSO 4: LOOP GRASP (max 12 vezes)  │
└────────┬───────────────────────────────┘
         ↓
    for (attempt = 1 to attemptLimit):
      assignedGenes = []
      unassignedQueue = queue.clone()
      iteration = 0
      ↓
      ┌──────────────────────────────┐
      │ PASSO 5: PARA CADA AULA      │
      └────────┬─────────────────────┘
               ↓
          while (!unassignedQueue.empty()):
            lesson = unassignedQueue.pop()
            iteration++
            ↓
            ┌──────────────────────────────┐
            │ PASSO 6: CRIAR RCL           │
            └────────┬─────────────────────┘
                     ↓
                alpha = rand(0.15, 0.45)
                ↓
                candidateSlots = findSlots(lesson)
                # Todos os slots viáveis para esta aula
                # Exemplo: [Seg 08:00, Ter 10:00, Qua 14:00, ...]
                ↓
                if (candidateSlots.empty()):
                  fail_fast++
                  break  # Aula não tem slot viável
                ↓
                # Ordenar por quitoalidade (custo)
                sortByQuality(candidateSlots)
                # [Seg 08:00 (cost=1.0), Ter 10:00 (cost=1.5),
                #  Qua 14:00 (cost=2.0), ...]
                ↓
                # RCL = α-melhores
                threshold = candidateSlots[0].cost +
                           alpha * (candidateSlots[last].cost -
                                   candidateSlots[0].cost)
                ↓
                rcl = [slots onde cost <= threshold]
                # Exemplo: IF alpha=0.30 e custo varia [1.0, 10.0]:
                # threshold = 1.0 + 0.30*(10.0-1.0) = 3.7
                # rcl = [Seg 08:00 (1.0), Ter 10:00 (1.5),
                #        Qua 14:00 (2.0), Qui 16:00 (3.2)]
                ↓
            ┌──────────────────────────────┐
            │ PASSO 7: SELEÇÃO ALEATÓRIA   │
            └────────┬─────────────────────┘
                     ↓
                if (rcl.size() < RCL_MIN_SIZE):
                  force rcl.size() >= 3
                ↓
                chosenSlot = rcl[rand() % rcl.size()]
                # Amostragem UNIFORME de RCL
                # (não sempre o melhor, permite aleatoriedade)
                ↓
            ┌──────────────────────────────┐
            │ PASSO 8: ALOCAR AULA         │
            └────────┬─────────────────────┘
                     ↓
                gene = Gene(lesson, chosenSlot.day,
                           chosenSlot.period)
                assignedGenes.push(gene)
                ↓
                updateOccupancy(gene)
                # Marcar slot como ocupado
                ↓
      ┌──────────────────────────────────┐
      │ PASSO 9: VERIFICAR SUCESSO      │
      └────────┬─────────────────────────┘
               ↓
          if (unassignedQueue.empty()):
            # Conseguiu alocar TODAS as aulas!
            cromossomo = Cromossomo(assignedGenes)
            ↓
            ┌──────────────────────────────┐
            │ PASSO 10: QUALITY GATE       │
            └────────┬─────────────────────┘
                     ↓
              if (passesQualityGate(cromossomo)):
                # hardPenalty não está tão alto
                lastAcceptedInitialSeed = cromossomo
                return cromossomo  ✅ SUCESSO!
              else:
                quality_gate_rejected++
    ↓
┌──────────────────────────────────────┐
│ PASSO 11: TODAS TENTATIVAS FALHARAM │
└────────┬────────────────────────────────┘
         ↓
    if (bestRejected != null):
      # Uma das tentativas chegou mais perto
      repaired = repairOperator.repair(bestRejected)
      return repaired  ✅ Conseguiu via repair
    else:
      throw Exception("Não conseguiu gerar")
      ↓
    END


Exemplo de Execução (Tentativa 1):
═════════════════════════════════════

Queue inicial: [Aula1, Aula2, Aula3, ... Aula150]

Iteração 1:
  lesson = Aula1
  alpha = 0.32
  candidateSlots = [Seg08 (cost=1), Ter10 (cost=1.5), Wed14 (cost=2),...]
  rcl = [Seg08, Ter10, Wed14]  (α-melhores)
  chosen = Ter10 (aleatório de RCL)
  gene = Gene(Aula1, Ter, 10:00)
  assignedGenes = [Gene1]

Iteração 2:
  lesson = Aula2
  alpha = 0.28
  candidateSlots = [Mon08 (cost=0.8), Wed10 (cost=1.2), ...]
  rcl = [Mon08, Wed10]
  chosen = Mon08
  gene = Gene(Aula2, Mon, 08:00)
  assignedGenes = [Gene1, Gene2]

... (iterações 3-149)

Iteração 150:
  lesson = Aula150
  alpha = 0.41
  candidateSlots = []  👈 NÃO HÁ SLOT VIÁVEL!
  fail-fast detectado
  break  ← Falha desta tentativa

unassignedQueue empty? NO
Tentativa 1 FALHOU

Tentativa 2: (repete processo)
... similar ...

Tentativa 8:
... sucesso até iteração 150
unassignedQueue vazio? SIM ✅
cromossomo.fitness = 74.2
passesQualityGate? SIM ✅
RETURN cromossomo
```

---

## 🎯 Resumo Configurações Padrão

```
POPULAÇÃO & GERAÇÕES:
├─ tamanhoPopulacao = 50          (por ilha)
├─ numeroGeracoes = 300
├─ Taxa crossover = 0.80          (80% aplica crossover)
├─ Taxa mutação base = 0.02       (2% aplica mutação)
├─ Taxa mutação min = 0.005       (adaptativa)
├─ Taxa mutação max = 0.35        (adaptativa)
└─ Taxa elitismo = 0.10           (10% elite mantida)

ESTRUTURA:
├─ Islands = 2                    (modelo de ilhas)
├─ popSize/island = 50
├─ lnsFrequency = 5 ger.          (ALNS a cada 5)
├─ migrationInterval = 25 ger.    (migração a cada 25)
└─ migrationCount = 2 individuos  (melhor de cada ilha)

PARADA:
├─ maxGenerations = 300
├─ targetFitness = 100.0
├─ maxGenWithoutImprovement = 50
├─ varianceThreshold = 0.0005     (for convergence check)
├─ varianceWindow = 8 gerações
├─ minDiversity = 0.08
└─ minEntropy = 0.10

HORÁRIO:
├─ aulasPorDia = 7
├─ diasSemana = 6
├─ duracaoAulaMinutos = 50
├─ duracaoIntervaloMinutos = 10
└─ permitirJanelas = true

PARALELO:
├─ parallelEvaluation = true
└─ maxWorkers = 8
```

