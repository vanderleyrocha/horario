<?php

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS;

class RouletteWheelSelector {
    public function select(array $operators, array $stats) {
        $total = 0;

        foreach ($operators as $op) {
            $total += $stats[spl_object_id($op)]->weight;
        }

        $rand = mt_rand() / mt_getrandmax() * $total;

        $sum = 0;

        foreach ($operators as $op) {

            $sum += $stats[spl_object_id($op)]->weight;

            if ($rand <= $sum) {
                return $op;
            }
        }

        return $operators[array_key_first($operators)];
    }
}
