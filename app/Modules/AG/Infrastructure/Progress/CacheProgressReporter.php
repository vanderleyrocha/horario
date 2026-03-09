<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Progress;

use Illuminate\Support\Facades\Cache;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Support\AGError;

final class CacheProgressReporter implements ProgressReporterInterface {
    private string $cacheKey;
    private int $ttlMinutes;

    public function __construct(int $horarioId, int $ttlMinutes = 30) {
        $this->cacheKey = "horario_geracao_{$horarioId}";
        $this->ttlMinutes = $ttlMinutes;
    }

    public function report(array $data): void {
        $payload = array_merge(['timestamp' => now()->toDateTimeString()], $data);

        Cache::put(
            $this->cacheKey,
            $payload,
            now()->addMinutes($this->ttlMinutes)
        );
    }

    public function reportError(AGError $error): void {
        Cache::put(
            $this->cacheKey,
            [
                'status' => 'erro',
                'erro' => $error->toArray(),
                'timestamp' => now()->toDateTimeString(),
            ],
            now()->addMinutes($this->ttlMinutes)
        );
    }

    public function reportCompleted(float $bestFitness): void {
        Cache::put(
            $this->cacheKey,
            [
                'status' => 'concluido',
                'percentual' => 100,
                'melhor_fitness' => round($bestFitness, 4),
                'timestamp' => now()->toDateTimeString(),
            ],
            now()->addMinutes($this->ttlMinutes)
        );
    }

    public function clear(): void {
        Cache::forget($this->cacheKey);
    }
}
