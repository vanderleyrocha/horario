<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Builders;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class EvaluationContextBuilder
{
    public function build(Cromossomo $cromossomo, ScheduleData $data): EvaluationContext
    {
        $diasPreferidos = [];
        $temposPreferidos = [];

        foreach ($data->lessons as $lesson) {
            $diasPreferidos[$lesson->id] = $lesson->preferredDays;
            $temposPreferidos[$lesson->id] = $lesson->preferredPeriods;
        }

        return new EvaluationContext(
            cromossomo: $cromossomo,
            data: $data,
            cargaEsperada: $data->expectedLoadByLesson,
            diasPreferidos: $diasPreferidos,
            temposPreferidos: $temposPreferidos,
        );
    }
}
