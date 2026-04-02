# Discovery — Constraints Customizadas

## Status da Fase 0

- Fase 0: DONE
- Implementacao iniciada: NAO
- Validacao da fase: OK

## Objetivo da discovery

Mapear o agregado correto para vinculo das constraints customizadas, os pontos exatos de montagem do ScheduleProblem, o fluxo atual do solver, as entidades de calendario relevantes e os padroes arquiteturais existentes no projeto.

## Resumo executivo

O agregado correto para as novas constraints customizadas e o Horario.

Essa escolha e a mais coerente com a arquitetura atual por quatro motivos:

1. O solver parte de um Horario como raiz de entrada em [app/Modules/AG/Application/RunGeneticAlgorithm.php](app/Modules/AG/Application/RunGeneticAlgorithm.php).
2. O grafo de dados consumido pelo problema e montado a partir de build(Horario) em [app/Modules/Horarios/Domain/Builders/ScheduleDataBuilder.php](app/Modules/Horarios/Domain/Builders/ScheduleDataBuilder.php).
3. As restricoes temporais ja existentes tambem pertencem ao contexto de Horario por meio de horario_id em [app/Models/RestricaoTempo.php](app/Models/RestricaoTempo.php).
4. O frontend operacional do modulo e centrado no gerenciamento de um horario individual em [app/Modules/Horarios/UI/Livewire/Manage.php](app/Modules/Horarios/UI/Livewire/Manage.php).

Conclusao arquitetural:

As novas constraints devem ser persistidas por horario_id e tratadas como configuracao contextual do horario, nao como entidade global do solver e nem como extensao direta de AG.

## Agregado escolhido

### Agregado: Horario

### Justificativa

- O modelo [app/Models/Horario.php](app/Models/Horario.php) ja concentra aulas, restricoes, configuracao, alocacoes e execucoes.
- O solver recebe um Horario e deriva todo o problema combinatorio a partir dele.
- A UI de configuracao e operacao ja usa o Horario como unidade de trabalho.
- Vincular a constraint diretamente a Turma, Professor ou Disciplina quebraria o contexto da execucao, porque a ativacao de uma regra depende do horario sendo gerado, nao apenas da entidade isolada.

### Recomendacao estrutural

- Tabela nova vinculada a horario_id.
- Referencias a turma, disciplina, aula, professor, dias e tempos devem ficar no payload tipado da constraint, nao como nova raiz agregada.
- Isso permite ter a mesma turma ou disciplina participando de regras diferentes em horarios diferentes.

## Pontos exatos de integracao com o solver

### 1. Entrada principal do solver

Arquivo: [app/Modules/AG/Application/RunGeneticAlgorithm.php](app/Modules/AG/Application/RunGeneticAlgorithm.php)

Papel atual:

- Recebe Horario.
- Monta GeneticAlgorithmConfigDTO.
- Cria ou reusa ScheduleExecution.
- Construi ScheduleData com ScheduleDataBuilder.
- Monta as regras de fitness atuais.
- Instancia GreedyRepairOperator.
- Instancia ScheduleProblem.
- Monta GeneticAlgorithmEngine e componentes auxiliares.

Ponto de enriquecimento recomendado:

- Carregar constraints ativas logo apos construir o Horario/config e antes de instanciar ScheduleProblem.
- Converter constraints persistidas para objetos de dominio do modulo Horarios.
- Inserir uma representacao solver-safe dessas constraints dentro do ScheduleData ou como dependencia adicional do ScheduleProblem.

### 2. Montagem do problema genetico

Arquivo: [app/Modules/Horarios/Domain/Problem/ScheduleProblem.php](app/Modules/Horarios/Domain/Problem/ScheduleProblem.php)

Papel atual:

- Implementa GeneticProblem.
- Cria individuos iniciais.
- Avalia fitness por meio do FitnessEvaluator.
- Mantem logica de construcao, fail-fast, quality gate, repair e telemetria.

Ponto de enriquecimento recomendado:

- Receber constraints customizadas como parte do estado do problema, preferencialmente via ScheduleData expandido ou dependencia de pipeline especializada.
- Nao acoplar Eloquent aqui.
- Nao carregar constraints de banco dentro do problema.

### 3. Montagem da visao de dados do problema

Arquivo: [app/Modules/Horarios/Domain/Builders/ScheduleDataBuilder.php](app/Modules/Horarios/Domain/Builders/ScheduleDataBuilder.php)

Papel atual:

- Traduz Horario e seus relacionamentos para Value Objects puros.
- Gera lessons, professors, classes, timeSlots, restrictions e availability maps.

Ponto de enriquecimento recomendado:

- Este e o ponto mais natural para incorporar constraints customizadas ao grafo de dominio consumido pelo solver.
- Pode ser expandido para produzir um bloco adicional de dados, por exemplo customConstraints, sem contaminar AG com modelos de persistencia.

### 4. Integracao no fitness

Arquivo principal atual: [app/Modules/AG/Domain/Fitness/FitnessEvaluator.php](app/Modules/AG/Domain/Fitness/FitnessEvaluator.php)

Observacao:

- O fitness atual e montado em RunGeneticAlgorithm por uma lista de regras hard e soft.
- A integracao futura das constraints deve preservar o contrato atual de FitnessResult.

Ponto de enriquecimento recomendado:

- Inserir um pipeline de avaliacao de constraints customizadas como camada adicional de penalizacao.
- HARD deve somar em hardPenalty.
- SOFT deve somar em softPenalty com base em weight.
- O ideal e manter os detalhes diagnosticos em uma estrutura separada, reutilizavel por diagnostico e UI.

## Entidades principais mapeadas

### Horario

Arquivo: [app/Models/Horario.php](app/Models/Horario.php)

Relacionamentos relevantes:

- aulas()
- restricoes()
- alocacoes()
- executions()
- configuracaoHorario()

Interpretacao:

- Horario e o contexto agregador da geracao.

### Aula

Arquivo: [app/Models/Aula.php](app/Models/Aula.php)

Campos relevantes para constraints futuras:

- horario_id
- professor_id
- disciplina_id
- turma_id
- aulas_semana
- tipo
- aulas_consecutivas
- max_aulas_dia
- dias_preferidos
- tempos_preferidos
- ativa

Interpretacao:

- Para o solver, Aula e a unidade mais proxima do que sera restrito por sincronizacao ou posicionamento.

### LessonData

Arquivo: [app/Modules/Horarios/Domain/ValueObjects/LessonData.php](app/Modules/Horarios/Domain/ValueObjects/LessonData.php)

Campos relevantes:

- id
- professorId
- classId
- disciplinaId
- requiredSlots
- weeklyOccurrences
- requiresConsecutive
- preferredDays
- preferredPeriods
- maxPerDay

Interpretacao:

- Esse VO ja oferece um ponto limpo para avaliadores de constraint operarem sem depender de Eloquent.

### RestricaoTempo

Arquivo: [app/Models/RestricaoTempo.php](app/Models/RestricaoTempo.php)

Campos relevantes:

- horario_id
- entidade_type
- entidade_id
- dia_semana
- tempo
- status
- peso

Interpretacao:

- Ja existe uma restricao temporal por entidade dentro do contexto do horario.
- Isso reforca a decisao de persistir custom constraints tambem por horario.
- Tambem indica que ha distincao preexistente entre bloqueio e preferencia, util para hard versus soft.

### Turma

Arquivo: [app/Models/Turma.php](app/Models/Turma.php)

Uso relevante:

- E participante principal de exclusao temporal e sincronizacao entre aulas.

### Disciplina

Arquivo: [app/Models/Disciplina.php](app/Models/Disciplina.php)

Uso relevante:

- Pode servir como agrupador semantico no payload, mas a unidade solver real continua sendo Aula ou conjunto de aulas.

### Professor

Arquivo: [app/Models/Professor.php](app/Models/Professor.php)

Uso relevante:

- Participa diretamente da disponibilidade e de conflitos hard ja existentes.

## Calendario: dias e tempos

### Origem dos dias e tempos

Arquivo: [app/Modules/Horarios/Domain/Builders/ScheduleDataBuilder.php](app/Modules/Horarios/Domain/Builders/ScheduleDataBuilder.php)

Como funciona hoje:

- timeSlots sao gerados a partir de configuracaoHorario.dias_semana e configuracaoHorario.aulas_por_dia.
- Cada TimeSlot possui id, day e lessonNumber.

Leitura arquitetural:

- Dias e tempos nao sao entidades persistidas isoladas no dominio atual.
- Eles sao derivados da configuracao do horario.
- Logo, payloads de constraint devem referenciar day e period como dados do contexto do horario, nao como tabela mestre obrigatoria.

## Fluxo atual do solver

Fluxo ponta a ponta observado:

1. A UI operacional dispara execucao em [app/Modules/AG/UI/Livewire/ExecutionCenter.php](app/Modules/AG/UI/Livewire/ExecutionCenter.php).
2. Essa tela cria ScheduleExecution e despacha GerarHorarioJob.
3. A camada de aplicacao usa [app/Modules/Horarios/Application/GenerateScheduleAction.php](app/Modules/Horarios/Application/GenerateScheduleAction.php).
4. Essa action delega para [app/Modules/AG/Application/RunGeneticAlgorithm.php](app/Modules/AG/Application/RunGeneticAlgorithm.php).
5. RunGeneticAlgorithm monta ScheduleData e ScheduleProblem.
6. GeneticAlgorithmEngine executa a evolucao.
7. PersistBestSolutionService grava alocacoes finais de volta no Horario.

Conclusao:

- A carga das constraints ativas deve acontecer antes da instanciacao de ScheduleProblem.
- O ponto mais limpo para isso e entre o carregamento do Horario e a construcao de ScheduleData ou imediatamente depois dela.

## Frontend existente e ponto coerente para a UI

### Tela principal de gerenciamento do horario

Arquivo: [app/Modules/Horarios/UI/Livewire/Manage.php](app/Modules/Horarios/UI/Livewire/Manage.php)

Abas atuais:

- overview
- config
- aulas
- restricoes
- algoritmo
- diagnostico

Conclusao:

- O ponto mais coerente para incorporar gerenciamento de constraints customizadas e uma nova aba dentro de Manage.
- Isso preserva a experiencia atual centrada no Horario.

Recomendacao inicial de UX:

- Adicionar uma aba nova, por exemplo constraints.
- Nao colocar a feature diretamente em ExecutionCenter, porque la o usuario opera o solver, nao define regras estruturais do horario.

### Tela de execucao do solver

Arquivo: [app/Modules/AG/UI/Livewire/ExecutionCenter.php](app/Modules/AG/UI/Livewire/ExecutionCenter.php)

Uso adequado no contexto da nova feature:

- Exibir somente resumo das constraints ativas e possiveis warnings de viabilidade.
- Nao e o melhor lugar para CRUD completo.

## Padroes arquiteturais existentes

### Domain em Horarios

Padrao observado:

- Value Objects fortemente tipados em app/Modules/Horarios/Domain/ValueObjects.
- Builders para traducao do agregado em dados solver-safe.
- Rules hard e soft separadas.
- DTOs ja aparecem em Analysis, como [app/Modules/Horarios/Domain/Analysis/DTO/FeasibilityReport.php](app/Modules/Horarios/Domain/Analysis/DTO/FeasibilityReport.php).

### Application em Horarios

Padrao observado:

- Orquestracao enxuta via services/actions, como [app/Modules/Horarios/Application/GenerateScheduleAction.php](app/Modules/Horarios/Application/GenerateScheduleAction.php) e [app/Modules/Horarios/Application/PersistBestSolutionService.php](app/Modules/Horarios/Application/PersistBestSolutionService.php).

### AG desacoplado do dominio de Horarios

Padrao observado:

- AG opera sobre contratos e estruturas genericas.
- O dominio de Horarios injeta problema, dados e regras.

Implicacao direta:

- As novas constraints devem nascer em Modules/Horarios e chegar ao AG apenas como dados e avaliadores de fitness, nunca como Eloquent nem como regra espalhada dentro de AG.

### Repository pattern

Padrao observado:

- Nao ha um repository formal dominante hoje em Modules/Horarios.
- O projeto mistura Eloquent direto em UI/Livewire e services de aplicacao.

Implicacao:

- A feature pode introduzir repositório para constraints sem quebrar a arquitetura, desde que ele fique contido na feature e que o dominio continue sem conhecer Eloquent.
- Isso sera uma melhoria localizada, nao uma inconsistencia grave.

## Riscos arquiteturais identificados

### 1. Acoplamento indevido ao modulo AG

Risco:

- Colocar enums, payloads ou regras de constraint dentro de Modules/AG.

Impacto:

- O AG deixaria de ser genericamente reutilizavel.

Diretriz:

- Toda semantica de custom constraint deve nascer em Modules/Horarios.

### 2. Persistir alvo da constraint fora do contexto do Horario

Risco:

- Criar constraints globais por turma, disciplina ou professor sem horario_id.

Impacto:

- Regras ambíguas entre horarios diferentes.

Diretriz:

- Sempre vincular a horario_id.

### 3. Misturar Eloquent com Domain puro

Risco:

- Passar models para validators, evaluators ou ScheduleProblem.

Impacto:

- Domain deixa de ser testavel e tipado.

Diretriz:

- Mapper ou factory deve traduzir persistencia em DTOs/VOs/entidades puras.

### 4. Espalhar ifs por tipo de constraint

Risco:

- Implementar type checks em varios pontos do codigo.

Impacto:

- Baixa extensibilidade.

Diretriz:

- Criar enums, factory e evaluators por tipo desde o inicio.

### 5. Injetar constraints tarde demais no fluxo

Risco:

- Carregar constraints apenas no evaluator final sem refletir no diagnostico ou viabilidade.

Impacto:

- Regras podem penalizar, mas nao alertar antecipadamente inconsistencias estruturais.

Diretriz:

- Preparar tambem analyzer de viabilidade e resumo humanizado.

## Pontos concretos de integracao recomendados

### Persistencia

- Nova tabela schedule_constraints com horario_id como FK obrigatoria.

### Dominio

- Novo namespace: app/Modules/Horarios/Domain/Constraints.

### Aplicacao

- Actions dedicadas em Modules/Horarios/Application para CRUD e carregamento das ativas.

### Builder/mapper para solver

- Enriquecer ScheduleDataBuilder ou criar um builder complementar para anexar custom constraints ao grafo do problema.

### Solver

- RunGeneticAlgorithm deve carregar as constraints ativas antes de instanciar ScheduleProblem.

### Fitness

- Integrar um pipeline de avaliacao que produza hardPenalty, softPenalty e detalhes por constraint.

### UI

- Incorporar a feature como aba nova dentro do gerenciamento de horario em [app/Modules/Horarios/UI/Livewire/Manage.php](app/Modules/Horarios/UI/Livewire/Manage.php).

## Recomendacao de desenho para a Fase 1

Com base no codigo atual, a modelagem mais segura e:

1. agregado de persistencia: Horario
2. payload tipado por tipo de constraint
3. dominio novo dentro de Modules/Horarios/Domain/Constraints
4. mapper de persistencia para objetos puros
5. carregamento das ativas em camada de aplicacao
6. injecao no ScheduleProblem via ScheduleData expandido ou dependencia especializada

## Checklist de aceite da Fase 0

- Agregado escolhido e justificado: OK
- Ponto de integracao com solver identificado: OK
- Regras atuais de fitness localizadas: OK
- Entidades de turmas, disciplinas, dias e tempos mapeadas: OK
- Estrutura de frontend mapeada: OK
- Nenhuma implementacao iniciada antes da discovery: OK

## Proxima fase recomendada

Fase 1 — Modelagem.

Primeira decisao a preservar nas proximas fases:

- schedule_constraints deve pertencer ao Horario e nao ao AG.
