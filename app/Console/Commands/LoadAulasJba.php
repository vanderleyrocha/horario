<?php

namespace App\Console\Commands;

use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\Professor;
use App\Models\Turma;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadAulasJba extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aulas:load-jba
        {horario_id : ID do horário a ser referenciado pelas aulas}
        {escola_id : ID da escola}
        {ano : Ano}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ler aulas do banco de dados JBA';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $horario_id = $this->argument('horario_id');
        $escola_id = $this->argument('escola_id');
        $ano = $this->argument('ano');


        $turno = [1 => "Manhã", 2 => "Tarde", 3 => "Noite", 4 => "Integral"];

        $turmas_r = DB::connection('jba')
            ->table('turmas')
            ->where([['escola_id', $escola_id], ['ano', $ano]])
            ->get()
            ->keyBy('id')
        ;

        $data = DB::connection('jba')
            ->table('disciplina_serie')
            ->select(['disciplina_id', 'serie_id', 'ch_semanal'])
            ->where([['escola_id', $escola_id], ['ano', $ano]])
            ->get()
        ;

        $ds = [];

        foreach ($data as $d) {
            $ds[$d->serie_id][$d->disciplina_id] = $d->ch_semanal;
        }

        $professor = DB::connection('jba')
            ->table('disciplina_professor AS dp')
            ->join("servidores AS s", "s.id", "=", "dp.professor_id")
            ->select(['s.id', 's.nome', 's.nome_abreviado', 's.email'])
            ->where([['dp.escola_id', $escola_id], ['dp.ano', $ano]])
            ->get()
            ->keyBy('id')
        ;

        $disciplina = DB::connection('jba')
            ->table('disciplina_professor AS dp')
            ->join("disciplinas AS d", "d.id", "=", "dp.disciplina_id")
            ->select(['d.id', 'd.nome', 'd.nome_abreviado'])
            ->where([['dp.escola_id', $escola_id], ['dp.ano', $ano]])
            ->get()
            ->keyBy('id')
        ;

        $el = DB::connection('jba')
            ->table('disciplina_serie AS ds')
            ->join("disciplinas AS d", "d.id", "=", "ds.disciplina_id")
            ->join("turmas AS t", "t.serie_id", "=", "ds.serie_id")
            ->select('t.id')
            ->where([['ds.escola_id', $escola_id], ['ds.ano', $ano], ['t.escola_id', $escola_id], ['t.ano', $ano], ['d.tipo', 'Eletiva']])
            ->pluck("id")
        ;

        $tel = [];

        foreach ($el as $e) {
            $tel[$e] = $e;
        }

        foreach ($turmas_r as $t) {
            Turma::updateOrCreate(["id" => $t->id], [
                "id" => $t->id,
                "nome" => $t->nome,
                "codigo" => $t->nome_abreviado,
                "serie" => $t->serie_id,
                "turno" => $turno[$t->turno],
                "ano" => $t->ano,
                "numero_alunos" => 40,
                "ativa" => 1,
            ]);
        }

        foreach ($professor as $p) {
            Professor::updateOrCreate(["id" => $p->id], [
                "id" => $p->id,
                "nome" => $p->nome,
                "nome_abreviado" => $p->nome_abreviado,
                "email" => $p->email,
                "carga_horaria_maxima" => 28,
                "ativo" => 1,
            ]);
        }

        foreach ($disciplina as $d) {
            Disciplina::updateOrCreate(["id" => $d->id,], [
                "id" => $d->id,
                "nome" => $d->nome,
                "codigo" => $d->nome_abreviado,
                "descricao" => "Disciplina de " . $d->nome,
                "carga_horaria_semanal" => 1,
                "cor" => '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT),
                "ativa" => 1,
            ]);
        }

        $pt = DB::connection('jba')
            ->table('disciplina_professor AS dp')
            ->join("professor_turma AS pt", "pt.disciplina_professor_id", "=", "dp.id")
            ->select(['dp.disciplina_id', 'dp.professor_id', 'pt.turma_id', 'pt.eletiva_professor_id'])
            ->where([['escola_id', $escola_id], ['ano', $ano]])
            ->get()
        ;

        $count = 0;
        foreach ($pt as $t) {

            if ($t->turma_id) {
                $turma_id = $t->turma_id;
            } else {
                $turma_id = array_shift($tel);
            }
            Aula::updateOrCreate([
                    "horario_id" => $horario_id,
                    "professor_id" => $t->professor_id,
                    "disciplina_id" => $t->disciplina_id,
                    "turma_id" => $turma_id,
                ], [
                    "horario_id" => $horario_id,
                    "professor_id" => $t->professor_id,
                    "disciplina_id" => $t->disciplina_id,
                    "turma_id" => $turma_id,
                    "aulas_semana" => $ds[$turmas_r[$turma_id]->serie_id][$t->disciplina_id],
                    "tipo" => "simples",
                    "aulas_consecutivas" => 0,
                    "max_aulas_dia" => 2,
                    "min_intervalo_dias" => 0,
                    "ativa" => 1,
            ]);
            $count++;
        }
        echo $count . " aulas inseridas";
    }
}
