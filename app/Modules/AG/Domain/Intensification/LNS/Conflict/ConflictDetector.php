<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Conflict;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class ConflictDetector
{
    public function detect(Cromossomo $cromossomo): ConflictSet
    {
        $conflicts = [];

        $profIndex = $cromossomo->professorPeriodoIndex();
        $turmaIndex = $cromossomo->turmaPeriodoIndex();

        /*
        |--------------------------------------------------------------------------
        | Conflitos de professor
        |--------------------------------------------------------------------------
        */

        foreach ($profIndex as $prof => $dias) {

            foreach ($dias as $dia => $periodos) {

                foreach ($periodos as $periodo => $genes) {

                    if (count($genes) <= 1) {
                        continue;
                    }

                    foreach ($genes as $geneIndex) {
                        $conflicts[$geneIndex] = new Conflict($geneIndex);
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Conflitos de turma
        |--------------------------------------------------------------------------
        */

        foreach ($turmaIndex as $turma => $dias) {

            foreach ($dias as $dia => $periodos) {

                foreach ($periodos as $periodo => $genes) {

                    if (count($genes) <= 1) {
                        continue;
                    }

                    foreach ($genes as $geneIndex) {
                        $conflicts[$geneIndex] = new Conflict($geneIndex);
                    }
                }
            }
        }

        return new ConflictSet(array_values($conflicts));
    }
}
