<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Progress;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Support\AGError;
use Illuminate\Support\Facades\Cache;

final class CacheProgressReporter implements ProgressReporterInterface
{
    private string $cacheKey;

    private int $ttlMinutes;

    public function __construct(int $horarioId, int $ttlMinutes = 30)
    {
        $this->cacheKey = "horario_geracao_{$horarioId}";
        $this->ttlMinutes = $ttlMinutes;
    }

    public function report(array $data): void
    {
        $payload = array_merge(['timestamp' => now()->toDateTimeString()], $data);

        Cache::put(
            $this->cacheKey,
            $payload,
            now()->addMinutes($this->ttlMinutes)
        );

        $this->storeExecutionScopedPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function reportError(AGError $error, ?int $executionId = null, array $context = []): void
    {
        $payload = array_merge([
            'status' => 'erro',
            'execution_id' => $executionId,
            'erro' => $error->toArray(),
            'timestamp' => now()->toDateTimeString(),
        ], $context);

        Cache::put(
            $this->cacheKey,
            $payload,
            now()->addMinutes($this->ttlMinutes)
        );

        $this->storeExecutionScopedPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function reportCompleted(float $bestFitness, ?int $executionId = null, array $context = []): void
    {
        $payload = array_merge($context, [
            'status' => 'concluido',
            'phase' => 'terminal',
            'execution_status' => 'finished',
            'execution_id' => $executionId,
            'percentual' => 100,
            'melhor_fitness' => round($bestFitness, 4),
            'timestamp' => now()->toDateTimeString(),
        ]);

        Cache::put(
            $this->cacheKey,
            $payload,
            now()->addMinutes($this->ttlMinutes)
        );

        $this->storeExecutionScopedPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function reportCancelled(?int $executionId = null, array $context = []): void
    {
        $payload = array_merge($context, [
            'status' => 'cancelled',
            'phase' => 'terminal',
            'execution_status' => 'cancelled',
            'execution_id' => $executionId,
            'timestamp' => now()->toDateTimeString(),
        ]);

        Cache::put(
            $this->cacheKey,
            $payload,
            now()->addMinutes($this->ttlMinutes)
        );

        $this->storeExecutionScopedPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function reportFailed(?int $executionId = null, array $context = []): void
    {
        $payload = array_merge($context, [
            'status' => 'failed',
            'phase' => 'terminal',
            'execution_status' => 'failed',
            'execution_id' => $executionId,
            'timestamp' => now()->toDateTimeString(),
        ]);

        Cache::put(
            $this->cacheKey,
            $payload,
            now()->addMinutes($this->ttlMinutes)
        );

        $this->storeExecutionScopedPayload($payload);
    }

    public function clear(): void
    {
        Cache::forget($this->cacheKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeExecutionScopedPayload(array $payload): void
    {
        $executionId = (int) ($payload['execution_id'] ?? 0);

        if ($executionId <= 0) {
            return;
        }

        Cache::put(
            "ga_execution_progress_{$executionId}",
            $payload,
            now()->addMinutes($this->ttlMinutes)
        );
    }
}
