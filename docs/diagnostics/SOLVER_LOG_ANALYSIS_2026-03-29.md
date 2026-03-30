# 🔍 Análise Detalhada da Evolução do Solver - 29/03/2026

## 📊 Resumo Executivo

**Status**: ❌ **FALHA - Nenhuma solução viável encontrada**
**Tempo Total**: 18 min 59s (19:12:26 → 19:30:25)
**Problema**: hard_penalty = 12.0 (inviável do início ao fim)
**Taxa Sucesso ALNS**: 0/9 (nenhuma melhoria em 9 iterações)

---

## 🔴 Problemas Críticos Identificados

### 1️⃣ **População Inicial com Hard Constraints Violados** (CRÍTICO)

#### Evidência no Log:
```
[19:12:27] schedule.initial_population.diagnosis
  - queue_size: 385 aulas
  - candidate_slots: 35 por aula (praticamente a semana toda)
  - hardest_lessons: 10 aulas críticas identificadas

[19:13:19] População criada! (1m 27s para 20 indivíduos)

[19:13:45] PRIMEIRO ALNS aplicado
  - base_fitness: 2.7173 (contém hard_penalty!)
  - hard constraints já violados desde o início
```

#### Raiz do Problema:
- As 10 "hardest lessons" têm apenas 35 slots candidatos cada
- A classe 211 tem restrições muito severas (5 dias disponíveis, muitas aulas)
- **GRASP está criando cromossomos com hard violations desde o início**

#### Impacto:
- Score lexicográfico: [0, 50) = INVIÁVEL
- Significa que TODAS as soluções populacionais estão no espaço inviável
- ALNS não consegue melhorar de um índice inviável para viável

---

### 2️⃣ **Operadores ALNS Totalmente Inefetivos** (CRÍTICO)

#### Análise de 9 Iterações ALNS:

| Iteração | Trigger | Destroy Op | Repair Op | Avaliação | Resultado |
|----------|---------|------------|-----------|-----------|-----------|
| 1 | landscape_pressure | Random | LNSRepair | 5m 32s | ❌ rejected_no_improvement |
| 2 | landscape_pressure | Random | RegretInsertion | 3m | ❌ rejected_hard_penalty_worsened |
| 3 | budget_interval | Random | LNSRepair | 0m 39s | ❌ rejected_no_improvement |
| 4 | budget_interval | Random | RegretInsertion | 3m 8s | ❌ rejected_hard_penalty_worsened |
| 5 | landscape_pressure | Random | LNSRepair | 0m 4s | ❌ rejected_no_improvement |
| 6 | landscape_pressure | ConflictDestroy | RegretInsertion | 4m 9s | ❌ rejected_hard_penalty_worsened |
| 7 | landscape_pressure | ClusterDestroy | LNSRepair | 3m 55s | ❌ rejected_no_improvement |
| 8 | landscape_pressure | Random | LNSRepair | 0m 5s | ❌ rejected_no_improvement |
| 9 | (final attempt) | - | - | - | ❌ repair passes: [] |

#### Rejeições Observadas:
- **5x "rejected_no_score_improvement"**: fitness não muda (2.7173 → 2.7173)
- **4x "rejected_hard_penalty_worsened"**: hard_penalty piora ao destruir/reparar (2.7173 → 0.58)
- **Todas as 3 tentativas finais de repair**: passes vazio, 0 relocations, 0 swaps

#### Causas Raiz:
1. **Base fitness já contém hard violations** → não há margem para alocar mais aulas
2. **Destroy operadores removerem genes válidos** → repair não consegue realocar conflitos
3. **Repair operators presos em local optima** → não conseguem escapar de hard violations
4. **Penalidade de hard constraints muito alta** → aceitação via simulated annealing/tabu falha

---

### 3️⃣ **Tempo Desperdiçado Sem Ganho** (CRÍTICO)

#### Breakdown de Tempo:

```
Fase 1 - População Inicial
├─ Ilha 1: 1m 27s (20 indivíduos, todos com hard violations)
├─ Ilha 2: 13s adicionais (20 indivíduos, todos com hard violations)
└─ Subtotal: 1m 40s ⚠️ SEM BENEFÍCIO (pop ruim desde início)

Fase 2 - ALNS Iterativo (9 tentativas)
├─ Iter 1-2-6: ~5m + ~3m + ~4m + ~3m = 15m em 4 iterações
├─ Tentar diferentes operadores, ZERO progresso
├─ Cada iteração lê fitness, aplica destroy, repair, avalia δ
└─ Subtotal: ~16-17m ⚠️ 100% DESPERDIÇADO

Total: 18m desperdiçados em ciclos improdutivos
```

#### Custo Computacional:
- Cada avaliação de fitness: ~1-2s (hard constraints check)
- Destroy + Repair: ~2-4s por iteração
- **9 iterações × múltiplas avaliações** = ~30-40 fitness calls
- **Resultado: 0% melhoria**

---

### 4️⃣ **Repair Final Sem Ações** (CRÍTICO)

#### Log das 3 Tentativas Finais:
```json
{
  "hard_penalty": 12.0,
  "soft_penalty": 54.0,
  "repair": {
    "passes": [],           ← ❌ Nenhum passe executado
    "relocations": 0,       ← ❌ Nenhuma realocação
    "swaps": 0,            ← ❌ Nenhuma troca
    "local_rebuilds": 0,   ← ❌ Nenhum rebuild local
    "aborted": false,
    "abort_reason": null
  }
}
```

#### Possíveis Causas:
1. **Todos os genes têm hard violations** → repair não encontra válido
2. **Neighborhood vazio** → não há moves viáveis
3. **Repair strategy incorreta** → não consegue sair de hard violations
4. **Condição de saída prematura** → abandona antes de tentar

---

## 🎯 Pontos de Melhoria Prioritários

### **PRIORIDADE 1: Forçar População Inicial Viável**

#### Problema Atual:
- GRASP constrói cromossomos com hard violations desde o início
- Score começa em [0, 50) = INVIÁVEL
- Algoritmo nunca consegue escapar

#### Solução Proposta:
```php
// 1. Aumentar qualidade do GRASP (arquivo: app/Modules/AG/Application/InitialPopulationBuilder.php)
const GRASP_ALPHA_RANGE = [0.15, 0.25];  // ← Reduzir RCL (mais greedy no início)
const GRASP_ATTEMPTS = 25;               // ← Aumentar de 12 para 25
const GRASP_QUALITY_GATE = 50.0;         // ← Rejeitar se score < 50 (inviável)

// 2. Pré-processamento de viabilidade
if ($scheduleProblem->isHardlyViable()) {
    // Usar construção mais conservadora
    // Ou relax de constraints mínimo necessário
}

// 3. Multi-start com diferentes seeds
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $chromosome = buildGRASP(...);
    if ($chromosome->isViable()) {
        $population[] = $chromosome;  // Aceitar apenas viáveis
    }
}
```

#### Benefício Estimado:
- ❌ Antes: Population inicial com 12 hard violations
- ✅ Depois: Population inicial com 0 hard violations
- **Impacto**: ALNS consegue melhorar em espaço viável

---

### **PRIORIDADE 2: Stronger Acceptance Criteria para ALNS**

#### Problema Atual:
- RegretInsertion piora hard_penalty (2.717 → 0.587)
- Resultado rejeitado por "rejected_hard_penalty_worsened"
- Operador não consegue sair de valley

#### Solução Proposta:
```php
// Arquivo: app/Modules/AG/Infrastructure/ALNS/ALNSEngine.php

class ALNSEngine {
    private const HARD_PENALTY_THRESHOLD = 1.0;  // ← Rejeitar se piora muito

    public function acceptCandidate($candidate, $current): bool
    {
        // 1. Se candidato é viável e atual não, SEMPRE aceitar
        if ($candidate->isViable() && !$current->isViable()) {
            return true;  // ← SEMPRE escapar de inviável
        }

        // 2. Se ambos inviáveis, aceitar se hard_penalty melhora (mesmo pouco)
        if (!$candidate->isViable() && !$current->isViable()) {
            $hardDelta = $candidate->hardPenalty() - $current->hardPenalty();

            // Aceitar se reduz hard penalty (mesmo não melhora soft)
            if ($hardDelta < -0.5) {
                return true;  // ← Escape moves para sair de hard violations
            }
        }

        // 3. Aceitar via simulated annealing com temperature adaptivo
        if ($this->simulatedAnnealingAccept($candidate, $current)) {
            return true;
        }

        return false;
    }
}
```

#### Benefício Estimado:
- ❌ Antes: Nenhuma iteração melhorou (0/9)
- ✅ Depois: Incremento gradual de viabilidade
- **Impacto**: Escape from hard violations plateaus

---

### **PRIORIDADE 3: Operador de Repair Mais Forte**

#### Problema Atual:
```
Reparação final com:
  - passes: [] (vazio!)
  - relocations: 0
  - swaps: 0
  - local_rebuilds: 0
```

#### Solução Proposta:

```php
// Arquivo: app/Modules/AG/Infrastructure/ALNS/RepairOperators/HardConstraintRepair.php

class HardConstraintRepair implements RepairOperator {

    public function repair(Cromossomo $chromosome): Cromossomo
    {
        $maxIterations = 100;
        $iteration = 0;

        while (!$chromosome->isViable() && $iteration < $maxIterations) {
            $iteration++;

            // 1. Encontrar hard constraint violations
            $violations = $chromosome->getHardViolations();
            if (empty($violations)) {
                break;
            }

            // 2. Para cada violação, tentar resolver
            foreach ($violations as $violation) {
                // Estratégia 1: Relocate gene para outro slot
                if ($this->tryRelocate($chromosome, $violation)) {
                    continue;  // Sucesso, próxima violação
                }

                // Estratégia 2: Swap com gene conflitante
                if ($this->trySwap($chromosome, $violation)) {
                    continue;  // Sucesso
                }

                // Estratégia 3: Remove gene problemático e reinsertar após destuir outros
                if ($this->tryRemoveAndReinsert($chromosome, $violation)) {
                    continue;  // Sucesso
                }

                // Estratégia 4: Local rebuild de uma vizinhança
                if ($this->tryLocalRebuild($chromosome, $violation)) {
                    continue;  // Sucesso
                }
            }
        }

        return $chromosome;
    }

    private function tryRelocate(Cromossomo &$chromosome, $violation): bool
    {
        // Buscar todos os alelos do gene problemático
        $gene = $violation['gene'];
        $alleles = $chromosome->getAllelesForGene($gene);

        foreach ($alleles as $position => $value) {
            // Tentar mover para outro slot horário
            $alternatives = $chromosome->problem()
                ->getViableSlots($gene);

            foreach ($alternatives as $slot) {
                if (!$chromosome->isConflictingWithSlot($slot, $gene)) {
                    $chromosome->relocateGene($gene, $position, $slot);
                    return true;
                }
            }
        }

        return false;
    }

    private function tryLocalRebuild(Cromossomo &$chromosome, $violation): bool
    {
        // Rebuildar genes vizinhos ao conflitante
        $conflictingGene = $violation['gene'];
        $neighborhood = $chromosome->getConflictingNeighborhood($conflictingGene);

        // Remover todos genes da neighborhood
        foreach ($neighborhood as $gene) {
            $chromosome->removeGene($gene);
        }

        // Reinsertar com greedy (regret insertion)
        $repaired = 0;
        foreach ($neighborhood as $gene) {
            if ($this->greedyInsert($chromosome, $gene)) {
                $repaired++;
            }
        }

        return $repaired > (count($neighborhood) * 0.7);  // 70% sucesso mínimo
    }
}
```

#### Benefício Estimado:
- ❌ Antes: 0 relocations, 0 swaps, 0 rebuilds
- ✅ Depois: ~20-30 moves por iteração
- **Impacto**: Escape from local optima

---

### **PRIORIDADE 4: Aumentar Destroy Severity**

#### Problema Atual:
```
Random Destroy removes genes, mas repair insere exatamente onde estava
  → Volta ao mesmo estado = "rejected_no_improvement"
```

#### Solução Proposta:

```php
// Arquivo: app/Modules/AG/Infrastructure/ALNS/DestroyOperators/RandomDestroyOperator.php

class RandomDestroyOperator implements DestroyOperator {

    // Aumentar percentual de destruição
    private const DESTRUCTION_PERCENTAGE = 0.40;   // ← De 0.15 para 0.40 (40% dos genes)
    private const ADJACENT_DESTROY = 0.8;          // ← Remover genes adjacentes também

    public function destroy(Cromossomo $chromosome): Cromossomo
    {
        $numGenes = $chromosome->getNumGenes();
        $destructionCount = (int)($numGenes * self::DESTRUCTION_PERCENTAGE);

        // 1. Selecionar genes aleatoriamente
        $genesToDestroy = [];
        $selected = array_rand(range(0, $numGenes - 1), $destructionCount);

        // 2. Para cada gene, destruir e remover adjacentes problemáticos
        foreach ($selected as $geneIndex) {
            $chromosome->removeGeneAt($geneIndex);

            // Se for gene conflitante, remover também vizinhos
            if ($chromosome->hasConflictAt($geneIndex)) {
                for ($offset = -1; $offset <= 1; $offset++) {
                    $adjacentIndex = $geneIndex + $offset;
                    if ($adjacentIndex >= 0 && $adjacentIndex < $numGenes) {
                        if (rand(0, 100) < (self::ADJACENT_DESTROY * 100)) {
                            $chromosome->removeGeneAt($adjacentIndex);
                        }
                    }
                }
            }
        }

        return $chromosome;
    }
}
```

#### Benefício Estimado:
- ❌ Antes: Remover ~15% genes, volta ao mesmo estado
- ✅ Depois: Remover ~40% genes, force repair a reconstruir
- **Impacto**: Mais diversidade, menos ciclos repeat

---

### **PRIORIDADE 5: Early Termination e Restart**

#### Problema Atual:
- Algoritmo gasta 18 minutos em ciclos improdutivos
- Só detecta falha no final

#### Solução Proposta:

```php
// Arquivo: app/Modules/AG/Application/RunGeneticAlgorithm.php

class RunGeneticAlgorithm {

    private const MAX_GENERATIONS_WITHOUT_IMPROVEMENT = 15;
    private const MAX_HARD_VIOLATIONS_ALLOWED = 10;

    public function execute(Horario $horario, ...): Cromossomo
    {
        $generationsSinceImprovement = 0;
        $lastBestFitness = null;

        for ($generation = 0; $generation < $this->maxGenerations; $generation++) {
            $best = $this->evolutionEngine->evolveGeneration($generation);

            // 1. Detectar se população está presa em hard violations
            if ($best->hardPenalty() > self::MAX_HARD_VIOLATIONS_ALLOWED) {
                $generationsSinceImprovement++;

                if ($generationsSinceImprovement > self::MAX_GENERATIONS_WITHOUT_IMPROVEMENT) {
                    Log::warning("solver.hard_constraint_stuck", [
                        "generation" => $generation,
                        "hard_penalty" => $best->hardPenalty(),
                        "reason" => "Prede em hard violations, tentando restart"
                    ]);

                    // Restart: criar nova população com seed diferente
                    $this->restartWithNewPopulation();
                    $generationsSinceImprovement = 0;
                    continue;
                }
            } else {
                $generationsSinceImprovement = 0;  // Reset counter
            }

            // 2. Early termination se viável encontrado
            if ($best->isViable()) {
                Log::info("solver.viable_found_early", [
                    "generation" => $generation,
                    "fitness" => $best->fitness()
                ]);
                return $best;  // ← Retorna imediatamente
            }

            // 3. Detectar degradação extrema
            if ($lastBestFitness !== null) {
                $fitnessChange = $best->fitness() - $lastBestFitness;
                if ($fitnessChange < -5.0) {  // Piorou muito
                    Log::warning("solver.extreme_degradation", [
                        "generation" => $generation,
                        "fitness_change" => $fitnessChange
                    ]);
                    // Revert to best chromosome e try different operators
                    $this->switchALNSOperators();
                }
            }

            $lastBestFitness = $best->fitness();
        }

        return $best;  // Retorna melhor encontrado
    }

    private function restartWithNewPopulation(): void
    {
        Log::info("solver.restart.triggered");

        // Manter 10% melhores da população anterior
        $elite = array_slice($this->population, 0, 5);

        // Gerar 90% nova com seeds diferentes
        for ($i = 5; $i < 50; $i++) {
            $seed = random_int(0, PHP_INT_MAX);
            $individual = $this->initialPopulationBuilder
                ->buildIndividual($this->problem, $seed);
            $this->population[] = $individual;
        }
    }
}
```

#### Benefício Estimado:
- ❌ Antes: Gasto 18m em ciclos improdutivos
- ✅ Depois: Detecção em ~3-5m, restart OU termina com melhor
- **Impacto**: -60-70% tempo desperdiçado

---

## 📈 Melhoria Estimada (Antes vs Depois)

| Métrica | Antes | Depois | Melhoria |
|---------|-------|--------|----------|
| **Taxa Sucesso ALNS** | 0% (0/9) | 70-80% | +∞ |
| **Hard Violations Iniciais** | 12 | 0 | -100% |
| **Tempo Fase 1** | 1m 40s | 3-4m | -50% (mais tentativas) |
| **Tempo Fase 2** | 16m | 3-5m | -85% (restart/early term) |
| **Tempo Total** | 18m | 5-8m | -60% |
| **Taxa Viáveis Encontrados** | 0% | 60-80% | +∞ |
| **Fitness Melhor** | 2.717 (inviável) | [50, 90] (viável) | +30 pontos |

---

## 🛠️ Implementação (Roadmap)

### Fase 1: Quick Wins (1-2 horas)
1. ✅ Aumentar GRASP_ATTEMPTS de 12 → 25
2. ✅ Adicionar GRASP_QUALITY_GATE (rejeitar se < 50)
3. ✅ Melhorar acceptance criteria para escape from hard violations
4. ✅ Aumentar destruction percentage de 15% → 40%

### Fase 2: Repair Forte (3-4 horas)
1. ✅ Implementar HardConstraintRepair com múltiplas estratégias
2. ✅ Adicionar local_rebuild logic
3. ✅ Test com log análogo

### Fase 3: Controle e Monitoramento (2-3 horas)
1. ✅ Early termination se viável
2. ✅ Restart detector se hard violations persist
3. ✅ Enhanced logging com métricas intermediárias

### Fase 4: Validação (1-2 horas)
1. ✅ Teste com dados reais
2. ✅ Compare tempos: antes vs depois
3. ✅ Ajuste parâmetros

---

## 📋 Checklist de Implementação

- [ ] Aumentar GRASP_ATTEMPTS (5 min)
- [ ] Adicionar quality gate no GRASP (5 min)
- [ ] Revisar ALNSEngine acceptance criteria (15 min)
- [ ] Aumentar destroy percentage (5 min)
- [ ] Implementar HardConstraintRepair (60 min)
- [ ] Adicionar local_rebuild strategy (45 min)
- [ ] Implementar early termination (30 min)
- [ ] Adicionar restart logic (30 min)
- [ ] Enhanced logging de diagnóstico (20 min)
- [ ] Teste com problema anterior (15 min)
- [ ] Validate improvements (30 min)

---

## 🎯 Conclusão

O solver está preso em um ciclo improdutivo porque:
1. ❌ População inicial com hard violations
2. ❌ Operadores ALNS não conseguem escapar (acceptance criteria muito restritiva)
3. ❌ Repair final sem ações (neighborhood vazio)
4. ❌ Sem early termination → desperdício de tempo

**Com as 5 mudanças propostas**, esperamos:
- ✅ Reduzir tempo de 18m para 5-8m (-60%)
- ✅ Aumentar taxa viáveis de 0% para 60-80%
- ✅ Melhorar fitness final significativamente
