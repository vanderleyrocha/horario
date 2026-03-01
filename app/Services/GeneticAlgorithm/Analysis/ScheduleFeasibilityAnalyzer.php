<?php

namespace App\Services\GeneticAlgorithm\Analysis;

use App\Services\GeneticAlgorithm\Analysis\DTO\FeasibilityReport;

final class ScheduleFeasibilityAnalyzer {

    public function analisar(array $aulas, int $dias, int $temposPorDia): FeasibilityReport {
        $report = new FeasibilityReport();

        if (empty($aulas)) {
            return $report;
        }

        $capacidadeEntidade = $dias * $temposPorDia;

        $cargaGlobal = 0;
        $cargaPorTurma = [];
        $cargaPorProfessor = [];
        $blocosNecessarios = 0;
        $blocosPossiveis = $dias * max($temposPorDia - 1, 0);

        foreach ($aulas as $aula) {

            $duracao = match ($aula->tipo) {
                'simples' => 1,
                'dupla' => 2,
                'tripla' => 3,
                default => 1,
            };

            $carga = $aula->aulas_semana * $duracao;

            $cargaGlobal += $carga;

            $cargaPorTurma[$aula->turma->id] = ($cargaPorTurma[$aula->turma->id] ?? 0) + $carga;

            $cargaPorProfessor[$aula->professor->id] = ($cargaPorProfessor[$aula->professor->id] ?? 0) + $carga;

            if ($duracao > 1) {
                $blocosNecessarios += $aula->aulas_semana;
            }
        }

        $totalTurmas = count(array_unique(array_map(fn($a) => $a->turma->id, $aulas)));

        $capacidadeGlobal = $totalTurmas * $capacidadeEntidade;

        $report->globalSaturation = ($cargaGlobal / $capacidadeGlobal) * 100;

        /*
        |--------------------------------------------------------------------------
        | Turmas
        |--------------------------------------------------------------------------
        */
        foreach ($cargaPorTurma as $turmaId => $carga) {

            if ($carga > $capacidadeEntidade) {

                $excesso = $carga - $capacidadeEntidade;

                $report->turmaOverloads[] = [
                    'turma_id' => $turmaId,
                    'excesso_aulas' => $excesso,
                    'excesso_percentual' => round(($carga / $capacidadeEntidade) * 100 - 100, 2)
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Professores
        |--------------------------------------------------------------------------
        */
        foreach ($cargaPorProfessor as $professorId => $carga) {

            if ($carga > $capacidadeEntidade) {

                $report->professorOverloads[] = [
                    'professor_id' => $professorId,
                    'excesso_aulas' => $carga - $capacidadeEntidade
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Blocos Contínuos
        |--------------------------------------------------------------------------
        */
        if ($blocosNecessarios > $blocosPossiveis) {

            $report->doubleBlockIssues[] = [
                'necessarios' => $blocosNecessarios,
                'disponiveis' => $blocosPossiveis,
                'deficit' => $blocosNecessarios - $blocosPossiveis
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Determinar viabilidade
        |--------------------------------------------------------------------------
        */
        $report->isFeasible = empty($report->turmaOverloads) && empty($report->professorOverloads) && empty($report->doubleBlockIssues) && $report->globalSaturation <= 100;

        $this->gerarSugestoes($report);

        $this->calcularIndiceRisco($report);

        return $report;
    }

    private function gerarSugestoes(FeasibilityReport $report): void {

        if ($report->globalSaturation > 100) {
            $report->suggestions[] = "Aumentar número de períodos por dia ou reduzir carga global.";
        }

        foreach ($report->turmaOverloads as $turma) {
            $report->suggestions[] = "Reduzir carga da turma ID {$turma['turma_id']} em pelo menos {$turma['excesso_aulas']} aulas.";
        }

        foreach ($report->professorOverloads as $prof) {
            $report->suggestions[] = "Redistribuir {$prof['excesso_aulas']} aulas do professor ID {$prof['professor_id']}.";
        }

        if (!empty($report->doubleBlockIssues)) {
            $report->suggestions[] = "Reduzir número de aulas duplas/triplas ou permitir janelas.";
        }
    }

    private function calcularIndiceRisco(FeasibilityReport $report): void {
        $saturacao = $report->globalSaturation;

        $turmas = count($report->turmaOverloads);
        $professores = count($report->professorOverloads);

        $deficitBlocos = 0;
        foreach ($report->doubleBlockIssues as $b) {
            $deficitBlocos += $b['deficit'];
        }

        $risco = $saturacao + ($turmas * 10) + ($professores * 8) + ($deficitBlocos * 5);

        $report->riskIndex = (int) min(100, round($risco));
    }
}
