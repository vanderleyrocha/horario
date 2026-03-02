<?php

namespace App\Modules\AG\Domain\Analysis;

use App\Modules\AG\Domain\Analysis\DTO\FeasibilityReport;

final class ScheduleFeasibilityAnalyzer {

    public function analisar(array $aulas, int $dias, int $temposPorDia): FeasibilityReport {
        if (empty($aulas)) {
            return new FeasibilityReport(isFeasible: true, globalSaturation: 0.0);
        }

        $capacidadePorTurma = $dias * $temposPorDia;

        $cargaGlobal = 0;
        $cargaPorTurma = [];
        $cargaPorProfessor = [];
        $blocosNecessarios = 0;

        foreach ($aulas as $aula) {

            $duracao = match ($aula->tipo) {
                'simples' => 1,
                'dupla' => 2,
                'tripla' => 3,
                default => 1,
            };

            $carga = $aula->aulas_semana * $duracao;

            $cargaGlobal += $carga;

            $cargaPorTurma[$aula->turma->id] =
                ($cargaPorTurma[$aula->turma->id] ?? 0) + $carga;

            $cargaPorProfessor[$aula->professor->id] =
                ($cargaPorProfessor[$aula->professor->id] ?? 0) + $carga;

            if ($duracao > 1) {
                $blocosNecessarios += $aula->aulas_semana;
            }
        }

        $totalTurmas = count(array_unique(
            array_map(fn($a) => $a->turma->id, $aulas)
        ));

        if ($totalTurmas === 0) {
            return new FeasibilityReport(isFeasible: true, globalSaturation: 0.0);
        }

        $capacidadeGlobal = $totalTurmas * $capacidadePorTurma;

        $globalSaturation = $capacidadeGlobal > 0 ? ($cargaGlobal / $capacidadeGlobal) * 100 : 0.0;

        /* ============================================================
         | TURMAS
         ============================================================ */

        $turmaOverloads = [];

        foreach ($cargaPorTurma as $turmaId => $carga) {

            if ($carga > $capacidadePorTurma) {

                $excedente = $carga - $capacidadePorTurma;

                $turmaOverloads[$turmaId] = [
                    'carga_total' => $carga,
                    'capacidade_maxima' => $capacidadePorTurma,
                    'excedente' => $excedente,
                    'percentual' =>
                    round(($carga / $capacidadePorTurma) * 100, 2),
                ];
            }
        }

        /* ============================================================
         | PROFESSORES (somente indicador de risco)
         ============================================================ */

        $professorOverloads = [];

        $capacidadeProfessor = $dias * $temposPorDia;

        foreach ($cargaPorProfessor as $professorId => $carga) {

            if ($carga > $capacidadeProfessor) {

                $excedente = $carga - $capacidadeProfessor;

                $professorOverloads[$professorId] = [
                    'carga_total' => $carga,
                    'capacidade_maxima' => $capacidadeProfessor,
                    'excedente' => $excedente,
                    'percentual' =>
                    round(($carga / $capacidadeProfessor) * 100, 2),
                ];
            }
        }

        /* ============================================================
         | BLOCOS CONTÍNUOS
         ============================================================ */

        $blocosPossiveis = $totalTurmas * $dias * max($temposPorDia - 1, 0);

        $doubleBlockIssues = [];

        if ($blocosNecessarios > $blocosPossiveis) {

            $doubleBlockIssues[] = [
                'duracao' => 'dupla_ou_maior',
                'blocos_disponiveis' => $blocosPossiveis,
                'necessarios' => $blocosNecessarios,
                'deficit' =>
                $blocosNecessarios - $blocosPossiveis,
            ];
        }

        /* ============================================================
         | DETERMINAR VIABILIDADE
         ============================================================ */

        $isFeasible = empty($turmaOverloads) && empty($doubleBlockIssues) && $globalSaturation <= 100;

        /* ============================================================
         | SUGESTÕES
         ============================================================ */

        $suggestions = $this->gerarSugestoes($globalSaturation, $turmaOverloads, $professorOverloads, $doubleBlockIssues);

        /* ============================================================
         | RISK INDEX
         ============================================================ */

        $riskIndex = $this->calcularIndiceRisco($globalSaturation, $turmaOverloads, $professorOverloads, $doubleBlockIssues);

        return new FeasibilityReport(
            isFeasible: $isFeasible,
            globalSaturation: $globalSaturation,
            turmaOverloads: $turmaOverloads,
            professorOverloads: $professorOverloads,
            doubleBlockIssues: $doubleBlockIssues,
            structuralBottlenecks: [],
            suggestions: $suggestions,
            riskIndex: $riskIndex
        );
    }

    /* ============================================================
     | SUGESTÕES
     ============================================================ */

    private function gerarSugestoes(float $saturacao, array $turmas, array $professores, array $blocos): array {

        $sugestoes = [];

        if ($saturacao > 100) {
            $sugestoes[] =
                "Aumentar períodos por dia ou reduzir carga global.";
        }

        foreach ($turmas as $id => $dados) {
            $sugestoes[] =
                "Reduzir carga da turma {$id} em {$dados['excedente']} tempos.";
        }

        foreach ($professores as $id => $dados) {
            $sugestoes[] =
                "Redistribuir {$dados['excedente']} tempos do professor {$id}.";
        }

        if (!empty($blocos)) {
            $sugestoes[] =
                "Reduzir aulas duplas/triplas ou permitir janelas.";
        }

        return $sugestoes;
    }

    /* ============================================================
     | RISCO
     ============================================================ */

    private function calcularIndiceRisco(float $saturacao, array $turmas, array $professores, array $blocos): int {

        $risco = ($saturacao * 0.5) + (count($turmas) * 5) + (count($professores) * 4) + (count($blocos) * 6);

        return (int) min(100, round($risco));
    }
}
