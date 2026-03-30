# 📚 Índice - Exploração do Algoritmo Genético

> **Documentação Completa da Arquitetura do Algoritmo Genético Híbrido com ALNS para Agendamento de Aulas**

---

## 📖 Documentos Criados

### 1. **AG_ARCHITECTURE.md** - Arquitetura Completa ⭐

Análise in-depth da estrutura do projeto:

- **Sumário Executivo**: Visão geral de técnicas utilizadas
- **Estrutura de Diretórios**: Mapa completo de `app/Modules/AG/`
- **Classes Principais**:
  - `RunGeneticAlgorithm` (orquestrador)
  - `Cromossomo` (solução/indivíduo)
  - `ScheduleProblem` (problema específico)
  - `FitnessEvaluator` (avaliação)
  - `GeneticAlgorithmEngine` (engine)
  - `AdaptiveLargeNeighborhoodSearch` (ALNS)

- **População Inicial (GRASP)**: Detalhe do algoritmo com constantes
- **ALNS e seus Operadores**: Destroy/Repair com exemplos
- **Cálculo de Penalidade**: Hard/Soft penalties com score lexicográfico
- **Configurações**: Todos os parâmetros do AG
- **Modelo de Ilhas**: Paralelização com migração
- **Métricas**: O que é coletado durante execução
- **Fluxo Geral**: Resumo da execução completa

**→ Comece aqui se quer entender a ARQUITETURA**

---

### 2. **AG_PRACTICAL_EXAMPLES.md** - Exemplos de Código ⭐

Exemplos práticos de como usar as classes:

- **Exemplo 1**: Criar um Cromossomo com GRASP
- **Exemplo 2**: Calcular Fitness (Hard + Soft)
- **Exemplo 3**: Executar ALNS em Uma Solução
  - Passo a passo com valores reais
  - Seleção de operadores
  - Cálculo de intensidade
  - Destroy e Repair em detalhes
  - Recompensa de operadores

- **Exemplo 4**: Score Lexicográfico em Ação
- **Exemplo 5**: Loop Completo de Uma Geração
  - Seleção
  - Crossover
  - Mutação
  - Repair
  - Avaliação
  - ALNS
  - Landscape
  - Substituição
  - Hiperheurística
  - Migração

- **Exemplo 6**: Critério de Parada
- **Resumo de Fluxo Completo**: Diagrama topo a fundo
- **Referências Rápidas**: Tabela de "onde está o quê"

**→ Comece aqui se quer EXEMPLOS DE CÓDIGO e COMO USAR**

---

### 3. **AG_VISUAL_FLOWS.md** - Diagramas e Visualizações ⭐

Fluxos visuais de execução:

- **Fluxo Completo do AG**: Diagrama ASCII de toda execução
  - Do `RunGeneticAlgorithm` até resultado final
  - Mostra cada passo desde population init até final repair

- **Fluxo do ALNS**: Detalhado passo a passo
  - Seleção de operadores
  - Cálculo de intensidade (com fatores)
  - Destroy com exemplo numérico
  - Repair com RegretInsertion
  - Recompensa dos operadores
  - Aceitação

- **Fluxo de Avaliação**: Com Delta Fitness Optimization
  - Processo tradicional (9 regras)
  - Delta Fitness otimizado (apenas afetadas)
  - Ganho de performance (-70%)

- **Modelo de Ilhas**: Visualização
  - 2 ilhas paralelamente
  - Migração bidirecional a cada 25 gen
  - Vantagens e trade-offs

- **Score Lexicográfico em Ação**: Exemplo com 4 cromossomos
  - Como viáveis dominam inviáveis
  - Ordem de seleção
  - Propriedade lexicográfica

- **Processo GRASP Detalhado**: Passo a passo de uma tentativa
  - Fila de aulas
  - Limite adaptativo
  - Reutilização de seed
  - RCL construction
  - Seleção aleatória
  - Quality gate
  - Exemplo de execução real

- **Resumo Configurações Padrão**: Referência rápida

**→ Comece aqui se quer VISUALIZAÇÕES e DIAGRAMAS**

---

## 🎯 Respostas aos 5 Pontos Solicitados

### 1. **Estrutura das Classes Principais** ✅

**Documentos**: `AG_ARCHITECTURE.md` (Classes Principais), `AG_PRACTICAL_EXAMPLES.md` (Exemplos)

**Classes**:
- `RunGeneticAlgorithm`: Orquestrador, cria 2 ilhas
- `Cromossomo`: Solução com 150 genes indexados para performance
- `ScheduleProblem`: Gera população inicial via GRASP
- `FitnessEvaluator`: Calcula score com 9 regras de fitness
- `GeneticAlgorithmEngine`: Loop de evolução (seleção, crossover, mutação, etc.)
- `AdaptiveLargeNeighborhoodSearch`: Intensificação local adaptativa

**Fluxo**:
```
RunGeneticAlgorithm
  ↓
IslandModelEngine (2 ilhas)
  ├─ Island 1: GeneticAlgorithmEngine (50 indivíduos)
  └─ Island 2: GeneticAlgorithmEngine (50 indivíduos)
```

---

### 2. **População Inicial (GRASP)** ✅

**Documento**: `AG_ARCHITECTURE.md` (seção "População Inicial com GRASP"), `AG_VISUAL_FLOWS.md` (Processo GRASP)

**Processo**:
1. **Fila de Alocação**: Aulas ordenadas por dificuldade (restrições)
2. **RCL**: Restricted Candidate List com α ∈ [0.15, 0.45]
   - α controlador de aleatoriedade
   - Sempre seleciona aleatoriamente de RCL (não simplesmente greedy)
3. **Quality Gate**: Rejeita if hard_penalty > limiar
4. **Repair**: Se inviável, aplica GreedyRepairOperator
5. **Limite Adaptativo**: 4-12 tentativas conforme população anterior

**Constantes**:
- `MAX_BUILD_ATTEMPTS = 12`
- `RCL_ALPHA_MIN = 0.15`, `RCL_ALPHA_MAX = 0.45`
- `RCL_MIN_SIZE = 3`

---

### 3. **Algoritmo ALNS** ✅

**Documento**: `AG_ARCHITECTURE.md` (seção "ALNS"), `AG_PRACTICAL_EXAMPLES.md` (Exemplo 3), `AG_VISUAL_FLOWS.md` (Fluxo ALNS)

**Operadores Destroy**:
- `RandomDestroyOperator`: Remove ~20-55% genes aleatoriamente
- `ConflictDestroyOperator`: Remove genes mais conflitantes
- `ClusterDestroyOperator`: Remove clusters/blocos de aulas

**Operadores Repair**:
- `GreedyRebuildOperator`: Reinserção gulosa
- `RegretInsertionOperator`: Critério de "regret" (arrependimento)
  - regret = 2º_best_cost - best_cost
  - Prioriza genes críticos

**Seleção Adaptativa**:
- Roulette Wheel baseada em histórico de sucesso
- Cada operador tem score que evolui
- Melhor operadores são selecionados com maior probabilidade

**Intensidade**:
- Base 0.35, aumenta com landscape_pressure, basin_lock, etc.
- Máximo 1.0
- Configura operadores para mais ou menos agressividade

**Frequência**: A cada 5-8 gerações

---

### 4. **Cálculo de Penalidade** ✅

**Documento**: `AG_ARCHITECTURE.md` (seção "Cálculo de Penalidade"), `AG_PRACTICAL_EXAMPLES.md` (Exemplo 2), `AG_VISUAL_FLOWS.md` (Fluxo Avaliação)

**Hard Penalties** (Inviabilidade):
- `TeacherConflictRule`: Professor em 2 slots SIMULTÂNEOS
- `ClassConflictRule`: Turma em 2 slots SIMULTÂNEOS
- `WorkloadExceededRule`: Carga de trabalho > limite
- `MandatoryBlockViolationRule`: Blocos obrigatórios inviolados

**Soft Penalties** (Qualidade):
- `WindowPenaltyRule`: Janelas (gaps) entre aulas (peso: 1.0)
- `DistributionRule`: Distribuição irregular (peso: 1.5)
- `MaxLessonsPerDayRule`: >X aulas/dia (peso: 2.0)
- `ConsecutiveLessonRule`: Falta de sequência (peso: 1.0)
- `PreferredTimeRule`: Fora de horário preferido (peso: 1.0)

**Score Lexicográfico**:
```
IF hardPenalty > 0:
  score = 49.999 / (1 + hardPenalty + softPenalty*0.10)
  → Faixa [0, 50) = INVIÁVEL
ELSE:
  score = 50.0 + (50.0 / (1 + softPenalty))
  → Faixa [50, 100] = VIÁVEL
```

**Propriedade**: Qualquer viável domina qualquer inviável!

**Otimização (Delta Fitness)**:
- Usa `RuleDependencyGraph` para identificar regras afetadas
- Reavalia apenas essas regras (não todas 9)
- **Ganho: -70% avaliações**, 3x mais rápido

---

### 5. **Configurações do AG** ✅

**Documento**: `AG_ARCHITECTURE.md` (seção "Configurações"), `AG_VISUAL_FLOWS.md` (Resumo Config)

**Parâmetros Principais** (em `GeneticAlgorithmConfigDTO`):
- `tamanhoPopulacao = 50` (por ilha)
- `numeroGeracoes = 300`
- `taxaMutacao = 0.02` (2%, adaptativa)
- `taxaCrossover = 0.80` (80%)
- `taxaElitismo = 0.10` (10%)
- `targetFitness = 100.0`
- `maxGenerationsWithoutImprovement = 50`

**Arquivo de Configuração** (`config/ag.php`):
- `parallel_evaluation = true`
- `max_workers = 8`
- `termination_variance_threshold = 0.0005`
- `termination_variance_window = 8`
- `termination_min_diversity = 0.08`
- `termination_min_entropy = 0.10`

**Freqências**:
- ALNS: a cada 5-8 gerações (baseado em `numeroGeracoes`)
- Landscape Analysis: a cada 10 gerações
- Migração: a cada 25 gerações
- Telemetria: a cada 30 segundos (heartbeat)

---

## 🔍 Como Navegar os Documentos

### Se você quer...

| Objetivo | Comece em |
|----------|-----------|
| **Entender arquitetura geral** | AG_ARCHITECTURE.md - Sumário |
| **Ver estrutura de diretórios** | AG_ARCHITECTURE.md - Estrutura |
| **Saber o que cada classe faz** | AG_ARCHITECTURE.md - Classes Principais |
| **Entender GRASP** | AG_ARCHITECTURE.md - População Inicial |
| **Ver código GRASP** | AG_PRACTICAL_EXAMPLES.md - Exemplo 1 |
| **Entender como calcula fitness** | AG_PRACTICAL_EXAMPLES.md - Exemplo 2 |
| **Ver ALNS em ação** | AG_PRACTICAL_EXAMPLES.md - Exemplo 3 |
| **Entender score lexicográfico** | AG_PRACTICAL_EXAMPLES.md - Exemplo 4 |
| **Ver loop de uma geração** | AG_PRACTICAL_EXAMPLES.md - Exemplo 5 |
| **Visualizar fluxo completo** | AG_VISUAL_FLOWS.md - Fluxo Completo |
| **Entender ALNS visualmente** | AG_VISUAL_FLOWS.md - Fluxo ALNS |
| **Ver delta fitness explicado** | AG_VISUAL_FLOWS.md - Fluxo Avaliação |
| **Modelo de ilhas** | AG_VISUAL_FLOWS.md - Modelo Ilhas |
| **Score lexicográfico visual** | AG_VISUAL_FLOWS.md - Score Lexicográfico |
| **GRASP passo a passo** | AG_VISUAL_FLOWS.md - Processo GRASP |
| **Referência rápida de código** | AG_PRACTICAL_EXAMPLES.md - Referências |
| **Resumo de configs** | AG_VISUAL_FLOWS.md - Resumo Config |

---

## 📊 Fluxo Geral em Uma Visão

```
START: RunGeneticAlgorithm.execute(horario)
  │
  ├─► Carrega config, build schedule data, cria fitness evaluator
  │
  ├─► IslandModelEngine com 2 ilhas (50 indiv cada)
  │   │
  │   ├─► Ilha 1: GeneticAlgorithmEngine
  │   │   ├─► initializePopulation (50x GRASP)
  │   │   ├─► FOR geração 1 TO 300:
  │   │   │   ├─ Seleção (Tournament + FitnessSharing)
  │   │   │   ├─ Crossover (ConflictGraphCrossover, 80%)
  │   │   │   ├─ Mutação (AdaptiveDiversity, 2%)
  │   │   │   ├─ Repair (GreedyRepair se inviável)
  │   │   │   ├─ Avaliação (Delta Fitness otimizado)
  │   │   │   ├─ ALNS (a cada 5 gen) ← Destroy/Repair adaptativos
  │   │   │   ├─ Landscape (a cada 10 gen)
  │   │   │   ├─ Substituição (Elitism + Niching)
  │   │   │   ├─ Hiperheurística (atualiza scores)
  │   │   │   └─ Migração (a cada 25 gen)
  │   │   └─ best_island_1
  │   │
  │   ├─► Ilha 2: GeneticAlgorithmEngine
  │   │   └─ (similar ao Ilha 1)
  │   │
  │   └─ best_global = max(best_island_1, best_island_2)
  │
  ├─► FinalRepairAttempts (3 vezes)
  │
  ├─► RETURN { best: Cromossomo, fitness: 87.5, metrics: [...] }
  │
END
```

---

## 🎓 Resumo Técnico

| Técnica | Implementação |
|---------|---------------|
| **Algoritmo Base** | Algoritmo Genético (GA) geracional |
| **População Inicial** | GRASP com RCL α-adaptativo |
| **Seleção** | Tournament (k=3) com Fitness Sharing |
| **Crossover** | ConflictGraphCrossover (preserva estrutura) |
| **Mutação** | AdaptiveDiversityMutation (3 tipos adaptativos) |
| **Repair** | GreedyRepairOperator |
| **Intensificação** | ALNS com Destroy/Repair e seleção adaptativa |
| **Destroy Ops** | Random, Conflict, Cluster |
| **Repair Ops** | GreedyRebuild, RegretInsertion (regret criterion) |
| **Op Selection** | Roulette Wheel com score histórico |
| **Fitness** | Score Lexicográfico (hard/soft penalties) |
| **Avaliação** | Delta Fitness com RuleDependencyGraph (-70%) |
| **Hiperheurística** | Learning com ε-greedy selector |
| **Paralelização** | Modelo de Ilhas + BestIndividualsMigration |
| **Landscape** | Detection + ResponseStrategy |
| **Parada** | Variance + Diversity/Entropy + targetFitness |

---

## 🏆 Fluxo GRASP em 30 Segundos

```
FOR cada tentativa (max 12):
  FOR cada aula na fila:
    ├─ Encontrar slots viáveis
    ├─ Criar RCL (α-melhores slots)
    ├─ Selecionar aleatoriamente de RCL ← Aleatoriedade!
    └─ Alocar aula

  IF todas aulas alocadas:
    ├─ IF qualidade OK → return solução ✅
    └─ ELSE → tentar próxima tentativa

  ELSE:
    └─ tentar próxima tentativa

SE esgotou tentativas:
  └─ repair e return
```

**Por que é "Randomized"?** Porque seleciona aleatoriamente de RCL, não sempre o melhor.

---

## 🎯 Próximos Passos

1. **Ler AG_ARCHITECTURE.md** para entender a arquitetura
2. **Ler AG_PRACTICAL_EXAMPLES.md** para ver código em ação
3. **Ler AG_VISUAL_FLOWS.md** para visualizar fluxos
4. **Explorar os arquivos reais** em `app/Modules/AG/`
5. **Debugar uma execução** para ver métricas em tempo real

---

## 📂 Arquivos Principais Referenciados

```
app/Modules/AG/
├── Application/
│   ├── RunGeneticAlgorithm.php ⭐
│   ├── GeneticAlgorithmEngine.php ⭐
│   └── PopulationFitnessEvaluator.php
├── Domain/
│   ├── Representation/Entities/Cromossomo.php ⭐
│   ├── Fitness/FitnessEvaluator.php ⭐
│   ├── Evolution/IslandModel/IslandModelEngine.php
│   ├── Operators/ (Seleção, Crossover, Mutação, etc.)
│   ├── Intensification/LNS/ALNS/AdaptiveLargeNeighborhoodSearch.php ⭐
│   ├── Intensification/LNS/Destroy/ (Operadores)
│   └── Intensification/LNS/Repair/ (Operadores)
├── Support/DTO/GeneticAlgorithmConfigDTO.php ⭐
└── Infrastructure/ (Paralelo, Métricas, Logging)

app/Modules/Horarios/
└── Domain/Problem/ScheduleProblem.php ⭐ (GRASP)

config/ag.php (Configurações)
```

---

**Documentação Completa - Última atualização: 29/03/2026**
