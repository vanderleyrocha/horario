Você está trabalhando no projeto Horário de Aula, um sistema modular de geração inteligente de horários escolares com Algoritmo Genético em PHP, Laravel 12 e Livewire 4, com arquitetura desacoplada entre:

Modules/AG → engine genética genérica
Modules/Horarios → domínio específico de geração de horários escolares
Contexto arquitetural obrigatório

Respeite rigorosamente estes princípios:

Nenhuma lógica de domínio do problema dentro do módulo AG
Nenhuma lógica evolutiva dentro do módulo Horarios fora dos pontos apropriados de integração
Nenhum uso de Eloquent no Domain
Nada de service monolítico
Nenhum operador genético conhecendo Laravel
Toda nova funcionalidade deve ser modular, extensível e cientificamente coerente com o solver

O objetivo desta implementação é criar uma funcionalidade completa para permitir que o usuário cadastre restrições personalizadas de horário, tais como:

As disciplinas selecionadas devem ser ministradas nas turmas selecionadas ao mesmo tempo
Uma disciplina não pode ser ministrada ao mesmo tempo que outra, ou a mesma disciplina não pode ocorrer simultaneamente em certas turmas
As disciplinas selecionadas devem ser ministradas no primeiro tempo
Estruturar isso de forma expansível para novas constraints futuras
Objetivo da implementação

Implementar de ponta a ponta um sistema de restrições customizadas do usuário, incluindo:

modelagem de banco de dados
entities / value objects / enums / DTOs no domínio
camada de aplicação
persistência
interface administrativa no frontend
integração com carregamento da execução
avaliação dessas constraints no solver via fitness
possibilidade de classificar constraints como hard ou soft
suporte inicial a 3 tipos:
SYNC_SAME_TIMESLOT
MUTUAL_EXCLUSION
TIME_PLACEMENT
Escopo funcional
1. O usuário deve conseguir cadastrar constraints customizadas para um determinado cenário de geração de horário

Essas constraints devem ser associadas ao contexto apropriado do sistema já existente, respeitando a modelagem atual do projeto. Se houver conceito de escola, ano letivo, matriz, unidade, turno, lotação ou cenário de geração, use o agregado mais adequado já existente no projeto para vincular a constraint.

As constraints devem conter:

nome amigável
descrição opcional
tipo
ativa/inativa
hard ou soft
peso, quando soft
payload estruturado
metadados mínimos de auditoria
Tipos de constraints a implementar
2. Tipo 1: sincronização temporal
SYNC_SAME_TIMESLOT

Semântica:
determinadas disciplinas, em determinadas turmas, devem ocorrer no mesmo slot de tempo.

Exemplo:

Matemática na turma 1A e Matemática na turma 1B no mesmo horário
Física na turma 2A e Química na turma 2B no mesmo horário, se o payload permitir pares distintos
Requisitos semânticos

Modelar esse tipo de forma genérica o suficiente para crescer depois.

Payload mínimo sugerido:

{
  "subject_ids": [1, 2],
  "class_ids": [10, 11],
  "occurrence_mode": "at_least_one",
  "match_mode": "all_to_all"
}

Implementar inicialmente os seguintes campos semânticos:

subject_ids: array de disciplinas envolvidas
class_ids: array de turmas envolvidas
occurrence_mode: inicialmente suportar:
all
at_least_one
match_mode: inicialmente suportar:
all_to_all

Se a modelagem atual do sistema exigir mais precisão, adaptar sem quebrar a ideia geral.

3. Tipo 2: exclusão temporal
MUTUAL_EXCLUSION

Semântica:
determinados alvos não podem ocorrer no mesmo slot de tempo.

Exemplos:

disciplina A não pode ocorrer ao mesmo tempo que disciplina B
a mesma disciplina não pode ser ministrada simultaneamente em certas turmas
um grupo de disciplinas não pode coincidir entre si

Payload mínimo sugerido:

{
  "left_subject_ids": [1],
  "left_class_ids": [10, 11],
  "right_subject_ids": [2],
  "right_class_ids": [12, 13],
  "scope": "timeslot"
}

Implementar inicialmente:

left_subject_ids
left_class_ids
right_subject_ids
right_class_ids
scope com valor inicial timeslot

Se necessário, permitir também o caso em que left e right representem o mesmo grupo.

4. Tipo 3: posicionamento temporal
TIME_PLACEMENT

Semântica:
disciplinas/turmas devem ocorrer em tempos específicos, dias específicos, ou faixas permitidas/proibidas.

Implementar inicialmente o caso:

disciplinas selecionadas devem ser ministradas no primeiro tempo

Mas a modelagem deve nascer genérica para suportar expansão futura.

Payload mínimo sugerido:

{
  "subject_ids": [1, 2],
  "class_ids": [10, 11],
  "allowed_periods": [1],
  "allowed_days": [1, 2, 3, 4, 5],
  "mode": "required"
}

Implementar:

subject_ids
class_ids
allowed_periods
allowed_days
mode suportando:
required
preferred
forbidden
Requisitos de modelagem de domínio
5. Criar estrutura de domínio para constraints no módulo Horarios

Criar uma estrutura semelhante a:

Modules/Horarios/
├── Domain
│   ├── Constraints
│   │   ├── Entities
│   │   ├── Enums
│   │   ├── ValueObjects
│   │   ├── DTOs
│   │   ├── Evaluators
│   │   ├── Results
│   │   └── Factories
Criar os seguintes elementos
Enums
ConstraintType
ConstraintLevel (HARD, SOFT)
TimePlacementMode
SyncOccurrenceMode
SyncMatchMode
DTOs
CreateScheduleConstraintInput
UpdateScheduleConstraintInput
ScheduleConstraintData
Entities / modelos de domínio puros

Criar uma abstração base para constraint e tipos especializados. Pode ser por herança ou composição, desde que fique coeso.

Sugestão:

ScheduleConstraint
SyncSameTimeslotConstraint
MutualExclusionConstraint
TimePlacementConstraint
Value Objects

Criar VOs quando fizer sentido, por exemplo:

ConstraintId
ConstraintWeight
ConstraintPriority se necessário
PeriodSet
DaySet
SubjectSet
ClassSet

Não exagerar em complexidade desnecessária, mas evitar arrays anêmicos por toda parte.

Requisitos de persistência
6. Criar migrations e persistência

Criar as migrations necessárias para armazenar as constraints customizadas.

Sugestão de tabela principal:

schedule_constraints

Campos mínimos:

id
contexto de vínculo com o cenário apropriado do sistema
name
description nullable
type
level
weight nullable
is_active
payload_json
created_by nullable
updated_by nullable
timestamps
soft deletes, se o padrão do projeto já utilizar
Regras
payload_json deve armazenar o payload específico do tipo
type, level, is_active e weight devem ser validados
se level = HARD, o peso pode ser nulo ou ignorado
se level = SOFT, deve haver peso válido positivo

Criar:

migration
model Eloquent de infraestrutura
repository de leitura/escrita
mapper entre persistence model e domain DTO/entity

Importante:

Eloquent deve ficar fora do domínio puro
repository interfaces devem ficar em camada adequada
implementar conversão de enum/string com cuidado
Requisitos de aplicação
7. Criar camada de aplicação para CRUD e carregamento das constraints

Criar actions/services de aplicação para:

listar constraints de um cenário
criar constraint
atualizar constraint
ativar/desativar constraint
remover constraint
carregar constraints ativas para a geração

Sugestão de nomes:

CreateScheduleConstraintAction
UpdateScheduleConstraintAction
DeleteScheduleConstraintAction
ToggleScheduleConstraintStatusAction
ListScheduleConstraintsAction
LoadActiveScheduleConstraintsAction

Criar validação semântica forte:

não aceitar payload inconsistente com o tipo
validar arrays de IDs
validar que períodos e dias existam em domínio compatível
validar coerência entre mode, level, weight e payload
Integração com frontend
8. Implementar interface Livewire 4 para gerenciamento das constraints

Criar UI administrativa no módulo de horários, integrada ao fluxo já existente.

Tela de listagem

Deve exibir:

nome
tipo
nível hard/soft
status ativa/inativa
resumo legível da regra
ações: editar, ativar/desativar, excluir
Tela/modal/form de criação e edição

Criar formulário dinâmico orientado ao tipo de constraint.

Campos gerais
nome
descrição
tipo
nível
peso
ativa/inativa
Campos específicos por tipo
Para SYNC_SAME_TIMESLOT
seleção de disciplinas
seleção de turmas
occurrence_mode
match_mode
Para MUTUAL_EXCLUSION
seleção de disciplinas do lado esquerdo
seleção de turmas do lado esquerdo
seleção de disciplinas do lado direito
seleção de turmas do lado direito
Para TIME_PLACEMENT
seleção de disciplinas
seleção de turmas
seleção de dias
seleção de tempos/períodos
mode
Requisitos de UX
formulário claro e objetivo
labels em português
mensagens de validação legíveis
exibir preview textual da regra antes de salvar, quando viável
ao editar, carregar corretamente o payload
usar componentes já existentes no projeto, se houver padrão visual
manter consistência com Livewire 4 e padrões do sistema

Se já existir uma tela de configuração do cenário de geração, encaixar essa UI no ponto arquitetural mais coerente. Não criar fluxo paralelo desorganizado.

Integração com solver
9. Integrar constraints customizadas ao fluxo de geração

Localizar o ponto correto do pipeline atual onde o problema de horário é montado para o solver. Integrar o carregamento das constraints ativas no contexto da geração.

Fluxo alvo:

carregar constraints ativas do cenário
converter para objetos de domínio adequados
disponibilizar ao componente de avaliação do problema
incorporar penalidades ao fitness
Requisitos obrigatórios
não modificar a engine AG para conhecer detalhes de constraint de horário
a integração deve ocorrer no módulo Horarios
o solver deve continuar recebendo o problema específico já enriquecido com essas regras
Avaliação no fitness
10. Criar pipeline de avaliação das custom constraints

Implementar avaliadores específicos por tipo.

Sugestão:

ConstraintEvaluatorInterface
SyncSameTimeslotConstraintEvaluator
MutualExclusionConstraintEvaluator
TimePlacementConstraintEvaluator
ConstraintEvaluationPipeline
ConstraintViolationResult
ConstraintEvaluationSummary
Semântica da avaliação

Cada constraint deve ser avaliada contra a solução/cromossomo/grade produzida pelo solver.

O retorno deve permitir:

quantidade de violações
penalidade hard
penalidade soft
detalhes diagnósticos por constraint
Regras
constraints HARD devem contribuir em hardPenalty
constraints SOFT devem contribuir em softPenalty usando weight
não quebrar o contrato atual de FitnessResult

Se já houver estrutura de avaliação de regras no módulo Horarios, integrar de forma coesa e refatorar se necessário para evitar duplicação ou acoplamento excessivo.

Diagnóstico e análise estrutural
11. Acrescentar análise prévia de viabilidade das constraints

Criar um analisador para detectar constraints estruturalmente problemáticas antes da execução do AG.

Sugestão:

ConstraintFeasibilityAnalyzer
ConstraintFeasibilityReport

Checar pelo menos:

payload inválido semanticamente
combinações impossíveis de períodos
excesso de exigências de primeiro tempo
grupos impossíveis de sincronização
contradições evidentes entre constraints ativas, quando detectáveis

A saída pode:

gerar warnings
elevar risco estrutural
opcionalmente bloquear a execução se o sistema já possuir esse tipo de bloqueio

Se o projeto já possui RiskIndex ou diagnóstico estrutural, integrar essas novas restrições ao mecanismo existente.

Repair operator
12. Tornar o sistema preparado para repair-aware, sem exagerar

Não é obrigatório implementar um repair avançado para todos os casos agora, mas preparar arquitetura para isso.

Se houver repair já existente, incluir pontos de extensão para futuras heurísticas relacionadas a:

alinhar aulas sincronizadas
mover aula para período permitido
eliminar conflitos de exclusão temporal

Se implementar algum ajuste já nesta etapa, fazê-lo de modo pequeno e seguro, sem transformar repair em solver alternativo.

Resumo legível das constraints
13. Criar componente para descrição humana das regras

Implementar um formatter/translator para converter payload técnico em descrição amigável para a UI.

Exemplos:

“Matemática e Física nas turmas 1A e 1B devem ocorrer no mesmo horário”
“Matemática da 1A não pode coincidir com Química da 1B”
“Inglês nas turmas 2A e 2B deve ocorrer no 1º tempo”

Sugestão:

ScheduleConstraintHumanizer
ou ConstraintDescriptionFormatter

Usar isso na listagem, confirmação e logs.

Testes
14. Criar testes automatizados completos

Implementar testes cobrindo:

Unitários
factories
validators
evaluators
humanizer
feasibility analyzer
Feature / integração
CRUD de constraints
validações do formulário
persistência correta do payload
carregamento das constraints ativas
integração com montagem do problema
Casos mínimos obrigatórios
criar constraint SYNC_SAME_TIMESLOT
criar constraint MUTUAL_EXCLUSION
criar constraint TIME_PLACEMENT exigindo primeiro tempo
verificar conversão hard vs soft
verificar penalização no fitness
verificar descrição legível
verificar que constraints inativas não entram no solver
Saídas esperadas do trabalho
15. Entregáveis obrigatórios

Ao final, entregar:

Migrations
Models de infraestrutura
Repositórios
DTOs / Enums / Entities / Value Objects
Factories e validators
Actions de aplicação
Telas Livewire 4
Integração com solver
Avaliadores de constraints
Analisador de viabilidade
Formatter de descrição humana
Testes
Documentação técnica curta em markdown
Requisitos de implementação
16. Regras de qualidade
respeitar PSR e padrões do projeto
usar tipagem forte sempre que possível
evitar arrays mágicos espalhados
evitar lógica duplicada
não quebrar funcionalidades existentes
escrever código limpo, nomeado e extensível
preferir composição a soluções rígidas
manter responsabilidade bem separada por camada
Requisitos de documentação no próprio PR/commit
17. Gerar documentação final com:
visão geral da funcionalidade
estrutura criada
como cadastrar uma nova constraint
como a constraint entra no solver
como adicionar novos tipos no futuro
Estratégia de execução
18. Ordem recomendada para implementação

Execute na seguinte ordem:

mapear o contexto atual do projeto e identificar o agregado correto para vincular as constraints
criar migrations e persistência
criar enums, DTOs, entities e factories
criar actions de CRUD
criar humanizer
criar UI Livewire
integrar carregamento ao fluxo de geração
implementar evaluators e pipeline de avaliação
integrar ao fitness
implementar feasibility analyzer
criar testes
escrever documentação técnica
Instruções finais importantes
19. Antes de codificar

Primeiro, inspecione a estrutura atual do projeto e identifique:

onde o cenário de geração é configurado
onde as regras atuais são avaliadas
como o solver recebe o problema
quais entidades representam turmas, disciplinas, dias e tempos
como o frontend Livewire está organizado
se já existe padrão para enums, DTOs, repositories e actions

Adapte os nomes concretos ao projeto real, mas preserve a arquitetura descrita.

20. Durante a implementação

Quando houver dúvida entre solução rápida e solução arquiteturalmente correta, prefira a solução arquiteturalmente correta.

Não criar gambiarra com ifs espalhados no fitness.
Não jogar JSON cru diretamente no solver.
Não acoplar a UI ao domínio.
Não acoplar o módulo AG a regras específicas de horários.

21. Resultado final esperado

Ao final, o sistema deve permitir que um usuário configure restrições personalizadas no frontend, persisti-las, visualizá-las, editá-las e fazer com que elas impactem formalmente a geração do horário, com distinção entre penalidades hard e soft, tudo isso sem violar a arquitetura modular do projeto.
