<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Risk;

final class RiskClassification
{
    public const MINIMO = 'MINIMO';
    public const BAIXO = 'BAIXO';
    public const MODERADO = 'MODERADO';
    public const ALTO = 'ALTO';
    public const CRITICO = 'CRITICO';

    public function classify(int $riskIndex): string
    {
        return match (true) {
            $riskIndex >= 80 => self::CRITICO,
            $riskIndex >= 60 => self::ALTO,
            $riskIndex >= 40 => self::MODERADO,
            $riskIndex >= 20 => self::BAIXO,
            default => self::MINIMO,
        };
    }
}
