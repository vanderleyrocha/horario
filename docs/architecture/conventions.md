# Convenção de Estrutura

## Objetivo
Documentar a convenção consolidada antes de mover código. O foco é manter uma fronteira clara entre código global da aplicação e domínios específicos.

## Regras adotadas
- `app/Livewire` abriga apenas componentes da aplicação (login, dashboard, layout, ações globais).
- `app/Modules/<Modulo>/UI/Livewire` contém os Livewire do domínio, sempre dentro do módulo correspondente.
- Views globais permanecem em `resources/views/livewire` e não devem referenciar domínios específicos.
- Views de domínio ficam em `resources/views/modules/<modulo>/livewire`.
- `routes/web.php` funciona como agregador e carrega arquivos segmentados em `routes/web/`.
- Módulos principais (`AG`, `Horarios`) mantêm pastas próprias de `Application`, `Domain`, `Infrastructure`, `Support` e `UI`.

## Passos de validação rápida
1. Conferir se cada Livewire de domínio vive dentro de `app/Modules`.
2. Verificar se cada rota aponta para namespaces dentro de `App\Modules` ou componentes globais.
3. Confirmar se não há views de domínio fora de `resources/views/modules`.
4. Atualizar este documento sempre que houver exceção aprovada.
