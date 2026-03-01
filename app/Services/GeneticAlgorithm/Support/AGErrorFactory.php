<?php

namespace App\Services\GeneticAlgorithm\Support;

use App\Services\GeneticAlgorithm\Analysis\DTO\FeasibilityReport;

final class AGErrorFactory {
    public static function populationFailure(array $dados): AGError {
        return new AGError(
            codigo: $dados['codigo_diagnostico'] ?? 'AG-010',
            categoria: 'POPULACAO',
            mensagem: $dados['mensagem_diagnostico'] ?? 'Falha na geração da população.',
            severidade: 'CRITICA',
            dados: $dados,
        );
    }

    public static function populationInfeasible(FeasibilityReport $report): AGError {
        return new AGError(
            codigo: 'AG-010',
            categoria: 'DIAGNOSTICO_ESTRUTURAL',
            mensagem: 'População inicial inviável estruturalmente.',
            severidade: 'CRITICA',
            dados: $report->toArray(),
        );
    }

    public static function populationGargalo(FeasibilityReport $report, array $estatisticas): AGError {
        return new AGError(
            codigo: 'AG-011',
            categoria: 'GARGALO_ESTRUTURAL',
            mensagem: 'Configuração estrutural altamente restritiva. População não convergiu.',
            severidade: 'ALTA',
            dados: array_merge(
                $report->toArray(),
                $estatisticas
            )
        );
    }
}
