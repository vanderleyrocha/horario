<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

/**
 * Perfil de ilha para o modelo de ilhas do algoritmo genético.
 *
 * Cada perfil define faixas de alpha GRASP e parâmetros de mutação distintos,
 * promovendo diversidade estrutural entre ilhas desde a população inicial.
 */
enum IslandProfile: string
{
    /** Ilha greedy — alpha baixo, mutação moderada. Favorece convergência rápida. */
    case Conservative = 'conservative';

    /** Ilha equilibrada — configuração padrão balanceando exploração e exploitação. */
    case Balanced = 'balanced';

    /** Ilha exploratória — alpha alto, mutação elevada. Favorece diversidade. */
    case Exploratory = 'exploratory';

    // ─── Limites de alpha GRASP ───

    public function graspAlphaMin(): float
    {
        return match ($this) {
            self::Conservative => 0.15,
            self::Balanced => 0.175,
            self::Exploratory => 0.21,
        };
    }

    public function graspAlphaMax(): float
    {
        return match ($this) {
            self::Conservative => 0.185,
            self::Balanced => 0.215,
            self::Exploratory => 0.25,
        };
    }

    // ─── Parâmetros do AdaptiveMutationController ───

    public function mutationBaseRate(): float
    {
        return match ($this) {
            self::Conservative => 0.015,
            self::Balanced => 0.02,
            self::Exploratory => 0.03,
        };
    }

    public function mutationAmplification(): float
    {
        return match ($this) {
            self::Conservative => 0.20,
            self::Balanced => 0.25,
            self::Exploratory => 0.30,
        };
    }

    public function mutationMaxRate(): float
    {
        return match ($this) {
            self::Conservative => 0.28,
            self::Balanced => 0.35,
            self::Exploratory => 0.42,
        };
    }

    // ─── Label descritivo ───

    public function label(): string
    {
        return match ($this) {
            self::Conservative => 'conservadora (alpha baixo, mutacao moderada)',
            self::Balanced => 'equilibrada (alpha medio, mutacao padrao)',
            self::Exploratory => 'exploratoria (alpha alto, mutacao elevada)',
        };
    }
}
