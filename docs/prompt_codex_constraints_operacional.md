# Prompt Operacional para implementação de um Sistema de Constraints Customizadas integrado ao sistema de Horários escolares

## Visão Geral
Este documento define a execução passo a passo para implementação de constraints customizadas no sistema de geração de horários escolares.

Cada etapa deve ser executada sequencialmente e validada com critérios objetivos de aceite.

Escopo resumido
Item: Tipos iniciais
Conteúdo: SYNC_SAME_TIMESLOT, MUTUAL_EXCLUSION e TIME_PLACEMENT

Item: Camadas
Conteúdo: Domain, Application, Infrastructure e UI/Livewire

Item: Integração
Conteúdo: Carregamento das constraints ativas no fluxo de geração e impacto no fitness

Item: Qualidade
Conteúdo: Testes unitários, feature tests, documentação e revisão arquitetural final

---

# FASE 0 — Descoberta

## Objetivo
•	Inspecionar a estrutura atual do projeto e localizar: agregado correto para vínculo das constraints, ponto de montagem do ScheduleProblem, ponto de integração com o solver, regras atuais de fitness, entidades de turmas, disciplinas, dias e tempos, e padrões existentes de actions, DTOs, enums, repositories e componentes Livewire.
•	Criar o arquivo docs/custom-constraints-discovery.md com as descobertas, riscos arquiteturais e pontos concretos de integração.


## Contexto
Projeto Laravel 12 + Livewire modular com AG desacoplado.

## Escopo
- Identificar entidades principais
- Identificar ponto de integração com solver
- Identificar estrutura de frontend

## Restrições
- Não modificar código ainda

## Passos
1. Analisar estrutura de pastas
2. Localizar ScheduleProblem
3. Mapear entidades: turma, disciplina, tempo, dia
4. Mapear fluxo do solver

## Critérios de Aceite
•	Existe documentação objetiva com o agregado escolhido e a justificativa arquitetural.
•	Estão identificados os pontos exatos onde o solver será enriquecido com as novas constraints.
•	Nenhuma implementação começou sem o mapeamento do projeto real.


## Validação
- Arquivo `docs/custom-constraints-discovery.md` existe
- Contém todos os itens mapeados

---

# FASE 1 — Modelagem

## Objetivo
Definir estrutura das constraints

## Contexto
Sistema deve suportar expansão futura

## Escopo
- Definir tipos
- Definir payloads mínimos, campos obrigatórios, validações semânticas, tratamento hard versus soft e regra de peso.
- Definir enums

## Restrições
- Não usar Eloquent
- Não misturar com AG

## Passos
1. Definir 3 tipos principais: SYNC_SAME_TIMESLOT, MUTUAL_EXCLUSION e TIME_PLACEMENT
2. Definir payload de cada tipo
3. Definir enums

## Critérios de Aceite
•   Documento de especificação criado
•	Cada tipo possui payload mínimo documentado.
•	Estão definidas validações semânticas e regras de peso.
•	A modelagem suporta expansão futura sem depender de ifs espalhados.


## Validação
- `docs/custom-constraints-spec.md` criado
- Contém exemplos JSON

---

# FASE 2 — Persistência

## Objetivo
•	Criar estrutura de banco
•	Criar a migration da tabela schedule_constraints com vínculo ao agregado correto, name, description, type, level, weight, is_active, payload_json, created_by, updated_by, timestamps e soft deletes, se já for padrão do projeto.
•	Criar o model Eloquent de infraestrutura com casts adequados.
•	Criar índices para contexto, type e is_active.


## Contexto
Constraints devem ser persistidas

## Escopo
- Migration
- Model
- Índices

## Restrições
- Não usar lógica de domínio aqui

## Passos
1. Criar migration
2. Criar model
3. Definir casts

## Critérios de Aceite
- Migration executa sem erro
- Tabela criada corretamente

## Validação
- Rodar migrate com sucesso

---

# FASE 3 — Domínio

## Objetivo
•	Criar Domínio de constraints
•	Criar a estrutura Modules/Horarios/Domain/Constraints com Entities, Enums, ValueObjects, DTOs, Factories, Evaluators, Results e Validators.
•	Criar enums: ConstraintType, ConstraintLevel, TimePlacementMode, SyncOccurrenceMode e SyncMatchMode.
•	Criar DTOs: CreateScheduleConstraintInput, UpdateScheduleConstraintInput e ScheduleConstraintData.
•	Criar entidades ou objetos de domínio: ScheduleConstraint, SyncSameTimeslotConstraint, MutualExclusionConstraint e TimePlacementConstraint.


## Contexto
Domain puro

## Escopo
- Entities
- DTOs
- Enums
- Value Objects

## Restrições
- Sem Eloquent

## Passos
1. Criar estrutura de pastas
2. Criar enums
3. Criar DTOs
4. Criar entities

## Critérios de Aceite
•	O domínio está isolado e fortemente tipado.
•	Não há arrays mágicos espalhados como estrutura principal do domínio.
•	Os três tipos iniciais estão representados de forma explícita.
•	Código compilando sem erros

## Validação
- Teste unitário básico passando

---

# FASE 4 — Validação e Factory

## Objetivo
•	Garantir integridade das constraints
•	Criar ScheduleConstraintFactory.
•	Criar validadores por tipo: SyncSameTimeslotConstraintValidator, MutualExclusionConstraintValidator e TimePlacementConstraintValidator.
•	Validar coerência entre level, weight e payload.


## Contexto
Payloads variam por tipo

## Escopo
- Validators
- Factory

## Restrições
- Não aceitar payload inválido

## Passos
1. Criar validators
2. Criar factory
3. Validar inputs

## Critérios de Aceite
•	Payloads inválidos são rejeitados com mensagens claras.
•	O tipo correto é construído pela factory.
•	Não há criação direta de constraint sem validação semântica.


## Validação
- Testes cobrindo erros

---

# FASE 5 — Repositórios e mapeamento

## Objetivo
•	Persistir e recuperar constraints
•	Criar interface e implementação de repositório para criar, atualizar, remover, listar por contexto, listar ativas e buscar por id.
•	Criar mapper entre model de persistência e DTO ou objeto de domínio.


## Contexto
Separação infra/domínio

## Escopo
- Repository interface
- Implementação

## Restrições
- Domain não conhece Eloquent

## Passos
1. Criar interface
2. Implementar repository
3. Criar mapper

## Critérios de Aceite
•	CRUD funcionando
•	O domínio não recebe model Eloquent.
•	As operações de CRUD funcionam no contexto correto.
•	Constraints inativas podem ser filtradas corretamente.


## Validação
- Teste de persistência passando

---

# FASE 6 — Camada de aplicação

## Objetivo
•	Orquestrar operações
•	Criar actions: CreateScheduleConstraintAction, UpdateScheduleConstraintAction, DeleteScheduleConstraintAction, ToggleScheduleConstraintStatusAction, ListScheduleConstraintsAction e LoadActiveScheduleConstraintsAction.
•	Garantir validação, persistência e retorno consistente para UI e integração com solver.


## Contexto
Camada intermediária

## Escopo
- Actions

## Restrições
- Sem lógica pesada

## Passos
1. Criar actions
2. Integrar repository
3. Validar dados

## Critérios de Aceite
•	Actions funcionando
•	As actions cobrem o ciclo completo de gerenciamento.
•	LoadActiveScheduleConstraintsAction carrega somente constraints ativas.
•	A camada de aplicação não contém regra de apresentação.


## Validação
- Testes de actions passando

---

# FASE 7 — Humanização e resumo legível

## Objetivo
•	Gerar descrição legível
•	Criar ScheduleConstraintHumanizer para converter uma constraint em descrição amigável.
•	Reutilizar o humanizer em listagens, confirmações, logs e diagnósticos.


## Contexto
UI precisa de feedback claro

## Escopo
- Formatter

## Passos
1. Criar humanizer
2. Mapear mensagens

## Critérios de Aceite
•	Texto legível gerado
•	A UI mostra descrições compreensíveis em português do Brasil.
•	O humanizer não depende de Livewire.
•	Os três tipos iniciais geram descrições legíveis.


## Validação
- Teste unitário validando saída

---

# FASE 8 — Frontend (Interface Livewire)

## Objetivo
•	Interface de usuário
•	Incorporar o gerenciamento das constraints no ponto mais coerente do frontend existente.
•	Criar listagem com nome, tipo, nível, status, descrição amigável e ações.
•	Criar formulário dinâmico com campos gerais e campos específicos por tipo.
•	Exibir preview textual da regra quando possível.


## Contexto
Livewire

## Escopo
- Listagem
- Formulário dinâmico

## Restrições
- Seguir padrão do sistema

## Passos
1. Criar tela
2. Criar formulário
3. Integrar actions

## Critérios de Aceite
•	Usuário cria/edita constraints
•	O formulário muda dinamicamente conforme o tipo.
•	Edição recarrega corretamente o payload salvo.
•	Labels, mensagens e fluxo estão em português e coerentes com o padrão visual do sistema.


## Validação
- Teste manual funcional

---

# FASE 9 — Integração Solver

## Objetivo
Levar constraints ao AG

## Contexto
Sem acoplamento

## Escopo
- Carregamento
- Conversão

## Restrições
- AG não conhece domínio

## Passos
1. Carregar constraints ativas
2. Converter para domain
3. Injetar no problema

## Critérios de Aceite
- Constraints disponíveis no solver

## Validação
- Log confirma carregamento

---

# FASE 10 — Fitness

## Objetivo
•	Aplicar penalidades
•	Criar ConstraintEvaluatorInterface, SyncSameTimeslotConstraintEvaluator, MutualExclusionConstraintEvaluator, TimePlacementConstraintEvaluator, ConstraintEvaluationPipeline, ConstraintViolationResult e ConstraintEvaluationSummary.
•	Integrar o pipeline ao cálculo atual de fitness respeitando o contrato existente de FitnessResult.


## Escopo
- Evaluators
- Pipeline

## Passos
1. Criar evaluators
2. Integrar fitness

## Critérios de Aceite
•	Penalidades aplicadas corretamente
•	Constraints HARD impactam hardPenalty.
•	Constraints SOFT impactam softPenalty usando weight.
•	É possível obter detalhes diagnósticos por constraint.


## Validação
- Teste verificando score

---

# FASE 11 — Avaliação por tipo

## Objetivo
Implementar regras

## Escopo
- 3 tipos

## Passos
1. Implementar SYNC: all_to_all com occurrence_mode all e at_least_one.
2. Implementar EXCLUSION: exclusão por timeslot entre os grupos definidos.
3. Implementar TIME: modos required, preferred e forbidden com allowed_periods e allowed_days.

## Critérios de Aceite
- Cada tipo funcionando isoladamente

## Validação
- Testes específicos

---

# FASE 12 — Viabilidade

## Objetivo
•	Detectar problemas antes do AG
•	Criar ConstraintFeasibilityAnalyzer e ConstraintFeasibilityReport.
•	Detectar payloads inválidos, excesso de exigência sobre primeiro tempo, sincronizações suspeitas, contradições evidentes e combinações impossíveis de dias ou períodos.
•	Integrar ao diagnóstico estrutural existente, se houver.


## Escopo
- Analyzer

## Passos
1. Criar analyzer
2. Integrar diagnóstico

## Critérios de Aceite
•	O sistema gera warnings claros antes da execução.
•	O mecanismo está preparado para alimentar o RiskIndex, se aplicável.
•	As constraints problemáticas podem ser identificadas antes do AG rodar.


## Validação
- Teste simulando erro estrutural

---

# FASE 13 — Repair (Preparação)

## Objetivo
•	Preparar pontos de extensão para repair relacionado a sincronização, posicionamento temporal e exclusão temporal, sem implementar solver paralelo.
•	Preparar extensão futura

## Escopo
- Hooks

## Passos
1. Criar pontos de extensão

## Critérios de Aceite
•	A arquitetura permite extensão futura sem refatoração destrutiva.

## Validação
- Estrutura clara

---

# FASE 14 — Testes

## Objetivo
•	Criar testes unitários para enums, factory, validators, humanizer, evaluators e feasibility analyzer.
•	Criar testes de integração ou feature para CRUD, persistência do payload, carregamento das ativas, integração com montagem do problema e penalização no fitness.


## Escopo
- Unitários
- Integração

## Passos
1. Criar testes
2. Cobrir cenários

## Critérios de Aceite
•	Existem testes cobrindo os três tipos iniciais.
•	Existe teste para hard versus soft e efeito do weight.
•	Existe teste garantindo que constraint inativa não entra no solver.
•	Todos testes passando

## Validação
- Coverage adequada

---

# FASE 15 — Documentação

## Objetivo
•	Criar docs/custom-constraints.md com visão geral, estrutura criada, tipos suportados, payloads, fluxo da UI ao solver, integração com fitness, como adicionar um novo tipo e limitações atuais.

## Escopo
- Markdown

## Passos
1. Criar doc final

## Critérios de Aceite
•	A documentação explica como a feature funciona ponta a ponta.
•	Há orientação clara para expansão futura.
•	O documento está alinhado com o código real implementado.

## Validação
- Revisão manual

---

# FASE 16 — Revisão Final

## Objetivo
•	Garantir qualidade
•	Revisar todo o código para garantir tipagem forte, nomes consistentes, ausência de duplicação grosseira, ausência de dependência circular e isolamento do módulo AG.
•	Refatorar com cautela apenas o necessário caso alguma estrutura antiga de regras esteja excessivamente acoplada.


## Escopo
- Code review

## Passos
1. Revisar código
2. Ajustar inconsistências

## Critérios de Aceite
•	Sem violações arquiteturais
•	Não há Eloquent no Domain.
•	Não há lógica de constraints vazando para Modules/AG.
•	O frontend não conhece detalhes internos da semântica do solver.


## Validação
- Checklist final aprovado

---

Critérios globais de pronto
•	A feature permite cadastrar, editar, ativar, desativar e excluir constraints personalizadas.
•	As constraints ativas impactam formalmente a geração do horário.
•	Existe distinção entre penalidades hard e soft.
•	A implementação preserva a arquitetura modular do projeto.
•	Há cobertura mínima de testes e documentação técnica final.


# INSTRUÇÃO FINAL

Execute fase por fase.

Após cada fase:
- Validar critérios
- Marcar DONE ou NOT DONE
- Só avançar após validação completa
