<?php

declare(strict_types=1);

namespace App\Modules\AG\Support;

use App\Modules\Horarios\Domain\Analysis\DTO\FeasibilityReport;

final class AGErrorFactory
{
    /* ============================================================
     |  AG-010 – INVIABILIDADE ESTRUTURAL
     ============================================================ */

    public static function populationInfeasible(FeasibilityReport $report): AGError
    {

        return new AGError(
            codigo: 'AG-010',
            categoria: 'DIAGNOSTICO_ESTRUTURAL',
            mensagem: 'População inicial inviável estruturalmente.',
            severidade: self::severityFromRisk($report),
            dados: [
                'resumo' => self::buildResumo($report),
                'diagnostico' => $report->toArray(),
            ],
        );
    }

    /* ============================================================
     |  AG-011 – GARGALO ESTRUTURAL (convergência falhou)
     ============================================================ */

    public static function populationGargalo(
        FeasibilityReport $report,
        array $estatisticas
    ): AGError {

        return new AGError(
            codigo: 'AG-011',
            categoria: 'GARGALO_ESTRUTURAL',
            mensagem: 'Configuração estrutural altamente restritiva. População não convergiu.',
            severidade: self::severityFromRisk($report),
            dados: [
                'resumo' => self::buildResumo($report),
                'diagnostico' => $report->toArray(),
                'estatisticas_execucao' => $estatisticas,
            ],
        );
    }

    /* ============================================================
     |  AG-012 – FALHA TÉCNICA NA GERAÇÃO
     ============================================================ */

    public static function populationFailure(
        array $dados
    ): AGError {

        return new AGError(
            codigo: $dados['codigo_diagnostico'] ?? 'AG-012',
            categoria: 'FALHA_TECNICA',
            mensagem: $dados['mensagem_diagnostico']
                ?? 'Falha técnica na geração da população inicial.',
            severidade: 'CRITICA',
            dados: $dados,
        );
    }

    /* ============================================================
     |  MÉTODOS AUXILIARES
     ============================================================ */

    private static function severityFromRisk(
        FeasibilityReport $report
    ): string {

        return match ($report->riskLevel()) {
            'CRITICO' => 'CRITICA',
            'ALTO' => 'ALTA',
            'MODERADO' => 'MEDIA',
            default => 'BAIXA',
        };
    }

    private static function buildResumo(
        FeasibilityReport $report
    ): array {

        return [
            'saturacao_global' => round($report->globalSaturation(), 2),
            'nivel_risco' => $report->riskLevel(),
            'indice_risco' => $report->riskIndex(),
            'turmas_criticas' => count($report->turmaOverloads()),
            'professores_criticos' => count($report->professorOverloads()),
            'aulas_duplas_problema' => count($report->doubleBlockIssues()),
        ];
    }
}
