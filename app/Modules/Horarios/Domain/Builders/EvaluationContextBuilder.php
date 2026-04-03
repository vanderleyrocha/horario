<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Builders;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class EvaluationContextBuilder
{
    // 🔧 PRIORIDADE 10: Cache de dados preferenciais (dias/tempos) por ScheduleData
    /**
     * @var array<int, array{diasPreferidos: array, temposPreferidos: array}>
     */
    private array $preferenceCache = [];

    public function build(Cromossomo $cromossomo, ScheduleData $data): EvaluationContext
    {
        // 🔧 PRIORIDADE 10: Reutilizar preferências cacheadas para esse ScheduleData
        $dataHash = spl_object_id($data);

        if (! isset($this->preferenceCache[$dataHash])) {
            // Primeira vez: construir e cachear
            $diasPreferidos = [];
            $temposPreferidos = [];

            foreach ($data->lessons as $lesson) {
                $diasPreferidos[$lesson->id] = $lesson->preferredDays;
                $temposPreferidos[$lesson->id] = $lesson->preferredPeriods;
            }

            $this->preferenceCache[$dataHash] = [
                'diasPreferidos' => $diasPreferidos,
                'temposPreferidos' => $temposPreferidos,
            ];
        }

        // Reutilizar do cache
        $cached = $this->preferenceCache[$dataHash];

        return new EvaluationContext(
            cromossomo: $cromossomo,
            data: $data,
            cargaEsperada: $data->expectedLoadByLesson,
            diasPreferidos: $cached['diasPreferidos'],
            temposPreferidos: $cached['temposPreferidos'],
        );
    }

    /**
     * 🔧 PRIORIDADE 10: Limpar cache (útil entre execuções)
     */
    public function clearCache(): void
    {
        $this->preferenceCache = [];
    }
}
