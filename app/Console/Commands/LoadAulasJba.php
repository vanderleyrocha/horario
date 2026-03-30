<?php

namespace App\Console\Commands;

use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\Professor;
use App\Models\Turma;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class LoadAulasJba extends Command
{
    protected $signature = 'aulas:load-jba
        {horario_id : ID do horário a ser referenciado pelas aulas}
        {escola_id : ID da escola}
        {ano : Ano}';

    protected $description = 'Lê aulas do banco JBA com validação estrutural e persistência transacional';

    public function handle(): int
    {
        $horarioId = (int) $this->argument('horario_id');
        $escolaId = (int) $this->argument('escola_id');
        $ano = (int) $this->argument('ano');

        $turnos = [
            1 => 'Manhã',
            2 => 'Tarde',
            3 => 'Noite',
            4 => 'Integral',
        ];

        try {
            $this->info("Iniciando carga JBA | horário={$horarioId} escola={$escolaId} ano={$ano}");

            $turmas = DB::connection('jba')
                ->table('turmas')
                ->where([
                    ['escola_id', '=', $escolaId],
                    ['ano', '=', $ano],
                ])
                ->get()
                ->keyBy('id');

            $disciplinasSerie = DB::connection('jba')
                ->table('disciplina_serie')
                ->select(['disciplina_id', 'serie_id', 'ch_semanal'])
                ->where([
                    ['escola_id', '=', $escolaId],
                    ['ano', '=', $ano],
                ])
                ->get();

            $cargaHoraria = [];
            foreach ($disciplinasSerie as $item) {
                $cargaHoraria[$item->serie_id][$item->disciplina_id] = (int) $item->ch_semanal;
            }

            $professores = DB::connection('jba')
                ->table('disciplina_professor as dp')
                ->join('servidores as s', 's.id', '=', 'dp.professor_id')
                ->select(['s.id', 's.nome', 's.nome_abreviado', 's.email'])
                ->where([
                    ['dp.escola_id', '=', $escolaId],
                    ['dp.ano', '=', $ano],
                ])
                ->distinct()
                ->get()
                ->keyBy('id');

            $disciplinas = DB::connection('jba')
                ->table('disciplina_professor as dp')
                ->join('disciplinas as d', 'd.id', '=', 'dp.disciplina_id')
                ->select(['d.id', 'd.nome', 'd.nome_abreviado'])
                ->where([
                    ['dp.escola_id', '=', $escolaId],
                    ['dp.ano', '=', $ano],
                ])
                ->distinct()
                ->get()
                ->keyBy('id');

            $turmasEletivas = DB::connection('jba')
                ->table('disciplina_serie as ds')
                ->join('disciplinas as d', 'd.id', '=', 'ds.disciplina_id')
                ->join('turmas as t', 't.serie_id', '=', 'ds.serie_id')
                ->select('t.id')
                ->where([
                    ['ds.escola_id', '=', $escolaId],
                    ['ds.ano', '=', $ano],
                    ['t.escola_id', '=', $escolaId],
                    ['t.ano', '=', $ano],
                    ['d.tipo', '=', 'Eletiva'],
                ])
                ->pluck('t.id')
                ->values();

            $aulasOrigem = DB::connection('jba')
                ->table('disciplina_professor as dp')
                ->join('professor_turma as pt', 'pt.disciplina_professor_id', '=', 'dp.id')
                ->select([
                    'dp.id as disciplina_professor_id',
                    'dp.disciplina_id',
                    'dp.professor_id',
                    'pt.turma_id',
                    'pt.eletiva_professor_id',
                ])
                ->where([
                    ['dp.escola_id', '=', $escolaId],
                    ['dp.ano', '=', $ano],
                ])
                ->get();

            if ($aulasOrigem->isEmpty()) {
                $this->warn('Nenhum vínculo disciplina/professor/turma foi encontrado na base JBA.');
                return self::SUCCESS;
            }

            $diagnostico = $this->preValidar(
                turmas: $turmas,
                professores: $professores,
                disciplinas: $disciplinas,
                turmasEletivas: $turmasEletivas,
                aulasOrigem: $aulasOrigem,
                cargaHoraria: $cargaHoraria
            );

            if ($diagnostico['has_errors']) {
                $this->reportarInconsistencias($diagnostico['errors'], $diagnostico['warnings']);
                $this->error('Carga abortada por inconsistências estruturais. Nada foi persistido.');
                return self::FAILURE;
            }

            $this->reportarInconsistencias([], $diagnostico['warnings']);

            DB::transaction(function () use (
                $horarioId,
                $turnos,
                $turmas,
                $professores,
                $disciplinas,
                $aulasOrigem,
                $turmasEletivas,
                $cargaHoraria
            ) {
                foreach ($turmas as $turma) {
                    Turma::updateOrCreate(
                        ['id' => $turma->id],
                        [
                            'id' => $turma->id,
                            'nome' => $turma->nome,
                            'codigo' => $turma->nome_abreviado,
                            'serie' => $turma->serie_id,
                            'turno' => $turnos[$turma->turno] ?? 'Indefinido',
                            'ano' => $turma->ano,
                            'numero_alunos' => 40,
                            'ativa' => 1,
                        ]
                    );
                }

                foreach ($professores as $professor) {
                    Professor::updateOrCreate(
                        ['id' => $professor->id],
                        [
                            'id' => $professor->id,
                            'nome' => $professor->nome,
                            'nome_abreviado' => $professor->nome_abreviado,
                            'email' => $professor->email,
                            'carga_horaria_maxima' => 28,
                            'ativo' => 1,
                        ]
                    );
                }

                foreach ($disciplinas as $disciplina) {
                    Disciplina::updateOrCreate(
                        ['id' => $disciplina->id],
                        [
                            'id' => $disciplina->id,
                            'nome' => $disciplina->nome,
                            'codigo' => $disciplina->nome_abreviado,
                            'descricao' => 'Disciplina de ' . $disciplina->nome,
                            'carga_horaria_semanal' => 1,
                            'cor' => $this->generateRandomColor(),
                            'ativa' => 1,
                        ]
                    );
                }

                $filaTurmasEletivas = $turmasEletivas->values()->all();
                $inseridas = 0;

                foreach ($aulasOrigem as $registro) {
                    $turmaId = $registro->turma_id ?: array_shift($filaTurmasEletivas);

                    if (! $turmaId) {
                        throw new \RuntimeException(
                            "Não há turma disponível para alocar a eletiva do professor {$registro->professor_id}, disciplina {$registro->disciplina_id}."
                        );
                    }

                    $turma = $turmas->get($turmaId);

                    if (! $turma) {
                        throw new \RuntimeException(
                            "Turma {$turmaId} não encontrada no conjunto de turmas carregadas."
                        );
                    }

                    $aulasSemana = $cargaHoraria[$turma->serie_id][$registro->disciplina_id] ?? null;

                    if ($aulasSemana === null) {
                        throw new \RuntimeException(
                            "Carga horária semanal não encontrada para serie_id={$turma->serie_id} e disciplina_id={$registro->disciplina_id}."
                        );
                    }

                    Aula::updateOrCreate(
                        [
                            'horario_id' => $horarioId,
                            'professor_id' => $registro->professor_id,
                            'disciplina_id' => $registro->disciplina_id,
                            'turma_id' => $turmaId,
                        ],
                        [
                            'horario_id' => $horarioId,
                            'professor_id' => $registro->professor_id,
                            'disciplina_id' => $registro->disciplina_id,
                            'turma_id' => $turmaId,
                            'aulas_semana' => $aulasSemana,
                            'tipo' => 'simples',
                            'aulas_consecutivas' => 0,
                            'max_aulas_dia' => 2,
                            'min_intervalo_dias' => 0,
                            'ativa' => 1,
                        ]
                    );

                    $inseridas++;
                }

                $this->info("Persistência concluída com sucesso. {$inseridas} aulas processadas.");
            });

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Falha controlada durante a carga.');
            $this->error('Tipo: ' . $e::class);
            $this->error('Mensagem: ' . $e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * @return array{
     *     has_errors: bool,
     *     errors: array<int, string>,
     *     warnings: array<int, string>
     * }
     */
    private function preValidar(
        Collection $turmas,
        Collection $professores,
        Collection $disciplinas,
        Collection $turmasEletivas,
        Collection $aulasOrigem,
        array $cargaHoraria
    ): array {
        $errors = [];
        $warnings = [];

        $professoresIds = $professores->keys()->map(fn ($id) => (int) $id)->all();
        $disciplinasIds = $disciplinas->keys()->map(fn ($id) => (int) $id)->all();
        $turmasIds = $turmas->keys()->map(fn ($id) => (int) $id)->all();

        $professoresSet = array_fill_keys($professoresIds, true);
        $disciplinasSet = array_fill_keys($disciplinasIds, true);
        $turmasSet = array_fill_keys($turmasIds, true);

        $faltamProfessores = [];
        $faltamDisciplinas = [];
        $faltamTurmas = [];
        $faltamCargaHoraria = [];
        $eletivasSemTurma = 0;

        $totalSemTurma = $aulasOrigem->whereNull('turma_id')->count();
        $totalTurmasEletivas = $turmasEletivas->count();

        foreach ($aulasOrigem as $registro) {
            $professorId = (int) $registro->professor_id;
            $disciplinaId = (int) $registro->disciplina_id;
            $turmaId = $registro->turma_id !== null ? (int) $registro->turma_id : null;

            if (! isset($professoresSet[$professorId])) {
                $faltamProfessores[$professorId] = $professorId;
            }

            if (! isset($disciplinasSet[$disciplinaId])) {
                $faltamDisciplinas[$disciplinaId] = $disciplinaId;
            }

            if ($turmaId !== null) {
                if (! isset($turmasSet[$turmaId])) {
                    $faltamTurmas[$turmaId] = $turmaId;
                    continue;
                }

                $serieId = $turmas->get($turmaId)->serie_id ?? null;
                if ($serieId === null || ! isset($cargaHoraria[$serieId][$disciplinaId])) {
                    $faltamCargaHoraria[] = "turma_id={$turmaId}, serie_id={$serieId}, disciplina_id={$disciplinaId}";
                }
            } else {
                $eletivasSemTurma++;
            }
        }

        if (! empty($faltamProfessores)) {
            $errors[] = 'Professores referenciados em professor_turma/disciplinas e ausentes no conjunto carregado: ' .
                implode(', ', array_values($faltamProfessores));
        }

        if (! empty($faltamDisciplinas)) {
            $errors[] = 'Disciplinas referenciadas e ausentes no conjunto carregado: ' .
                implode(', ', array_values($faltamDisciplinas));
        }

        if (! empty($faltamTurmas)) {
            $errors[] = 'Turmas referenciadas e ausentes no conjunto carregado: ' .
                implode(', ', array_values($faltamTurmas));
        }

        if (! empty($faltamCargaHoraria)) {
            $errors[] = 'Combinações sem carga horária semanal em disciplina_serie: ' .
                implode(' | ', $faltamCargaHoraria);
        }

        if ($totalSemTurma > 0 && $totalTurmasEletivas === 0) {
            $errors[] = "Existem {$totalSemTurma} registros sem turma_id e nenhuma turma elegível para eletiva.";
        }

        if ($totalSemTurma > $totalTurmasEletivas) {
            $errors[] = "Existem {$totalSemTurma} registros sem turma_id, mas apenas {$totalTurmasEletivas} turmas elegíveis para eletiva.";
        }

        if ($totalSemTurma > 0 && $totalSemTurma <= $totalTurmasEletivas) {
            $warnings[] = "Foram encontrados {$totalSemTurma} vínculos sem turma_id. Será usada alocação sequencial com a fila de turmas eletivas.";
        }

        if ($turmas->isEmpty()) {
            $errors[] = 'Nenhuma turma encontrada para a escola/ano informados.';
        }

        if ($professores->isEmpty()) {
            $errors[] = 'Nenhum professor carregado a partir de disciplina_professor + servidores.';
        }

        if ($disciplinas->isEmpty()) {
            $errors[] = 'Nenhuma disciplina carregada a partir de disciplina_professor + disciplinas.';
        }

        return [
            'has_errors' => ! empty($errors),
            'errors' => array_values($errors),
            'warnings' => array_values($warnings),
        ];
    }

    private function reportarInconsistencias(array $errors = [], array $warnings = []): void
    {
        if (! empty($warnings)) {
            $this->newLine();
            $this->warn('Avisos de pré-checagem:');
            foreach ($warnings as $warning) {
                $this->line(" - {$warning}");
            }
        }

        if (! empty($errors)) {
            $this->newLine();
            $this->error('Inconsistências encontradas:');
            foreach ($errors as $error) {
                $this->line(" - {$error}");
            }
        }
    }

    private function generateRandomColor(): string
    {
        return '#' . str_pad(dechex(random_int(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }
}
