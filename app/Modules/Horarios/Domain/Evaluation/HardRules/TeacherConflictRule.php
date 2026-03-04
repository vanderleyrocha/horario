<?php

namespace App\Modules\Horarios\Domain\Evaluation\HardRules;

use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class TeacherConflictRule implements HardRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;

        foreach ($context->professorIndex() as $profId => $dias) {
            foreach ($dias as $dia => $tempos) {

                // Se houver mais de um gene no mesmo slot, já seria conflito
                // Como o Cromossomo sobrescreve boolean, precisamos detectar via genes

                $ocupacao = [];

                foreach ($context->genes() as $gene) {

                    if ($gene->professorId() !== $profId) {
                        continue;
                    }

                    for ($i = 0; $i < $gene->duracaoTempos(); $i++) {

                        $tempo = $gene->periodoDia() + $i;

                        $slot = $dia . '_' . $tempo;

                        if (isset($ocupacao[$slot])) {
                            $penalty++;
                        }

                        $ocupacao[$slot] = true;
                    }
                }
            }
        }

        return new RuleResult($penalty, self::class);
    }

    public function isHard(): bool {
        return true;
    }
}
