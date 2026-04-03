# Project Guidelines

## Code Style

- Siga o estilo Laravel com PSR-12 e preserve o formato compatível com Pint antes de encerrar alterações em PHP.
- Mantenha mudanças pequenas e localizadas; não refatore áreas adjacentes sem necessidade clara.
- Em testes PHP, use Pest com `it()` e `expect()` seguindo os padrões já existentes em `tests/Feature` e `tests/Unit`.

## Architecture

- Este projeto usa Laravel 12 com Fortify para autenticação, Livewire 4 para UI reativa, Flux para componentes e Tailwind CSS v4 via Vite.
- Código global da aplicação fica em `app/Livewire`, `app/Actions`, `app/Jobs` e `routes/web.php`; código de domínio deve permanecer em `app/Modules/<Modulo>/Application|Domain|Infrastructure|Support|UI`.
- Para componentes de domínio, prefira `app/Modules/<Modulo>/UI/Livewire` com views em `resources/views/modules/<modulo>/livewire`; evite colocar UI de domínio em áreas globais.
- `routes/web.php` é o agregador principal e carrega arquivos segmentados em `routes/web/`.
- O solver de horários vive principalmente em `app/Modules/AG` e `app/Modules/Horarios`; antes de mudar lógica do algoritmo, leia `docs/AG_ARCHITECTURE.md` e `config/ag.php`.

## Build and Test

- Setup inicial: `composer run-script setup`
- Ambiente de desenvolvimento completo: `composer run-script dev`
- Lint PHP: `composer run-script lint`
- Verificação de lint sem corrigir: `composer run-script test:lint`
- Suite principal de testes: `composer run-script test`
- Assets frontend: `npm run dev` para watch e `npm run build` para build final

## Conventions

- Fortify customizado fica em `app/Actions/Fortify`; preserve esse padrão para fluxos de autenticação.
- Jobs longos e execuções do solver passam por fila; preserve telemetria, status de execução e fluxo assíncrono ao mexer em `app/Jobs` ou no módulo `AG`.
- Ao alterar o solver, mantenha alinhamento entre backend, UI e configuração em `config/ag.php`; a chave `islands` é usada explicitamente por mais de uma camada.
- Em testes com SQLite, a tabela de professores pode não ter `nome_abreviado`; código e asserts devem aceitar fallback para `nome`.
- O quality gate e a população inicial do solver têm histórico de regressões; valide mudanças nessa área com cuidado e consulte as notas técnicas em `docs/diagnostics/` quando o comportamento degradar.

## Pitfalls

- SQLite é aceitável para testes, mas pode sofrer lock com múltiplos jobs; não assuma comportamento de concorrência equivalente ao MySQL.
- Execuções do algoritmo genético podem deixar cache e métricas antigos afetando novas rodadas; se investigar comportamento estranho, considere limpar cache com `php artisan cache:clear`.
- Há documentação operacional e diagnósticos extensos para constraints e solver; prefira linkar e seguir esses documentos em vez de duplicar regras em novas instruções.

## References

- Convenções estruturais: `docs/architecture/conventions.md`
- Arquitetura do solver: `docs/AG_ARCHITECTURE.md`
- Índice e navegação do solver: `docs/AG_INDEX.md`
- Exemplos práticos do solver: `docs/AG_PRACTICAL_EXAMPLES.md`
- Fluxos visuais do solver: `docs/AG_VISUAL_FLOWS.md`
- Diagnósticos e troubleshooting do solver: `docs/diagnostics/README.md`
- Constraints customizadas: `docs/custom-constraints-spec.md`, `docs/custom-constraints.md` e `docs/prompt_codex_constraints_operacional.md`
