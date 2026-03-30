# 🔍 AUDITORIA COMPLETA - ScheduleDataBuilder

**Data**: 29 de março de 2026
**Status**: ❌ CRÍTICO - Múltiplas falhas encontradas
**Arquivo**: `app/Modules/Horarios/Domain/Builders/ScheduleDataBuilder.php`

---

## 📋 SUMÁRIO EXECUTIVO

O `ScheduleDataBuilder` está **completamente quebrado** para processar restrições (restricoes_tempo). Nenhuma restrição será lida, processada ou aplicada corretamente, causando:

- ✗ Alocações inválidas (professores/turmas com conflito de horário)
- ✗ Violações de restrições de bloqueio (mandatory blocks ignorados)
- ✗ Campo `max_aulas_dia` de Turma acesso a valor inexistente
- ✗ Campo `carga_maxima` de Professor acesso a valor inexistente
- ✗ Estrutura de dados inconsistente entre modelo e uso

**Risco**: CRITICO - O algoritmo genético pode produzir horários inválidos.

---

## 🔴 PROBLEMAS CRÍTICOS ENCONTRADOS

### **PROBLEMA #1: Acesso Incorreto ao Relacionamento de Restrições**

**Localização**: Linha 123
**Código Atual**:
```php
$restricoes = $horario->restricoes_tempo ?? $horario->restricoesTempo ?? [];
```

**Análise**:
- ❌ Horario NÃO tem atributo `restricoes_tempo` (propriedade de banco)
- ❌ Horario NÃO tem atributo `restricoesTempo` (property style)
- ✅ Horario TEM método `restricoes()` que retorna `HasMany` -> RestricaoTempo

**Modelo Correto**:
```php
public function restricoes(): HasMany
{
    return $this->hasMany(RestricaoTempo::class);
}
```

**Correção**:
```php
$restricoes = $horario->restricoes()->get() ?? [];
// ou mais seguro:
$restricoes = collect($horario->restricoes ?? []);
```

**Impacto**: 🔴 CRÍTICO - $restricoes será SEMPRE array vazio

---

### **PROBLEMA #2: Campo "tipo" Não Existe em RestricaoTempo**

**Localização**: Linha 129
**Código Atual**:
```php
'entity_type' => $r->tipo,
```

**Análise**:
- ❌ RestricaoTempo NÃO tem campo `tipo`
- ✅ RestricaoTempo TEM campo `entidade_type` (via morphs)
- ✅ Campo `entidade_type` contém FQN class como: `App\Models\Professor`

**Migração Correta**:
```php
$table->morphs('entidade'); // Cria entidade_type e entidade_id
```

**Correção**:
```php
'entity_type' => $r->entidade_type, // Retorna "App\Models\Professor", etc.
```

**Impacto**: 🔴 CRÍTICO - Será gerado `null` na estrutura de dados

---

### **PROBLEMA #3: Campo "time_slot" Não Existe**

**Localização**: Linha 131
**Código Atual**:
```php
'time_slot' => $r->time_slot,
```

**Análise**:
- ❌ RestricaoTempo NÃO tem campo `time_slot` como um objeto
- ✅ RestricaoTempo TEM campos `dia_semana` (int) e `tempo` (int) separados
- ✅ TimeSlot deve ser construído como ValueObject

**Campos Disponíveis em RestricaoTempo**:
```
- dia_semana: integer (1=Seg, 2=Ter, ..., 6=Sab)
- tempo: integer (1, 2, 3, ...)
```

**Correção**:
```php
'time_slot' => new TimeSlot(
    id: $r->dia_semana . '_' . $r->tempo,
    day: $r->dia_semana,
    lessonNumber: $r->tempo
),
```

**Impacto**: 🔴 CRÍTICO - Será gerado `null`, causando erro ao acessar `$slot->day`

---

### **PROBLEMA #4: Acesso a Propriedades de um Null Object**

**Localização**: Linhas 134-136
**Código Atual**:
```php
$slot = $r->time_slot;           // ❌ $slot = null
$dia = $slot->day ?? null;        // ❌ Tentando acessar propriedade de null
$periodo = $slot->lessonNumber ?? null;  // ❌ Idem
```

**Análise**:
- ❌ `$r->time_slot` não existe, então `$slot` = null
- ❌ Tentativa de acessar `$slot->day` em um null causará TypeError ou warning

**Correção**:
```php
$dia = $r->dia_semana;
$periodo = $r->tempo;
```

**Impacto**: 🔴 CRÍTICO - Possível TypeError ou lógica completamente quebrada

---

### **PROBLEMA #5: Comparação Incorreta de Tipo de Entidade**

**Localização**: Linhas 137 e 140
**Código Atual**:
```php
if ($r->tipo === 'professor') {        // ❌ Campo 'tipo' não existe
    $byProfessor[$r->entidade_id][$dia][$periodo] = true;
}

if ($r->tipo === 'turma') {            // ❌ Campo 'tipo' não existe
    $byClass[$r->entidade_id][$dia][$periodo] = true;
}
```

**Análise**:
- ❌ `$r->tipo` não existe
- ❌ Comparação seria impossível mesmo que existisse
- ✅ `$r->entidade_type` contém FQN class, não string simples
- ❌ Comparar contra `'professor'` seria sempre false

**Valores Reais de `entidade_type`**:
```
- "App\Models\Professor"
- "App\Models\Turma"
- "App\Models\Disciplina"
```

**Correção**:
```php
if ($r->entidade_type === Professor::class) {
    $byProfessor[$r->entidade_id][$dia][$periodo] = true;
}

if ($r->entidade_type === Turma::class) {
    $byClass[$r->entidade_id][$dia][$periodo] = true;
}
```

**Impacto**: 🔴 CRÍTICO - Restrições nunca serão categorizadas por professor/turma

---

### **PROBLEMA #6: Campo "obrigatorio" Não Existe**

**Localização**: Linha 128
**Código Atual**:
```php
'is_mandatory' => (bool) $r->obrigatorio,
```

**Análise**:
- ❌ RestricaoTempo NÃO tem campo `obrigatorio` (boolean)
- ✅ RestricaoTempo TEM campo `status` (enum: 'livre', 'preferencial', 'bloqueado')
- ✅ Campo `peso` (integer) para ponderação

**Mapeamento Correto**:
- `status = 'bloqueado'` → obrigatório (não usar este slot)
- `status = 'preferencial'` → preferência (evitar se possível)
- `status = 'livre'` → sem restrição

**Correção**:
```php
'is_mandatory' => $r->status === 'bloqueado',
```

**Impacto**: 🔴 CRÍTICO - Será gerado `null`, lógica de restrições quebrada

---

### **PROBLEMA #7: Campo "carga_maxima" em Professor**

**Localização**: Linha 61
**Código Atual**:
```php
maxWeeklyLoad: $aula->professor->carga_maxima ?? 40,
```

**Análise**:
- ❌ Professor NÃO tem campo `carga_maxima`
- ✅ Professor TEM campo `carga_horaria_maxima`

**Migração Confirmada**:
```php
$table->integer('carga_horaria_maxima')->default(40);
```

**Correção**:
```php
maxWeeklyLoad: (int) ($aula->professor->carga_horaria_maxima ?? 40),
```

**Impacto**: 🟠 ALTO - Será sempre 40 (default), mesmo que professor tenha limite diferente

---

### **PROBLEMA #8: Campo "max_aulas_dia" em Turma**

**Localização**: Linha 67
**Código Atual**:
```php
maxDailyLessons: $aula->turma->max_aulas_dia ?? 6,
```

**Análise**:
- ❌ Turma NÃO tem campo `max_aulas_dia`
- ❌ Campo existe em Aula (limite por disciplina/dia)
- ✅ Limite geral de aulas/dia está em ConfiguracaoHorario (`aulas_por_dia`)
- ❌ Confusão entre "limite geral do dia" vs "limite por turma"

**Migração de Turma** (confirmada):
```php
Schema::create('turmas', function (Blueprint $table) {
    $table->id();
    $table->string('nome');
    $table->string('codigo');
    // ... mas SEM max_aulas_dia
});
```

**O que existentes**:
- ConfiguracaoHorario: `aulas_por_dia` (ex: 5 aulas por dia)
- Aula: `max_aulas_dia` (ex: max 2 aulas de Math por dia)

**Correção Possível**:
```php
// Opção 1: Usar limite geral do dia
maxDailyLessons: (int) ($config->aulas_por_dia ?? 6),

// Opção 2: Se há modelo de limite por turma, adicionar campo a Turma
// e atualizar migração
```

**Impacto**: 🟠 ALTO - Será sempre 6 (default), sem considerar limite real por configuração

---

## ✅ CAMPOS CORRETOS (Sem Problemas)

| Campo | Modelo | Status | Notas |
|-------|--------|--------|-------|
| `$aula->getDuracaoTempos()` | Aula | ✅ OK | Método implementado |
| `$aula->aulas_semana` | Aula | ✅ OK | Campo integer |
| `$aula->aulas_consecutivas` | Aula | ✅ OK | Campo boolean |
| `$aula->dias_preferidos` | Aula | ✅ OK | Campo array/json |
| `$aula->tempos_preferidos` | Aula | ✅ OK | Campo array/json |
| `$aula->max_aulas_dia` | Aula | ✅ OK | Campo integer (não em Turma) |
| `$config->dias_semana` | ConfiguracaoHorario | ✅ OK | Campo integer |
| `$config->aulas_por_dia` | ConfiguracaoHorario | ✅ OK | Campo integer |
| `$config->agrupar_disciplinas` | ConfiguracaoHorario | ✅ OK | Campo boolean |
| `$config->max_aulas_seguidas` | ConfiguracaoHorario | ✅ OK | Campo integer |
| buildTimeSlots() | Builder | ✅ OK | Lógica correta |
| Eager loading (with) | Aula | ✅ OK | Relacionamentos existem |

---

## 📊 MATRIZ DE MAPEAMENTO - RestricaoTempo

| Campo Esperado Pelo Builder | Campo Correto | Tipo | Status |
|-----|----|----|---|
| `$r->tipo` | `$r->entidade_type` | `string` (FQN class) | ❌ ERRADO |
| `$r->time_slot` (object) | `dia_semana` + `tempo` | `integer` x 2 | ❌ ERRADO |
| `$r->obrigatorio` (bool) | `$r->status` | `enum('livre', 'preferencial', 'bloqueado')` | ❌ ERRADO |
| `$r->entidade_id` | `$r->entidade_id` | `integer` | ✅ OK |
| - | `$r->peso` | `integer` | IGNORADO |
| - | `$r->motivo` | `text` | IGNORADO |
| - | `$r->dia_semana` | `integer` | ✅ CORRETO |
| - | `$r->tempo` | `integer` | ✅ CORRETO |

---

## 🔄 FLUXO DE DADOS AFETADO

```
Horario {id}
    ↓
ScheduleDataBuilder::build()
    ├─ buildTimeSlots()  ✅ OK
    ├─ buildRestrictions()  ❌ BROKEN
    │   ├─ Lê: $horario->restricoes_tempo  ❌ NÃO EXISTE
    │   ├─ Processa: $r->tipo  ❌ NÃO EXISTE
    │   ├─ Cria: $r->time_slot  ❌ NÃO EXISTE
    │   └─ Mapeia: (bool) $r->obrigatorio  ❌ NÃO EXISTE
    │
    └─ buildAvailability()
        ├─ Recebe: $restrictionsByProfessor  ❌ VAZIO
        ├─ Recebe: $restrictionsByClass  ❌ VAZIO
        └─ Resulta: availableSlotsByProfessor  ❌ APENAS SLOTS LIVRES
                   availableSlotsByClass  ❌ APENAS SLOTS LIVRES
        ↓
ScheduleData {
    restrictions: [],  ❌ VAZIO
    availableSlotsByProfessor: [...TODOS SLOTS...],  ❌ INCORRETO
    availableSlotsByClass: [...TODOS SLOTS...],  ❌ INCORRETO
}
        ↓
ScheduleProblem
    ├─ GreedyRepairOperator: Usa availableSlots (INCORRETO)  ❌
    └─ Constraints: Nenhuma restrição aplicada  ❌
        ↓
HORÁRIO INVÁLIDO ❌ (Conflitos, bloqueios ignorados)
```

---

## 🛠️ CÓDIGO CORRIGIDO

### Substitua a função `buildRestrictions` completa:

```php
private function buildRestrictions(Horario $horario): array
{
    $restrictions = [];
    $byProfessor = [];
    $byClass = [];

    // ✅ CORREÇÃO 1: Usar método relacionamento em vez de atributo
    $restricoes = $horario->restricoes()->get() ?? [];

    foreach ($restricoes as $r) {
        // ✅ CORREÇÃO 3 & 4: Construir TimeSlot a partir dos campos corretos
        $dia = $r->dia_semana;
        $periodo = $r->tempo;

        $slot = new TimeSlot(
            id: $dia . '_' . $periodo,
            day: $dia,
            lessonNumber: $periodo
        );

        // ✅ CORREÇÃO 2 & 6: Usar entidade_type e status corretos
        $restrictions[] = [
            'entity_type' => $r->entidade_type,
            'entity_id' => $r->entidade_id,
            'time_slot' => $slot,
            'is_mandatory' => $r->status === 'bloqueado',
        ];

        // ✅ CORREÇÃO 5: Comparar contra class FQN
        if ($r->entidade_type === Professor::class) {
            $byProfessor[$r->entidade_id][$dia][$periodo] = true;
        }

        if ($r->entidade_type === Turma::class) {
            $byClass[$r->entidade_id][$dia][$periodo] = true;
        }
    }

    return [$restrictions, $byProfessor, $byClass];
}
```

### Corrija linha 61 (carga_maxima → carga_horaria_maxima):

```php
// ANTES:
if (! isset($professors[$aula->professor_id])) {
    $professors[$aula->professor_id] = new ProfessorData(
        id: $aula->professor_id,
        maxWeeklyLoad: $aula->professor->carga_maxima ?? 40,
    );
}

// DEPOIS:
if (! isset($professors[$aula->professor_id])) {
    $professors[$aula->professor_id] = new ProfessorData(
        id: $aula->professor_id,
        maxWeeklyLoad: (int) ($aula->professor->carga_horaria_maxima ?? 40),
    );
}
```

### Corrija linha 67 (max_aulas_dia em Turma):

```php
// ANTES:
if (! isset($classes[$aula->turma_id])) {
    $classes[$aula->turma_id] = new ClassData(
        id: $aula->turma_id,
        maxDailyLessons: $aula->turma->max_aulas_dia ?? 6,
    );
}

// DEPOIS:
if (! isset($classes[$aula->turma_id])) {
    $classes[$aula->turma_id] = new ClassData(
        id: $aula->turma_id,
        maxDailyLessons: (int) ($config->aulas_por_dia ?? 6),
    );
}
```

### Adicione imports necessários no início do arquivo:

```php
use App\Models\Professor;
use App\Models\Turma;
use App\Models\Disciplina;
```

---

## 📋 CHECKLIST DE CORREÇÃO

```
[ ] Adicionar imports: Professor, Turma, Disciplina
[ ] Corrigir linha 123: restricoes_tempo → restricoes()->get()
[ ] Corrigir linha 128: obrigatorio → status === 'bloqueado'
[ ] Corrigir linha 129: tipo → entidade_type
[ ] Corrigir linha 131: time_slot → new TimeSlot(...)
[ ] Corrigir linhas 134-136: $slot->day → $r->dia_semana
[ ] Corrigir linha 137: $r->tipo === 'professor' → entidade_type === Professor::class
[ ] Corrigir linha 140: $r->tipo === 'turma' → entidade_type === Turma::class
[ ] Corrigir linha 61: carga_maxima → carga_horaria_maxima
[ ] Corrigir linha 67: $aula->turma->max_aulas_dia → $config->aulas_por_dia
[ ] Testar: Verificar se restrições são carregadas corretamente
[ ] Testar: Executar algoritmo genético e validar horário
[ ] Testar: Verificar se bloqueios são respeitados
```

---

## 🧪 TESTES RECOMENDADOS

### 1. Teste Unitário para buildRestrictions()

```php
public function testRestrictionsAreMappedCorrectly()
{
    $horario = Horario::factory()->create();
    $professor = Professor::factory()->create();

    // Criar restrição de bloqueio
    RestricaoTempo::create([
        'horario_id' => $horario->id,
        'entidade_type' => Professor::class,
        'entidade_id' => $professor->id,
        'dia_semana' => 2,
        'tempo' => 3,
        'status' => 'bloqueado',
    ]);

    $builder = new ScheduleDataBuilder();
    $data = $builder->build($horario);

    // Verificar se restrição foi mapeada "true"
    $this->assertNotEmpty($data->restrictionsByProfessor);
    $this->assertTrue($data->restrictionsByProfessor[$professor->id][2][3] ?? false);
}
```

### 2. Teste de Integração

```php
public function testConstraintsAreEnforcedInSchedule()
{
    // Criar horário com restrições
    // Executar algoritmo genético
    // Verificar se restrições são respeitadas
    // Validar que nenhuma aula viola bloqueios
}
```

---

## 📚 REFERÊNCIAS

- [Migração RestricaoTempo](d:\laragon\www\horario\database\migrations\2026_01_30_000016_create_restricoes_tempo_table.php)
- [Model RestricaoTempo](d:\laragon\www\horario\app\Models\RestricaoTempo.php)
- [Model Horario](d:\laragon\www\horario\app\Models\Horario.php)
- [Model Professor](d:\laragon\www\horario\app\Models\Professor.php)
- [ScheduleDataBuilder](d:\laragon\www\horario\app\Modules\Horarios\Domain\Builders\ScheduleDataBuilder.php)

---

## 👤 Conclusão

O `ScheduleDataBuilder` está severamente quebrado. **Nenhuma restrição de tempo será processada**, causando geração de horários inválidos. As correções listadas acima são **obrigatórias** para o correto funcionamento do sistema de geração de horários.

**Tempo Estimado de Correção**: 30-45 minutos
**Criticidade**: 🔴 CRÍTICA - Sistema em produção não pode ser usado
