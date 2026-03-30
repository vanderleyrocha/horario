<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Problem;

use App\Models\ScheduleExecution;
use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\AG\Support\Exceptions\ExecutionCancelledException;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;
use Illuminate\Support\Facades\Log;

final class ScheduleProblem implements GeneticProblem
{
    private const MAX_BUILD_ATTEMPTS = 25;  // ← Aumentado de 12 para melhor qualidade

    private const MIN_BUILD_ATTEMPTS = 4;

    private const RCL_MIN_SIZE = 3;

    private const RCL_ALPHA_MIN = 0.15;

    private const RCL_ALPHA_MAX = 0.25;  // ← Reduzido de 0.45 (mais greedy, menos aleatório)

    private const TELEMETRY_EVERY_ALLOCATIONS = 25;

    private const DYNAMIC_QUEUE_REORDER_EVERY_ALLOCATIONS = 8;

    private const REGRET_FRONTIER_SIZE = 6;

    private const INITIAL_QUALITY_GATE_BASE_HARD_PENALTY = 12.0;

    private const INITIAL_QUALITY_GATE_CONFLICT_RATIO_START = 0.005;

    private const INITIAL_QUALITY_GATE_CONFLICT_RATIO_GROWTH = 0.0025;

    private const INITIAL_QUALITY_GATE_CONFLICT_RATIO_MAX = 0.03;

    private const INITIAL_QUALITY_GATE_FAIL_FAST_GRACE_RATIO = 0.005;

    private const REPAIR_TELEMETRY_SAMPLE_EVERY = 25;

    private const INITIAL_QUALITY_GATE_REPAIR_TIME_BUDGET_MS = 1500;

    private const INITIAL_QUALITY_GATE_REPAIR_MAX_PASSES_WITHOUT_PROGRESS = 1;

    private const EVOLUTION_REPAIR_HEARTBEAT_INTERVAL_SECONDS = 30;

    private const LONG_RUNNING_REPAIR_LOG_INTERVAL_SECONDS = 300;

    private const CANCELLATION_CHECK_INTERVAL_SECONDS = 2;

    private const SEED_REUSE_MAX_ATTEMPTS = 4;

    private const SEED_REUSE_PERTURBATION_RATIO = 0.12;

    private const SEED_REUSE_PERTURBATION_MIN = 2;

    private const SEED_REUSE_PERTURBATION_MAX = 18;

    private string $lastBuildFailure = 'Falha ao montar individuo inicial.';

    private ?array $cachedPlacementQueue = null;

    private ?array $cachedDiagnostics = null;

    private array $candidateSlotIdsByLesson = [];

    private array $availableDaysCountCache = [];

    private int $repairTelemetryCounter = 0;

    private array $lastRepairTelemetry = [];

    private ?float $lastEvolutionRepairHeartbeatAt = null;

    private ?float $lastCancellationCheckAt = null;

    private ?string $lastKnownExecutionStatus = null;

    private ?Cromossomo $lastAcceptedInitialSeed = null;

    /**
     * @var array{
     *     lesson_slot: array<string, int>,
     *     professor_slot: array<string, int>,
     *     class_slot: array<string, int>
     * }
     */
    private array $initialPopulationNogoods = [
        'lesson_slot' => [],
        'professor_slot' => [],
        'class_slot' => [],
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private array $initialPopulationAttemptHistory = [];

    /**
     * @var array<string, int>
     */
    private array $initialPopulationCounters = [
        'fail_fast' => 0,
        'quality_gate_rejected' => 0,
        'construct_failed' => 0,
        'quality_gate_passed' => 0,
    ];

    private int $currentBuildAttemptLimitBase = self::MAX_BUILD_ATTEMPTS;

    private int $currentBuildAttemptLimit = self::MAX_BUILD_ATTEMPTS;

    /**
     * @var list<string>
     */
    private array $currentBuildAttemptLimitReductionCriteria = [];

    // 🔧 PRIORIDADE 10: Rastreamento de fitness anterior para evaluateDelta
    /**
     * @var array<string, FitnessResult>
     * Mapping de cromossomo signature -> fitness anterior
     * Usado para calcular delta em vez de reavaliar completo
     */
    private array $previousFitnessCache = [];

    public function __construct(private readonly ScheduleData $data, private readonly EvaluationContextBuilder $contextBuilder, private readonly FitnessEvaluator $fitnessEvaluator, private readonly GreedyRepairOperator $repairOperator, private readonly ?ProgressReporterInterface $progress = null, private readonly ?int $executionId = null)
    {
    }

    public function createIndividual(): Cromossomo
    {
        $this->assertNotCancelled();
        $queue = $this->buildPlacementQueue();
        $bestRejectedAttempt = null;
        $this->currentBuildAttemptLimit = $this->resolveAdaptiveBuildAttemptLimit();

        $this->runPreventiveDiagnosis($queue);

        if ($this->lastAcceptedInitialSeed !== null) {
            $seedCandidate = $this->tryCreateIndividualFromAcceptedSeed($queue);

            if ($seedCandidate !== null) {
                return $seedCandidate;
            }
        }

        for ($attempt = 1; $attempt <= $this->currentBuildAttemptLimit; $attempt++) {
            $this->assertNotCancelled();
            $attemptStartedAt = microtime(true);
            $teacherBusy = [];
            $classBusy = [];
            $assignedGenes = [];
            $alpha = $this->randomAlpha();
            $telemetry = [
                'attempt' => $attempt,
                'alpha' => round($alpha, 4),
                'queue_size' => count($queue),
                'allocations' => 0,
                'forced_allocations' => 0,
                'hard_conflict_allocations' => 0,
                'dynamic_reorders' => 0,
                'regret_selections' => 0,
                'attempt_limit' => $this->currentBuildAttemptLimit,
                'rcl_sizes' => [],
            ];

            Log::info('schedule.initial_population.grasp.start', $telemetry);
            $this->reportInitialPopulationProgress([
                'stage' => 'grasp_start',
                'attempt' => $attempt,
                'alpha' => round($alpha, 4),
                'queue_size' => count($queue),
                'attempt_limit' => $this->currentBuildAttemptLimit,
                'allocations' => 0,
                'forced_allocations' => 0,
                'hard_conflict_allocations' => 0,
                'fill_ratio' => 0,
            ]);

            if ($this->constructWithGrasp($queue, $alpha, $assignedGenes, $teacherBusy, $classBusy, $telemetry)) {
                $failFast = $this->evaluateInitialPopulationFailFast(attempt: $attempt, queueSize: count($queue), telemetry: $telemetry);

                if ($failFast['should_fail_fast']) {
                    $this->rememberNogoodsFromAssignedGenes($assignedGenes);
                    $this->lastBuildFailure = $failFast['message'];
                    $this->initialPopulationCounters['fail_fast']++;
                    $this->recordInitialPopulationAttempt(attempt: $attempt, outcome: 'fail_fast', telemetry: $telemetry, attemptStartedAt: $attemptStartedAt, extra: [
                            'message' => $this->lastBuildFailure,
                            'fail_fast_limit' => $failFast['fail_fast_limit'],
                        ]);

                    Log::warning('schedule.initial_population.quality_gate.fail_fast', [
                        'attempt' => $attempt,
                        'queue_size' => count($queue),
                        'forced_allocations' => $telemetry['forced_allocations'],
                        'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                        'max_hard_conflict_allocations' => $failFast['max_hard_conflict_allocations'],
                        'grace_hard_conflict_allocations' => $failFast['grace_hard_conflict_allocations'],
                    ]);

                    $this->reportInitialPopulationProgress([
                        'stage' => 'quality_gate_fail_fast',
                        'attempt' => $attempt,
                        'queue_size' => count($queue),
                        'attempt_limit' => $this->currentBuildAttemptLimit,
                        'forced_allocations' => $telemetry['forced_allocations'],
                        'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                        'max_hard_conflict_allocations' => $failFast['max_hard_conflict_allocations'],
                        'grace_hard_conflict_allocations' => $failFast['grace_hard_conflict_allocations'],
                        'fill_ratio' => 1,
                        'message' => $this->lastBuildFailure,
                    ]);

                    continue;
                }

                $candidate = $this->repairWithTelemetry(new Cromossomo($assignedGenes), reportProgress: true, source: 'initial_population_quality_gate', progressContext: [
                        'attempt' => $attempt,
                        'queue_size' => count($queue),
                        'forced_allocations' => $telemetry['forced_allocations'],
                        'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                        'fill_ratio' => 1,
                    ]);
                $qualityGate = $this->evaluateInitialPopulationQualityGate(candidate: $candidate, attempt: $attempt, queueSize: count($queue), telemetry: $telemetry);

                if ($qualityGate['passes']) {
                    $this->initialPopulationCounters['quality_gate_passed']++;
                    $this->recordInitialPopulationAttempt(attempt: $attempt, outcome: 'quality_gate_passed', telemetry: $telemetry, attemptStartedAt: $attemptStartedAt, extra: [
                            'hard_penalty' => $qualityGate['hard_penalty'],
                            'soft_penalty' => $qualityGate['soft_penalty'],
                            'score' => $qualityGate['score'],
                        ]);
                    $this->reportInitialPopulationProgress([
                        'stage' => 'grasp_completed',
                        'attempt' => $attempt,
                        'alpha' => round($alpha, 4),
                        'queue_size' => count($queue),
                        'attempt_limit' => $this->currentBuildAttemptLimit,
                        'allocations' => $telemetry['allocations'],
                        'forced_allocations' => $telemetry['forced_allocations'],
                        'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                        'dynamic_reorders' => $telemetry['dynamic_reorders'],
                        'regret_selections' => $telemetry['regret_selections'],
                        'fill_ratio' => 1,
                        'hard_penalty' => $qualityGate['hard_penalty'],
                        'soft_penalty' => $qualityGate['soft_penalty'],
                        'fitness_score' => $qualityGate['score'],
                    ]);

                    $this->reportInitialPopulationProgress([
                        'stage' => 'quality_gate_passed',
                        'attempt' => $attempt,
                        'queue_size' => count($queue),
                        'attempt_limit' => $this->currentBuildAttemptLimit,
                        'hard_penalty' => $qualityGate['hard_penalty'],
                        'soft_penalty' => $qualityGate['soft_penalty'],
                        'fitness_score' => $qualityGate['score'],
                        'max_hard_penalty' => $qualityGate['max_hard_penalty'],
                        'max_hard_conflict_allocations' => $qualityGate['max_hard_conflict_allocations'],
                        'forced_allocations' => $telemetry['forced_allocations'],
                        'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                    ]);

                    $this->lastAcceptedInitialSeed = $candidate->copy();

                    return $candidate;
                }

                $this->initialPopulationCounters['quality_gate_rejected']++;
                $this->rememberNogoodsFromAssignedGenes($candidate->genes());
                $this->recordInitialPopulationAttempt(attempt: $attempt, outcome: 'quality_gate_rejected', telemetry: $telemetry, attemptStartedAt: $attemptStartedAt, extra: [
                        'hard_penalty' => $qualityGate['hard_penalty'],
                        'soft_penalty' => $qualityGate['soft_penalty'],
                        'score' => $qualityGate['score'],
                        'message' => $this->lastBuildFailure,
                    ]);

                if (
                    $bestRejectedAttempt === null ||
                    $qualityGate['hard_penalty'] < $bestRejectedAttempt['hard_penalty'] ||
                    ($qualityGate['hard_penalty'] === $bestRejectedAttempt['hard_penalty'] &&
                        $qualityGate['soft_penalty'] < $bestRejectedAttempt['soft_penalty'])
                ) {
                    $bestRejectedAttempt = $qualityGate;
                }

                $this->lastBuildFailure = sprintf('Quality gate rejeitou tentativa %d: hard_penalty=%.4f (limite=%.4f), hard_conflicts=%d (limite=%d).', $attempt, $qualityGate['hard_penalty'], $qualityGate['max_hard_penalty'], $telemetry['hard_conflict_allocations'], $qualityGate['max_hard_conflict_allocations']);

                $this->reportInitialPopulationProgress([
                    'stage' => 'quality_gate_rejected',
                    'attempt' => $attempt,
                    'queue_size' => count($queue),
                    'attempt_limit' => $this->currentBuildAttemptLimit,
                    'hard_penalty' => $qualityGate['hard_penalty'],
                    'soft_penalty' => $qualityGate['soft_penalty'],
                    'fitness_score' => $qualityGate['score'],
                    'max_hard_penalty' => $qualityGate['max_hard_penalty'],
                    'max_hard_conflict_allocations' => $qualityGate['max_hard_conflict_allocations'],
                    'forced_allocations' => $telemetry['forced_allocations'],
                    'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                    'message' => $this->lastBuildFailure,
                ]);
            } else {
                $this->initialPopulationCounters['construct_failed']++;
                $this->recordInitialPopulationAttempt(attempt: $attempt, outcome: 'construct_failed', telemetry: $telemetry, attemptStartedAt: $attemptStartedAt, extra: [
                        'message' => $this->lastBuildFailure,
                    ]);
            }

            // Log::warning('schedule.initial_population.retry', [
            //     'attempt' => $attempt,
            //     'reason' => $this->lastBuildFailure,
            //     'alpha' => round($alpha, 4),
            //     'allocations' => $telemetry['allocations'],
            //     'forced_allocations' => $telemetry['forced_allocations'],
            //     'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
            // ]);

            $this->reportInitialPopulationProgress([
                'stage' => 'grasp_retry',
                'attempt' => $attempt,
                'alpha' => round($alpha, 4),
                'queue_size' => count($queue),
                'attempt_limit' => $this->currentBuildAttemptLimit,
                'allocations' => $telemetry['allocations'],
                'forced_allocations' => $telemetry['forced_allocations'],
                'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                'dynamic_reorders' => $telemetry['dynamic_reorders'],
                'regret_selections' => $telemetry['regret_selections'],
                'fill_ratio' => round($telemetry['allocations'] / max(1, count($queue)), 4),
                'message' => $this->lastBuildFailure,
            ]);
        }

        if ($bestRejectedAttempt !== null) {
            $this->lastBuildFailure = sprintf('%s Melhor tentativa rejeitada: hard_penalty=%.4f, soft_penalty=%.4f, score=%.4f.', $this->lastBuildFailure, $bestRejectedAttempt['hard_penalty'], $bestRejectedAttempt['soft_penalty'], $bestRejectedAttempt['score']);
        }

        throw new \RuntimeException($this->lastBuildFailure);
    }

    private function tryCreateIndividualFromAcceptedSeed(array $queue): ?Cromossomo
    {
        for ($attempt = 1; $attempt <= self::SEED_REUSE_MAX_ATTEMPTS; $attempt++) {
            $this->assertNotCancelled();

            $this->reportInitialPopulationProgress([
                'stage' => 'seed_reuse_start',
                'attempt' => $attempt,
                'queue_size' => count($queue),
            ]);

            $candidate = $this->perturbAcceptedSeed();

            if ($candidate === null) {
                continue;
            }

            $candidate = $this->repairWithTelemetry($candidate, reportProgress: true, source: 'initial_population_quality_gate', progressContext: [
                    'attempt' => $attempt,
                    'queue_size' => count($queue),
                    'forced_allocations' => 0,
                    'hard_conflict_allocations' => count($this->countSeedHardConflicts($candidate)),
                    'fill_ratio' => 1,
                    'seed_reuse' => true,
                ]);

            $qualityGate = $this->evaluateInitialPopulationQualityGate(candidate: $candidate, attempt: self::MAX_BUILD_ATTEMPTS, queueSize: count($queue), telemetry: [
                    'hard_conflict_allocations' => count($this->countSeedHardConflicts($candidate)),
                ]);

            if ($qualityGate['passes']) {
                $this->lastAcceptedInitialSeed = $candidate->copy();

                $this->reportInitialPopulationProgress([
                    'stage' => 'seed_reuse_passed',
                    'attempt' => $attempt,
                    'queue_size' => count($queue),
                    'hard_penalty' => $qualityGate['hard_penalty'],
                    'soft_penalty' => $qualityGate['soft_penalty'],
                    'fitness_score' => $qualityGate['score'],
                ]);

                return $candidate;
            }

            $this->reportInitialPopulationProgress([
                'stage' => 'seed_reuse_rejected',
                'attempt' => $attempt,
                'queue_size' => count($queue),
                'hard_penalty' => $qualityGate['hard_penalty'],
                'soft_penalty' => $qualityGate['soft_penalty'],
                'fitness_score' => $qualityGate['score'],
                'message' => 'Seed reutilizado nao passou no quality gate.',
            ]);
        }

        return null;
    }

    private function perturbAcceptedSeed(): ?Cromossomo
    {
        $seed = $this->lastAcceptedInitialSeed?->copy();

        if ($seed === null || $seed->count() === 0) {
            return null;
        }

        $working = $seed->copy();
        $indexes = $this->randomSeedPerturbationIndexes($working->count());

        if ($indexes === []) {
            return $working;
        }

        foreach ($indexes as $index) {
            $genes = $working->genes();

            if (! isset($genes[$index])) {
                continue;
            }

            $gene = $genes[$index];
            $lesson = $this->data->lessons[$gene->aulaId()] ?? null;

            if ($lesson === null) {
                continue;
            }

            [$teacherBusy, $classBusy] = $this->buildOccupancyMapsFromChromosome($working, [$index]);
            $assignedGenes = array_values(array_filter($working->genes(), static fn (Gene $assignedGene, int $geneIndex): bool => $geneIndex !== $index, ARRAY_FILTER_USE_BOTH));
            $candidate = $this->findPreferredPerturbedGenePlacement($lesson, $teacherBusy, $classBusy, $assignedGenes);

            if ($candidate === null) {
                continue;
            }

            $working->replaceGene($index, $candidate);
        }

        return $working;
    }

    /**
     * @return list<int>
     */
    private function randomSeedPerturbationIndexes(int $geneCount): array
    {
        if ($geneCount <= 0) {
            return [];
        }

        $targetCount = (int) ceil($geneCount * self::SEED_REUSE_PERTURBATION_RATIO);
        $targetCount = max(self::SEED_REUSE_PERTURBATION_MIN, $targetCount);
        $targetCount = min(self::SEED_REUSE_PERTURBATION_MAX, $targetCount, $geneCount);

        $indexes = range(0, $geneCount - 1);
        shuffle($indexes);

        return array_slice($indexes, 0, $targetCount);
    }

    private function resolveAdaptiveBuildAttemptLimit(): int
    {
        $baseLimit = $this->lastAcceptedInitialSeed !== null
            ? min(self::MAX_BUILD_ATTEMPTS, 6)
            : self::MAX_BUILD_ATTEMPTS;
        $reductionCriteria = [];

        $recentAttempts = array_slice($this->initialPopulationAttemptHistory, -6);

        if ($recentAttempts === []) {
            $this->currentBuildAttemptLimitBase = $baseLimit;
            $this->currentBuildAttemptLimitReductionCriteria = [];

            return $baseLimit;
        }

        $recentCount = count($recentAttempts);
        $failFastCount = count(array_filter($recentAttempts, static fn (array $attempt): bool => ($attempt['outcome'] ?? null) === 'fail_fast'));
        $qualityGateRejectedCount = count(array_filter($recentAttempts, static fn (array $attempt): bool => ($attempt['outcome'] ?? null) === 'quality_gate_rejected'));
        $slowAttemptCount = count(array_filter($recentAttempts, static fn (array $attempt): bool => ((int) ($attempt['duration_ms'] ?? 0)) >= 120000));
        $avgHardPenalty = array_sum(array_map(static fn (array $attempt): float => (float) ($attempt['hard_penalty'] ?? 0.0), $recentAttempts)) / max(1, $recentCount);

        $limit = $baseLimit;

        if (($failFastCount / $recentCount) >= 0.7) {
            $limit -= 3;
            $reductionCriteria[] = 'Taxa alta de fail-fast nas ultimas tentativas.';
        }

        if (($qualityGateRejectedCount / $recentCount) >= 0.5) {
            $limit -= 2;
            $reductionCriteria[] = 'Muitas rejeicoes no quality gate apos o repair.';
        }

        if (($slowAttemptCount / $recentCount) >= 0.34) {
            $limit -= 2;
            $reductionCriteria[] = 'Tentativas recentes ficaram lentas demais para o beneficio entregue.';
        }

        if ($avgHardPenalty >= 36.0) {
            $limit -= 1;
            $reductionCriteria[] = 'A penalidade hard media segue alta mesmo apos varias tentativas.';
        }

        $resolvedLimit = max(self::MIN_BUILD_ATTEMPTS, min(self::MAX_BUILD_ATTEMPTS, $limit));

        $this->currentBuildAttemptLimitBase = $baseLimit;
        $this->currentBuildAttemptLimitReductionCriteria = $resolvedLimit < $baseLimit
            ? array_values(array_unique($reductionCriteria))
            : [];

        return $resolvedLimit;
    }

    public function evaluate(Cromossomo $individual): FitnessResult
    {
        $context = $this->contextBuilder->build($individual, $this->data);

        return $this->fitnessEvaluator->evaluate($individual, $context);
    }

    public function evaluateDelta(Cromossomo $individual, AffectedRegion $region, FitnessResult $previous): FitnessResult
    {
        $context = $this->contextBuilder->build($individual, $this->data);

        return $this->fitnessEvaluator->evaluateDelta($individual, $context, $region, $previous);
    }

    /**
     * 🔧 PRIORIDADE 10: Avaliar usando delta se fitness anterior está disponível.
     * Reduz significativamente o custo de avaliação em mutação/repair.
     *
     * Se o cromossomo foi modificado em regiões específicas (gene indices),
     * use evaluateDelta para reavalia apenas regras afetadas.
     * Caso contrário, volta para evaluate() completo.
     */
    public function evaluateWithDelta(
        Cromossomo $individual,
        AffectedRegion $region
    ): FitnessResult {
        $signature = $individual->signature();

        // Se não temos fitness anterior, fazer avaliação completa
        if (!isset($this->previousFitnessCache[$signature])) {
            $result = $this->evaluate($individual);
            // Guardar para próxima mutação
            $this->previousFitnessCache[$signature] = $result;
            return $result;
        }

        // Usar delta evaluation - muito mais rápido!
        $previous = $this->previousFitnessCache[$signature];
        $result = $this->evaluateDelta($individual, $region, $previous);

        // Atualizar cache para próxima mutação
        $this->previousFitnessCache[$signature] = $result;

        return $result;
    }

    /**
     * 🔧 PRIORIDADE 10: Registrar fitness após mutação/repair para próxima avaliação.
     * Isso permite que evaluateWithDelta() funcione sem surpresas.
     */
    public function recordFitness(Cromossomo $individual, FitnessResult $fitness): void
    {
        $signature = $individual->signature();
        $this->previousFitnessCache[$signature] = $fitness;
    }

    /**
     * 🔧 PRIORIDADE 10: Limpar cache de fitness (entre gerações ou execuções).
     */
    public function clearFitnessDeltaCache(): void
    {
        $this->previousFitnessCache = [];
    }

    /**
     * 🔧 PRIORIDADE 10: Avaliar com delta se AffectedRegion está disponível.
     * Para uso em contextos onde sabemos exatamente qual região foi modificada.
     *
     * Fallback automático para evaluate() se delta não for possível.
     */
    public function evaluateWithAffectedRegion(
        Cromossomo $individual,
        ?AffectedRegion $region = null
    ): FitnessResult {
        $signature = $individual->signature();

        // Se região não foi fornecida, fazer avaliação completa
        if ($region === null) {
            $result = $this->evaluate($individual);
            $this->previousFitnessCache[$signature] = $result;
            return $result;
        }

        // Se não temos fitness anterior, fazer avaliação completa
        if (!isset($this->previousFitnessCache[$signature])) {
            $result = $this->evaluate($individual);
            $this->previousFitnessCache[$signature] = $result;
            return $result;
        }

        // Usar delta evaluation - muito mais rápido!
        $previous = $this->previousFitnessCache[$signature];
        $result = $this->evaluateDelta($individual, $region, $previous);

        // Atualizar cache para próxima mutação
        $this->previousFitnessCache[$signature] = $result;

        return $result;
    }

    public function repair(Cromossomo $individual): Cromossomo
    {
        $shouldReportProgress = $this->progress !== null && $this->executionId !== null;

        return $this->repairWithTelemetry($individual, reportProgress: $shouldReportProgress, source: 'evolution_runtime');
    }

    public function repairWithTelemetry(Cromossomo $individual, bool $reportProgress = false, string $source = 'evolution', array $progressContext = []): Cromossomo
    {
        $probe = null;
        $heartbeat = null;
        $repairStartedAt = microtime(true);
        $lastLongRunningRepairLogAt = null;

        if ($reportProgress && $source === 'initial_population_quality_gate') {
            $probe = function (Cromossomo $candidate): array {
                $result = $this->evaluate($candidate);

                return [
                    'hard_penalty' => $result->hardPenalty(),
                    'soft_penalty' => $result->softPenalty(),
                    'score' => $result->score(),
                ];
            };
        }

        if ($reportProgress && $source === 'initial_population_quality_gate') {
            $this->reportInitialPopulationProgress($progressContext + [
                'stage' => 'quality_gate_repair_started',
            ]);

            $heartbeat = function (array $heartbeatPayload) use ($progressContext, $source, $repairStartedAt, &$lastLongRunningRepairLogAt): void {
                $this->logLongRunningRepairOperation($source, $progressContext, $heartbeatPayload, $repairStartedAt, $lastLongRunningRepairLogAt);
                $this->reportInitialPopulationProgress($progressContext + [
                    'stage' => 'quality_gate_repairing',
                    'repair_event' => $heartbeatPayload['event'] ?? null,
                    'repair_abort_reason' => $heartbeatPayload['abort_reason'] ?? null,
                    'repair_pass' => $heartbeatPayload['pass'] ?? null,
                    'repair_time_budget_ms' => $heartbeatPayload['time_budget_ms'] ?? null,
                    'repair_passes_without_progress' => $heartbeatPayload['passes_without_progress'] ?? null,
                    'repair_invalid_genes_before' => $heartbeatPayload['invalid_genes_before'] ?? null,
                    'repair_invalid_genes_after' => $heartbeatPayload['invalid_genes_after'] ?? null,
                    'repair_processed_invalid_genes' => $heartbeatPayload['processed_invalid_genes'] ?? null,
                    'repair_total_invalid_genes' => $heartbeatPayload['total_invalid_genes'] ?? null,
                    'repair_hard_penalty_before' => $heartbeatPayload['hard_penalty_before'] ?? null,
                    'repair_hard_penalty_after' => $heartbeatPayload['hard_penalty_after'] ?? null,
                    'repair_hard_penalty_delta' => $heartbeatPayload['hard_penalty_delta'] ?? null,
                    'repair_relocations' => $heartbeatPayload['relocations'] ?? 0,
                    'repair_swaps' => $heartbeatPayload['swaps'] ?? 0,
                    'repair_local_rebuilds' => $heartbeatPayload['local_rebuilds'] ?? 0,
                ]);
            };
        }

        if ($reportProgress && $source === 'evolution_runtime') {
            $heartbeat = function (array $heartbeatPayload) use ($source, $progressContext, $repairStartedAt, &$lastLongRunningRepairLogAt): void {
                $now = microtime(true);

                $this->logLongRunningRepairOperation($source, $progressContext, $heartbeatPayload, $repairStartedAt, $lastLongRunningRepairLogAt);

                if (
                    $this->lastEvolutionRepairHeartbeatAt !== null
                    && ($now - $this->lastEvolutionRepairHeartbeatAt) < self::EVOLUTION_REPAIR_HEARTBEAT_INTERVAL_SECONDS
                    && ! in_array($heartbeatPayload['event'] ?? null, ['repair_aborted', 'pass_finished'], true)
                ) {
                    return;
                }

                $this->lastEvolutionRepairHeartbeatAt = $now;

                $this->progress?->report([
                    'phase' => 'evolution',
                    'stage' => 'repair_runtime',
                    'execution_id' => $this->executionId,
                    'current_operation' => 'repair_runtime',
                    'operation_label' => 'Reparando descendente durante a evolucao',
                    'repair_event' => $heartbeatPayload['event'] ?? null,
                    'repair_abort_reason' => $heartbeatPayload['abort_reason'] ?? null,
                    'repair_pass' => $heartbeatPayload['pass'] ?? null,
                    'repair_passes_without_progress' => $heartbeatPayload['passes_without_progress'] ?? null,
                    'repair_invalid_genes_before' => $heartbeatPayload['invalid_genes_before'] ?? null,
                    'repair_invalid_genes_after' => $heartbeatPayload['invalid_genes_after'] ?? null,
                    'repair_processed_invalid_genes' => $heartbeatPayload['processed_invalid_genes'] ?? null,
                    'repair_total_invalid_genes' => $heartbeatPayload['total_invalid_genes'] ?? null,
                    'repair_hard_penalty_before' => $heartbeatPayload['hard_penalty_before'] ?? null,
                    'repair_hard_penalty_after' => $heartbeatPayload['hard_penalty_after'] ?? null,
                    'repair_hard_penalty_delta' => $heartbeatPayload['hard_penalty_delta'] ?? null,
                    'repair_relocations' => $heartbeatPayload['relocations'] ?? 0,
                    'repair_swaps' => $heartbeatPayload['swaps'] ?? 0,
                    'repair_local_rebuilds' => $heartbeatPayload['local_rebuilds'] ?? 0,
                ]);
            };
        }

        $limits = [];

        if ($source === 'initial_population_quality_gate') {
            $limits = [
                'max_millis' => self::INITIAL_QUALITY_GATE_REPAIR_TIME_BUDGET_MS,
                'max_passes_without_progress' => self::INITIAL_QUALITY_GATE_REPAIR_MAX_PASSES_WITHOUT_PROGRESS,
            ];
        }

        $repaired = $this->repairOperator->repair($individual, $this->data, $probe, $heartbeat, $limits);
        $this->lastRepairTelemetry = $this->repairOperator->lastTelemetry();

        if ($reportProgress && $source === 'initial_population_quality_gate') {
            $this->reportInitialPopulationProgress($progressContext + [
                'stage' => 'quality_gate_repair_finished',
                'repair_summary' => $this->summarizeRepairTelemetry($this->lastRepairTelemetry),
            ]);
        }

        if (
            $reportProgress
            && $source !== 'initial_population_quality_gate'
            && $this->shouldPublishRepairTelemetry($this->lastRepairTelemetry)
        ) {
            $this->reportRepairProgress($source, $this->lastRepairTelemetry);
        }

        return $repaired;
    }

    /**
     * @param  array<string, mixed>  $progressContext
     * @param  array<string, mixed>  $heartbeatPayload
     */
    private function logLongRunningRepairOperation(string $source, array $progressContext, array $heartbeatPayload, float $repairStartedAt, ?float &$lastLongRunningRepairLogAt): void
    {
        $now = microtime(true);
        $elapsedSeconds = $now - $repairStartedAt;

        if (
            $elapsedSeconds < self::LONG_RUNNING_REPAIR_LOG_INTERVAL_SECONDS
            || ($lastLongRunningRepairLogAt !== null
                && ($now - $lastLongRunningRepairLogAt) < self::LONG_RUNNING_REPAIR_LOG_INTERVAL_SECONDS)
        ) {
            return;
        }

        Log::warning('schedule.repair.long_running_operation', [
            'execution_id' => $this->executionId,
            'source' => $source,
            'operation_label' => $source === 'evolution_runtime'
                ? 'Reparando descendente durante a evolucao'
                : 'Reparando candidato inicial do quality gate',
            'elapsed_seconds' => (int) round($elapsedSeconds),
            'attempt' => $progressContext['attempt'] ?? null,
            'queue_size' => $progressContext['queue_size'] ?? null,
            'repair_event' => $heartbeatPayload['event'] ?? null,
            'repair_pass' => $heartbeatPayload['pass'] ?? null,
            'processed_invalid_genes' => $heartbeatPayload['processed_invalid_genes'] ?? null,
            'total_invalid_genes' => $heartbeatPayload['total_invalid_genes'] ?? null,
            'hard_penalty_before' => $heartbeatPayload['hard_penalty_before'] ?? null,
            'hard_penalty_after' => $heartbeatPayload['hard_penalty_after'] ?? null,
            'hard_penalty_delta' => $heartbeatPayload['hard_penalty_delta'] ?? null,
            'relocations' => $heartbeatPayload['relocations'] ?? null,
            'swaps' => $heartbeatPayload['swaps'] ?? null,
            'local_rebuilds' => $heartbeatPayload['local_rebuilds'] ?? null,
        ]);

        $lastLongRunningRepairLogAt = $now;
    }

    public function lastRepairTelemetry(): array
    {
        return $this->lastRepairTelemetry;
    }

    public function isFeasible(Cromossomo $individual): bool
    {
        $result = $this->evaluate($individual);

        return $result->hardPenalty() === 0.0;
    }

    public function clearFitnessCache(): void
    {
        $this->fitnessEvaluator->clearCache();
    }

    private function constructWithGrasp(array $queue, float $alpha, array &$assignedGenes, array &$teacherBusy, array &$classBusy, array &$telemetry): bool
    {
        $remainingQueue = array_values($queue);
        $shouldReorder = true;

        while ($remainingQueue !== []) {
            $this->assertNotCancelled();
            $currentIndex = $telemetry['allocations'];

            if ($shouldReorder || $this->shouldReorderDynamically($telemetry['allocations'], count($remainingQueue))) {
                $remainingQueue = $this->reorderPlacementQueueDynamically($remainingQueue, $teacherBusy, $classBusy, $assignedGenes);
                $telemetry['dynamic_reorders']++;
                $shouldReorder = false;
            }

            $selectedTask = $this->selectNextTaskByRegret(queue: $remainingQueue, teacherBusy: $teacherBusy, classBusy: $classBusy, assignedGenes: $assignedGenes);

            if ($selectedTask !== null) {
                $task = $selectedTask['task'];
                array_splice($remainingQueue, $selectedTask['index'], 1);

                if (($selectedTask['used_regret'] ?? false) === true) {
                    $telemetry['regret_selections']++;
                }
            } else {
                $task = array_shift($remainingQueue);
            }

            if (! is_array($task) || ! isset($task['lesson'], $task['occurrence'])) {
                continue;
            }

            /** @var LessonData $lesson */
            $lesson = $task['lesson'];
            $occurrence = $task['occurrence'];

            $scoredCandidates = $this->scoreFeasibleCandidates(lesson: $lesson, queue: $remainingQueue, currentIndex: $currentIndex, teacherBusy: $teacherBusy, classBusy: $classBusy, assignedGenes: $assignedGenes);

            if (! empty($scoredCandidates)) {
                $rcl = $this->buildRestrictedCandidateList($scoredCandidates, $alpha);
                $slotId = $this->selectFromRcl($rcl);
                $slot = $this->data->timeSlots[$slotId];
                $telemetry['rcl_sizes'][] = count($rcl);
            } else {
                $slot = $this->selectFallbackSlot($lesson);

                if ($slot === null) {
                    $this->lastBuildFailure = "Nenhum slot estrutural para aula {$lesson->id} (ocorrencia {$occurrence}).";

                    return false;
                }

                $telemetry['forced_allocations']++;
                $shouldReorder = true;

                //     Log::warning('schedule.initial_population.grasp.fallback', [
                //         'lesson_id' => $lesson->id,
                //         'occurrence' => $occurrence,
                //         'class_id' => $lesson->classId,
                //         'professor_id' => $lesson->professorId,
                //         'day' => $slot->day,
                //         'period' => $slot->lessonNumber,
                //     ]);
            }

            if (! $this->canUseSlot($lesson, $slot, $teacherBusy, $classBusy)) {
                $telemetry['hard_conflict_allocations']++;
                $this->rememberNogoodPlacement($lesson, $slot);
                $shouldReorder = true;
            }

            $assignedGenes[] = new Gene(aulaId: $lesson->id, professorId: $lesson->professorId, turmaId: $lesson->classId, disciplinaId: $lesson->disciplinaId, diaSemana: $slot->day, periodoDia: $slot->lessonNumber, duracaoTempos: $lesson->requiredSlots);

            $this->occupySlot($lesson, $slot, $teacherBusy, $classBusy);

            $telemetry['allocations']++;

            if ($telemetry['allocations'] % self::TELEMETRY_EVERY_ALLOCATIONS === 0 || $telemetry['allocations'] === $telemetry['queue_size']) {
                // Log::info('schedule.initial_population.grasp.progress', [
                //     'attempt' => $telemetry['attempt'],
                //     'alpha' => $telemetry['alpha'],
                //     'allocations' => $telemetry['allocations'],
                //     'queue_size' => $telemetry['queue_size'],
                //     'forced_allocations' => $telemetry['forced_allocations'],
                //     'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                //     'fill_ratio' => round($telemetry['allocations'] / max(1, $telemetry['queue_size']), 4),
                // ]);
                $this->reportInitialPopulationProgress([
                    'stage' => 'grasp_progress',
                    'attempt' => $telemetry['attempt'],
                    'alpha' => $telemetry['alpha'],
                    'allocations' => $telemetry['allocations'],
                    'queue_size' => $telemetry['queue_size'],
                    'forced_allocations' => $telemetry['forced_allocations'],
                    'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                    'dynamic_reorders' => $telemetry['dynamic_reorders'],
                    'regret_selections' => $telemetry['regret_selections'],
                    'fill_ratio' => round($telemetry['allocations'] / max(1, $telemetry['queue_size']), 4),
                ]);
            }
        }

        return count($assignedGenes) === count($queue);
    }

    private function buildPlacementQueue(): array
    {
        if ($this->cachedPlacementQueue !== null) {
            return $this->cachedPlacementQueue;
        }

        $lessons = array_values($this->data->lessons);
        $difficulty = [];

        foreach ($lessons as $lesson) {
            $candidates = $this->getStaticCandidateSlotIds($lesson);
            $candidateCount = count($candidates);
            $demand = $lesson->weeklyOccurrences * $lesson->requiredSlots;
            $professorDays = $this->availableDaysCount($lesson->professorId, true);
            $classDays = $this->availableDaysCount($lesson->classId, false);

            $difficulty[$lesson->id] = [
                'candidate_count' => $candidateCount,
                'demand' => $demand,
                'duration' => $lesson->requiredSlots,
                'weekly_occurrences' => $lesson->weeklyOccurrences,
                'professor_days' => $professorDays,
                'class_days' => $classDays,
                'tie_breaker' => mt_rand(1, 1000),
            ];
        }

        usort($lessons, function (LessonData $a, LessonData $b) use ($difficulty) {
            $scoreA = $difficulty[$a->id];
            $scoreB = $difficulty[$b->id];

            return
                [
                    $scoreA['candidate_count'],
                    $scoreA['professor_days'],
                    $scoreA['class_days'],
                    -$scoreA['demand'],
                    -$scoreA['duration'],
                    -$scoreA['weekly_occurrences'],
                    $scoreA['tie_breaker'],
                ]
                <=>
                [
                    $scoreB['candidate_count'],
                    $scoreB['professor_days'],
                    $scoreB['class_days'],
                    -$scoreB['demand'],
                    -$scoreB['duration'],
                    -$scoreB['weekly_occurrences'],
                    $scoreB['tie_breaker'],
                ];
        });

        $queue = [];

        foreach ($lessons as $lesson) {
            for ($occurrence = 1; $occurrence <= $lesson->weeklyOccurrences; $occurrence++) {
                $queue[] = [
                    'lesson' => $lesson,
                    'occurrence' => $occurrence,
                    'candidate_count' => $difficulty[$lesson->id]['candidate_count'],
                ];
            }
        }

        return $this->cachedPlacementQueue = $queue;
    }

    private function shouldReorderDynamically(int $allocations, int $remainingQueueCount): bool
    {
        if ($remainingQueueCount <= 1) {
            return false;
        }

        if ($allocations === 0) {
            return true;
        }

        return $allocations % self::DYNAMIC_QUEUE_REORDER_EVERY_ALLOCATIONS === 0;
    }

    private function reorderPlacementQueueDynamically(array $queue, array $teacherBusy, array $classBusy, array $assignedGenes): array
    {
        $ranked = [];

        foreach ($queue as $task) {
            if (! is_array($task) || ! isset($task['lesson'])) {
                continue;
            }

            /** @var LessonData $lesson */
            $lesson = $task['lesson'];
            $ranked[] = [
                'task' => $task,
                'priority' => $this->dynamicQueuePriority($lesson, $teacherBusy, $classBusy, $assignedGenes),
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            return [
                $left['priority']['feasible_slots'],
                -$left['priority']['nogood_pressure'],
                -$left['priority']['same_entity_pressure'],
                -$left['priority']['same_discipline_opportunity'],
                -$left['priority']['demand'],
                $left['priority']['candidate_count'],
                $left['priority']['tie_breaker'],
            ] <=> [
                $right['priority']['feasible_slots'],
                -$right['priority']['nogood_pressure'],
                -$right['priority']['same_entity_pressure'],
                -$right['priority']['same_discipline_opportunity'],
                -$right['priority']['demand'],
                $right['priority']['candidate_count'],
                $right['priority']['tie_breaker'],
            ];
        });

        return array_values(array_map(static fn (array $item): array => $item['task'], $ranked));
    }

    /**
     * @return array{index:int,task:array<string,mixed>,used_regret:bool}|null
     */
    private function selectNextTaskByRegret(array $queue, array $teacherBusy, array $classBusy, array $assignedGenes): ?array
    {
        if ($queue === []) {
            return null;
        }

        $frontierSize = min(self::REGRET_FRONTIER_SIZE, count($queue));
        $bestSelection = null;

        for ($index = 0; $index < $frontierSize; $index++) {
            $task = $queue[$index] ?? null;

            if (! is_array($task) || ! isset($task['lesson'])) {
                continue;
            }

            /** @var LessonData $lesson */
            $lesson = $task['lesson'];
            $scoredCandidates = $this->scoreFeasibleCandidates(lesson: $lesson, queue: $queue, currentIndex: $index, teacherBusy: $teacherBusy, classBusy: $classBusy, assignedGenes: $assignedGenes);

            if ($scoredCandidates === []) {
                continue;
            }

            $regret = $this->calculateRegretScore($scoredCandidates);
            $selection = [
                'index' => $index,
                'task' => $task,
                'used_regret' => $index > 0,
                'regret' => $regret,
                'feasible_slots' => count($scoredCandidates),
                'candidate_count' => count($this->getStaticCandidateSlotIds($lesson)),
                'demand' => $lesson->weeklyOccurrences * $lesson->requiredSlots,
            ];

            if (
                $bestSelection === null
                || [$selection['regret'], -$selection['feasible_slots'], $selection['demand'], -$selection['candidate_count'], -$selection['index']]
                    > [$bestSelection['regret'], -$bestSelection['feasible_slots'], $bestSelection['demand'], -$bestSelection['candidate_count'], -$bestSelection['index']]
            ) {
                $bestSelection = $selection;
            }
        }

        if ($bestSelection === null) {
            return [
                'index' => 0,
                'task' => $queue[0],
                'used_regret' => false,
            ];
        }

        return [
            'index' => $bestSelection['index'],
            'task' => $bestSelection['task'],
            'used_regret' => (bool) $bestSelection['used_regret'],
        ];
    }

    private function calculateRegretScore(array $scoredCandidates): float
    {
        $scores = array_values($scoredCandidates);

        if ($scores === []) {
            return -INF;
        }

        $best = (float) $scores[0];
        $second = isset($scores[1]) ? (float) $scores[1] : ($best + 4.0);
        $third = isset($scores[2]) ? (float) $scores[2] : ($second + 2.0);

        return (($second - $best) * 1.7) + (($third - $best) * 0.8);
    }

    /**
     * @return array<string, int|float>
     */
    private function dynamicQueuePriority(LessonData $lesson, array $teacherBusy, array $classBusy, array $assignedGenes): array
    {
        $feasibleSlots = 0;
        $nogoodPressure = 0.0;
        $sameDisciplineOpportunity = 0;

        foreach ($this->getStaticCandidateSlotIds($lesson) as $slotId) {
            $slot = $this->data->timeSlots[$slotId] ?? null;

            if ($slot === null) {
                continue;
            }

            $nogoodPressure += $this->nogoodPenalty($lesson, $slot);

            if ($this->canUseSlot($lesson, $slot, $teacherBusy, $classBusy)) {
                $feasibleSlots++;

                if ($this->sameDisciplineAdjacencyPenalty($lesson, $slot, $assignedGenes) < 0) {
                    $sameDisciplineOpportunity++;
                }
            }
        }

        return [
            'feasible_slots' => $feasibleSlots,
            'nogood_pressure' => $nogoodPressure,
            'same_entity_pressure' => $this->sameEntityPressure($lesson, $teacherBusy, $classBusy),
            'same_discipline_opportunity' => $sameDisciplineOpportunity,
            'demand' => $lesson->weeklyOccurrences * $lesson->requiredSlots,
            'candidate_count' => count($this->getStaticCandidateSlotIds($lesson)),
            'tie_breaker' => ($lesson->id * 31) % 997,
        ];
    }

    private function runPreventiveDiagnosis(array $queue): void
    {
        if ($this->cachedDiagnostics !== null) {
            if (! empty($this->cachedDiagnostics['blocked'])) {
                $first = $this->cachedDiagnostics['blocked'][0];
                $this->lastBuildFailure = "Diagnostico preventivo: aula {$first['lesson_id']} tem {$first['candidate_slots']} slots viaveis para {$first['weekly_occurrences']} ocorrencias.";
                throw new \RuntimeException($this->lastBuildFailure);
            }

            return;
        }

        $diagnostics = [];

        foreach ($this->data->lessons as $lesson) {
            $candidateSlotIds = $this->getStaticCandidateSlotIds($lesson);

            $diagnostics[] = [
                'lesson_id' => $lesson->id,
                'candidate_slots' => count($candidateSlotIds),
                'weekly_occurrences' => $lesson->weeklyOccurrences,
                'required_slots' => $lesson->requiredSlots,
                'class_id' => $lesson->classId,
                'professor_id' => $lesson->professorId,
                'professor_available_days' => $this->availableDaysCount($lesson->professorId, true),
                'class_available_days' => $this->availableDaysCount($lesson->classId, false),
            ];
        }

        usort($diagnostics, fn (array $a, array $b) => $a['candidate_slots'] <=> $b['candidate_slots']);

        Log::info('schedule.initial_population.diagnosis', [
            'queue_size' => count($queue),
            'hardest_lessons' => array_slice($diagnostics, 0, 10),
        ]);
        $this->reportInitialPopulationProgress([
            'stage' => 'diagnosis',
            'queue_size' => count($queue),
            'hardest_lessons' => array_slice($diagnostics, 0, 5),
        ]);

        $blocked = array_filter($diagnostics, static fn (array $item) => $item['candidate_slots'] < $item['weekly_occurrences']);
        $blocked = array_values($blocked);
        $this->cachedDiagnostics = [
            'diagnostics' => $diagnostics,
            'blocked' => $blocked,
        ];

        if (! empty($blocked)) {
            $first = $blocked[0];
            $this->lastBuildFailure = "Diagnostico preventivo: aula {$first['lesson_id']} tem {$first['candidate_slots']} slots viaveis para {$first['weekly_occurrences']} ocorrencias.";

            throw new \RuntimeException($this->lastBuildFailure);
        }
    }

    private function scoreFeasibleCandidates(LessonData $lesson, array $queue, int $currentIndex, array $teacherBusy, array $classBusy, array $assignedGenes = []): array
    {
        $candidateScores = [];

        foreach ($this->getStaticCandidateSlotIds($lesson) as $slotId) {
            $slot = $this->data->timeSlots[$slotId];

            if (! $this->canUseSlot($lesson, $slot, $teacherBusy, $classBusy)) {
                continue;
            }

            $candidateScores[$slotId] = $this->scoreCandidateSlot(lesson: $lesson, slot: $slot, queue: $queue, currentIndex: $currentIndex, teacherBusy: $teacherBusy, classBusy: $classBusy, assignedGenes: $assignedGenes);
        }

        asort($candidateScores);

        return $candidateScores;
    }

    private function scoreCandidateSlot(LessonData $lesson, TimeSlot $slot, array $queue, int $currentIndex, array $teacherBusy, array $classBusy, array $assignedGenes = []): float
    {
        $score = 0.0;

        $score += $this->sameDayLoadPenalty($lesson, $slot, $teacherBusy, $classBusy);
        $score += $this->sameDisciplineAdjacencyPenalty($lesson, $slot, $assignedGenes);
        $score += $this->nogoodPenalty($lesson, $slot);
        $score -= $this->futureFlexibilityScore($slot, $queue, $currentIndex, $lesson, $teacherBusy, $classBusy);
        $score += mt_rand(0, 100) / 1000;

        return $score;
    }

    private function futureFlexibilityScore(TimeSlot $slot, array $queue, int $currentIndex, LessonData $currentLesson, array $teacherBusy, array $classBusy): float
    {
        $teacherBusySimulated = $teacherBusy;
        $classBusySimulated = $classBusy;

        $this->occupySlot($currentLesson, $slot, $teacherBusySimulated, $classBusySimulated);

        $score = 0.0;

        foreach (array_slice($queue, $currentIndex + 1, 8) as $task) {
            /** @var LessonData $lesson */
            $lesson = $task['lesson'];

            if ($lesson->professorId !== $currentLesson->professorId && $lesson->classId !== $currentLesson->classId) {
                continue;
            }

            $options = 0;

            foreach ($this->getStaticCandidateSlotIds($lesson) as $slotId) {
                $candidate = $this->data->timeSlots[$slotId];

                if ($this->canUseSlot($lesson, $candidate, $teacherBusySimulated, $classBusySimulated)) {
                    $options++;
                }
            }

            $score += min($options, 6);
        }

        return $score;
    }

    private function sameDayLoadPenalty(LessonData $lesson, TimeSlot $slot, array $teacherBusy, array $classBusy): float
    {
        $teacherDayLoad = 0;
        $classDayLoad = 0;

        foreach ($teacherBusy[$lesson->professorId] ?? [] as $key => $occupied) {
            if (str_starts_with($key, $slot->day . '-')) {
                $teacherDayLoad++;
            }
        }

        foreach ($classBusy[$lesson->classId] ?? [] as $key => $occupied) {
            if (str_starts_with($key, $slot->day . '-')) {
                $classDayLoad++;
            }
        }

        return ($teacherDayLoad * 0.2) + ($classDayLoad * 0.3);
    }

    private function sameEntityPressure(LessonData $lesson, array $teacherBusy, array $classBusy): int
    {
        return count($teacherBusy[$lesson->professorId] ?? [])
            + count($classBusy[$lesson->classId] ?? []);
    }

    private function sameDisciplineAdjacencyPenalty(LessonData $lesson, TimeSlot $slot, array $assignedGenes): float
    {
        if ($assignedGenes === [] || $this->data->maxConsecutiveLessons < 2) {
            return 0.0;
        }

        $sameDisciplinePeriods = [];

        foreach ($assignedGenes as $gene) {
            if (
                ! $gene instanceof Gene
                || $gene->turmaId() !== $lesson->classId
                || $gene->disciplinaId() !== $lesson->disciplinaId
                || $gene->diaSemana() !== $slot->day
            ) {
                continue;
            }

            $sameDisciplinePeriods[] = $gene->periodoDia();
        }

        if ($sameDisciplinePeriods === []) {
            return 0.0;
        }

        $sameDayCount = count($sameDisciplinePeriods);
        $maxSameDay = max(1, min($lesson->maxPerDay ?? $this->data->maxConsecutiveLessons, $this->data->maxConsecutiveLessons));

        if ($sameDayCount >= $maxSameDay) {
            return 6.0;
        }

        $adjacentPeriods = array_filter($sameDisciplinePeriods, static fn (int $period): bool => abs($period - $slot->lessonNumber) === 1);

        if ($adjacentPeriods !== []) {
            return $this->data->groupDisciplines || $lesson->requiresConsecutive
                ? -2.2
                : -1.2;
        }

        return 0.75;
    }

    private function buildRestrictedCandidateList(array $candidateScores, float $alpha): array
    {
        if (empty($candidateScores)) {
            return [];
        }

        $values = array_values($candidateScores);
        $minScore = min($values);
        $maxScore = max($values);
        $threshold = $minScore + ($alpha * ($maxScore - $minScore));

        $rcl = [];

        foreach ($candidateScores as $slotId => $score) {
            if ($score <= $threshold) {
                $rcl[] = $slotId;
            }
        }

        if (count($rcl) < self::RCL_MIN_SIZE) {
            $rcl = array_slice(array_keys($candidateScores), 0, min(self::RCL_MIN_SIZE, count($candidateScores)));
        }

        return $rcl;
    }

    private function selectFromRcl(array $rcl): int
    {
        $index = random_int(0, count($rcl) - 1);

        return $rcl[$index];
    }

    private function selectFallbackSlot(LessonData $lesson): ?TimeSlot
    {
        $candidateSlotIds = $this->getStaticCandidateSlotIds($lesson);

        if (empty($candidateSlotIds)) {
            return null;
        }

        $penalizedCandidates = [];

        foreach ($candidateSlotIds as $slotId) {
            $slot = $this->data->timeSlots[$slotId] ?? null;

            if ($slot === null) {
                continue;
            }

            $penalizedCandidates[$slotId] = $this->nogoodPenalty($lesson, $slot);
        }

        asort($penalizedCandidates);

        $bestPenalty = $penalizedCandidates === []
            ? 0.0
            : (float) reset($penalizedCandidates);
        $bestSlotIds = array_keys(array_filter($penalizedCandidates, static fn (float $penalty): bool => abs($penalty - $bestPenalty) < 0.0001));

        if ($bestSlotIds === []) {
            $bestSlotIds = $candidateSlotIds;
        }

        $slotId = $bestSlotIds[random_int(0, count($bestSlotIds) - 1)];

        return $this->data->timeSlots[$slotId] ?? null;
    }

    private function availableDaysCount(int $entityId, bool $isProfessor): int
    {
        $cacheKey = ($isProfessor ? 'professor:' : 'class:') . $entityId;

        if (array_key_exists($cacheKey, $this->availableDaysCountCache)) {
            return $this->availableDaysCountCache[$cacheKey];
        }

        $slotIds = $isProfessor
            ? ($this->data->availableSlotsByProfessor[$entityId] ?? [])
            : ($this->data->availableSlotsByClass[$entityId] ?? []);

        if (empty($slotIds)) {
            return $this->availableDaysCountCache[$cacheKey] = 0;
        }

        $days = [];

        foreach ($slotIds as $slotId) {
            $slot = $this->data->timeSlots[$slotId] ?? null;

            if ($slot === null) {
                continue;
            }

            $days[$slot->day] = true;
        }

        return $this->availableDaysCountCache[$cacheKey] = count($days);
    }

    private function randomAlpha(): float
    {
        $rand = mt_rand() / mt_getrandmax();

        return self::RCL_ALPHA_MIN + ($rand * (self::RCL_ALPHA_MAX - self::RCL_ALPHA_MIN));
    }

    private function averageRclSize(array $sizes): float
    {
        if (empty($sizes)) {
            return 0.0;
        }

        return round(array_sum($sizes) / count($sizes), 2);
    }

    private function getStaticCandidateSlotIds(LessonData $lesson): array
    {
        if (isset($this->candidateSlotIdsByLesson[$lesson->id])) {
            return $this->candidateSlotIdsByLesson[$lesson->id];
        }

        $candidateSlotIds = [];

        // ✅ AÇÃO 09: Pré-filtrar por disponibilidade do professor e turma
        // Evita candidatos que já têm availability=0
        $professorAvailable = $this->data->availableSlotsByProfessor[$lesson->professorId] ?? [];
        $classAvailable = $this->data->availableSlotsByClass[$lesson->classId] ?? [];

        // Interseção: só slots disponíveis para AMBOS professor e turma
        $availableIntersection = array_intersect($professorAvailable, $classAvailable);

        foreach ($this->data->timeSlots as $slotId => $slot) {

            if (! $this->slotSupportsDuration($lesson, $slot)) {
                continue;
            }

            // ✅ AÇÃO 09: Early constraint filtering - skip se não está em slots disponíveis
            if (! in_array($slotId, $availableIntersection, strict: true)) {
                continue;
            }

            $candidateSlotIds[] = $slotId;
        }

        return $this->candidateSlotIdsByLesson[$lesson->id] = $candidateSlotIds;
    }

    private function findPreferredPerturbedGenePlacement(LessonData $lesson, array $teacherBusy, array $classBusy, array $assignedGenes): ?Gene
    {
        $scoredCandidates = $this->scoreFeasibleCandidates(lesson: $lesson, queue: [], currentIndex: 0, teacherBusy: $teacherBusy, classBusy: $classBusy, assignedGenes: $assignedGenes);

        if ($scoredCandidates === []) {
            return null;
        }

        $bestSlotId = array_key_first($scoredCandidates);
        $bestSlot = $bestSlotId !== null ? ($this->data->timeSlots[$bestSlotId] ?? null) : null;

        if ($bestSlot === null) {
            return null;
        }

        return new Gene(aulaId: $lesson->id, professorId: $lesson->professorId, turmaId: $lesson->classId, disciplinaId: $lesson->disciplinaId, diaSemana: $bestSlot->day, periodoDia: $bestSlot->lessonNumber, duracaoTempos: $lesson->requiredSlots);
    }

    /**
     * @param  list<int>  $ignoredIndexes
     * @return array{0: array<int, array<string, bool>>, 1: array<int, array<string, bool>>}
     */
    private function buildOccupancyMapsFromChromosome(Cromossomo $chromosome, array $ignoredIndexes = []): array
    {
        $teacherBusy = [];
        $classBusy = [];

        foreach ($chromosome->genes() as $index => $gene) {
            if (in_array($index, $ignoredIndexes, true)) {
                continue;
            }

            for ($offset = 0; $offset < $gene->duracaoTempos(); $offset++) {
                $key = $gene->diaSemana() . '-' . ($gene->periodoDia() + $offset);
                $teacherBusy[$gene->professorId()][$key] = true;
                $classBusy[$gene->turmaId()][$key] = true;
            }
        }

        return [$teacherBusy, $classBusy];
    }

    private function nogoodPenalty(LessonData $lesson, TimeSlot $slot): float
    {
        $lessonKey = $this->makeLessonSlotNogoodKey($lesson->id, $slot->id);
        $professorKey = $this->makeEntitySlotNogoodKey($lesson->professorId, $slot->id);
        $classKey = $this->makeEntitySlotNogoodKey($lesson->classId, $slot->id);

        return ((float) ($this->initialPopulationNogoods['lesson_slot'][$lessonKey] ?? 0)) * 1.8
            + ((float) ($this->initialPopulationNogoods['professor_slot'][$professorKey] ?? 0)) * 1.2
            + ((float) ($this->initialPopulationNogoods['class_slot'][$classKey] ?? 0)) * 1.2;
    }

    private function rememberNogoodPlacement(LessonData $lesson, TimeSlot $slot): void
    {
        $lessonKey = $this->makeLessonSlotNogoodKey($lesson->id, $slot->id);
        $professorKey = $this->makeEntitySlotNogoodKey($lesson->professorId, $slot->id);
        $classKey = $this->makeEntitySlotNogoodKey($lesson->classId, $slot->id);

        $this->initialPopulationNogoods['lesson_slot'][$lessonKey] = ($this->initialPopulationNogoods['lesson_slot'][$lessonKey] ?? 0) + 1;
        $this->initialPopulationNogoods['professor_slot'][$professorKey] = ($this->initialPopulationNogoods['professor_slot'][$professorKey] ?? 0) + 1;
        $this->initialPopulationNogoods['class_slot'][$classKey] = ($this->initialPopulationNogoods['class_slot'][$classKey] ?? 0) + 1;
    }

    /**
     * @param  Gene[]  $genes
     */
    private function rememberNogoodsFromAssignedGenes(array $genes): void
    {
        $teacherIndex = [];
        $classIndex = [];

        foreach ($genes as $gene) {
            if (! $gene instanceof Gene) {
                continue;
            }

            foreach ($gene->timeslots() as $period) {
                $teacherKey = $gene->professorId() . '-' . $gene->diaSemana() . '-' . $period;
                $classKey = $gene->turmaId() . '-' . $gene->diaSemana() . '-' . $period;

                if (isset($teacherIndex[$teacherKey])) {
                    $this->rememberNogoodGene($gene);
                    $this->rememberNogoodGene($teacherIndex[$teacherKey]);
                }

                if (isset($classIndex[$classKey])) {
                    $this->rememberNogoodGene($gene);
                    $this->rememberNogoodGene($classIndex[$classKey]);
                }

                $teacherIndex[$teacherKey] = $gene;
                $classIndex[$classKey] = $gene;
            }
        }
    }

    private function rememberNogoodGene(Gene $gene): void
    {
        $slotId = $this->findSlotIdByDayAndPeriod($gene->diaSemana(), $gene->periodoDia());

        if ($slotId === null) {
            return;
        }

        $lessonKey = $this->makeLessonSlotNogoodKey($gene->aulaId(), $slotId);
        $professorKey = $this->makeEntitySlotNogoodKey($gene->professorId(), $slotId);
        $classKey = $this->makeEntitySlotNogoodKey($gene->turmaId(), $slotId);

        $this->initialPopulationNogoods['lesson_slot'][$lessonKey] = ($this->initialPopulationNogoods['lesson_slot'][$lessonKey] ?? 0) + 1;
        $this->initialPopulationNogoods['professor_slot'][$professorKey] = ($this->initialPopulationNogoods['professor_slot'][$professorKey] ?? 0) + 1;
        $this->initialPopulationNogoods['class_slot'][$classKey] = ($this->initialPopulationNogoods['class_slot'][$classKey] ?? 0) + 1;
    }

    private function makeLessonSlotNogoodKey(int $lessonId, int $slotId): string
    {
        return $lessonId . ':' . $slotId;
    }

    private function makeEntitySlotNogoodKey(int $entityId, int $slotId): string
    {
        return $entityId . ':' . $slotId;
    }

    private function totalNogoodsLearned(): int
    {
        return array_sum($this->initialPopulationNogoods['lesson_slot'])
            + array_sum($this->initialPopulationNogoods['professor_slot'])
            + array_sum($this->initialPopulationNogoods['class_slot']);
    }

    private function findSlotIdByDayAndPeriod(int $day, int $period): ?int
    {
        foreach ($this->data->timeSlots as $slotId => $slot) {
            if ($slot->day === $day && $slot->lessonNumber === $period) {
                return (int) $slotId;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function countSeedHardConflicts(Cromossomo $candidate): array
    {
        $conflicts = [];
        $teacherIndex = [];
        $classIndex = [];

        foreach ($candidate->genes() as $index => $gene) {
            foreach ($gene->timeslots() as $period) {
                $teacherKey = $gene->professorId() . '-' . $gene->diaSemana() . '-' . $period;
                $classKey = $gene->turmaId() . '-' . $gene->diaSemana() . '-' . $period;

                if (isset($teacherIndex[$teacherKey])) {
                    $conflicts[$index] = $index;
                    $conflicts[$teacherIndex[$teacherKey]] = $teacherIndex[$teacherKey];
                }

                if (isset($classIndex[$classKey])) {
                    $conflicts[$index] = $index;
                    $conflicts[$classIndex[$classKey]] = $classIndex[$classKey];
                }

                $teacherIndex[$teacherKey] = $index;
                $classIndex[$classKey] = $index;
            }
        }

        return array_values($conflicts);
    }

    private function reportInitialPopulationProgress(array $payload): void
    {
        if ($this->progress === null) {
            return;
        }

        $bottlenecks = $this->buildInitialPopulationBottleneckSummary();

        $this->progress->report(array_merge([
            'phase' => 'initial_population',
            'execution_id' => $this->executionId,
        ], $payload, $bottlenecks === [] ? [] : [
            'initial_population_bottlenecks' => $bottlenecks,
        ]));
    }

    private function reportRepairProgress(string $source, array $telemetry): void
    {
        if ($this->progress === null) {
            return;
        }

        $this->progress->report([
            'phase' => 'repair',
            'stage' => 'repair_summary',
            'source' => $source,
            'execution_id' => $this->executionId,
            'hard_penalty_before' => $telemetry['hard_penalty_before'] ?? null,
            'hard_penalty_after' => $telemetry['hard_penalty_after'] ?? null,
            'soft_penalty_after' => $telemetry['soft_penalty_after'] ?? null,
            'score_after' => $telemetry['score_after'] ?? null,
            'invalid_genes_before' => $telemetry['invalid_genes_before'] ?? null,
            'invalid_genes_after' => $telemetry['invalid_genes_after'] ?? null,
            'relocations' => $telemetry['relocations'] ?? 0,
            'swaps' => $telemetry['swaps'] ?? 0,
            'local_rebuilds' => $telemetry['local_rebuilds'] ?? 0,
            'passes' => array_map(static fn (array $pass): array => [
                    'pass' => $pass['pass'],
                    'hard_penalty_before' => $pass['hard_penalty_before'],
                    'hard_penalty_after' => $pass['hard_penalty_after'],
                    'hard_penalty_delta' => $pass['hard_penalty_delta'],
                    'invalid_genes_before' => $pass['invalid_genes_before'],
                    'invalid_genes_after' => $pass['invalid_genes_after'],
                    'relocations' => $pass['relocations'],
                    'swaps' => $pass['swaps'],
                    'local_rebuilds' => $pass['local_rebuilds'],
                ], $telemetry['passes'] ?? []),
        ]);
    }

    private function shouldPublishRepairTelemetry(array $telemetry): bool
    {
        $this->repairTelemetryCounter++;

        if (($telemetry['hard_penalty_before'] ?? null) === null) {
            return false;
        }

        if (($telemetry['hard_penalty_after'] ?? INF) <= 0.0) {
            return true;
        }

        if (($telemetry['relocations'] ?? 0) > 0 || ($telemetry['swaps'] ?? 0) > 0 || ($telemetry['local_rebuilds'] ?? 0) > 0) {
            return $this->repairTelemetryCounter % self::REPAIR_TELEMETRY_SAMPLE_EVERY === 0;
        }

        return false;
    }

    private function evaluateInitialPopulationQualityGate(Cromossomo $candidate, int $attempt, int $queueSize, array $telemetry): array
    {
        $result = $this->evaluate($candidate);
        $thresholds = $this->initialQualityGateThresholds($attempt, $queueSize);

        // 🔧 PRIORIDADE 1: Forçar score >= 50 (viável) para população inicial
        $fitnessScoreViable = $result->score() >= 50.0;

        return [
            'passes' => $result->hardPenalty() <= $thresholds['max_hard_penalty']
                && $telemetry['hard_conflict_allocations'] <= $thresholds['max_hard_conflict_allocations']
                && $fitnessScoreViable,  // ← NOVO: rejeitar se inviável
            'hard_penalty' => $result->hardPenalty(),
            'soft_penalty' => $result->softPenalty(),
            'score' => $result->score(),
            'max_hard_penalty' => $thresholds['max_hard_penalty'],
            'max_hard_conflict_allocations' => $thresholds['max_hard_conflict_allocations'],
            'viable' => $fitnessScoreViable,  // ← NOVO: indicador de viabilidade
        ];
    }

    private function initialQualityGateThresholds(int $attempt, int $queueSize): array
    {
        $conflictRatio = min(self::INITIAL_QUALITY_GATE_CONFLICT_RATIO_MAX, self::INITIAL_QUALITY_GATE_CONFLICT_RATIO_START + (($attempt - 1) * self::INITIAL_QUALITY_GATE_CONFLICT_RATIO_GROWTH));
        $maxHardConflictAllocations = max(1, (int) ceil($queueSize * $conflictRatio));

        return [
            'max_hard_conflict_allocations' => $maxHardConflictAllocations,
            'max_hard_penalty' => max(self::INITIAL_QUALITY_GATE_BASE_HARD_PENALTY, $maxHardConflictAllocations * 6.0),
        ];
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @return array<string, mixed>
     */
    private function evaluateInitialPopulationFailFast(int $attempt, int $queueSize, array $telemetry): array
    {
        $thresholds = $this->initialQualityGateThresholds($attempt, $queueSize);
        $graceHardConflictAllocations = max(1, (int) ceil($queueSize * self::INITIAL_QUALITY_GATE_FAIL_FAST_GRACE_RATIO));
        $failFastLimit = $thresholds['max_hard_conflict_allocations'] + $graceHardConflictAllocations;
        $hardConflictAllocations = (int) ($telemetry['hard_conflict_allocations'] ?? 0);
        $shouldFailFast = $hardConflictAllocations > $failFastLimit;

        return [
            'should_fail_fast' => $shouldFailFast,
            'max_hard_conflict_allocations' => $thresholds['max_hard_conflict_allocations'],
            'grace_hard_conflict_allocations' => $graceHardConflictAllocations,
            'fail_fast_limit' => $failFastLimit,
            'message' => sprintf('Quality gate fail-fast na tentativa %d: hard_conflicts=%d excedeu o limite operacional %d (limite base=%d, margem=%d).', $attempt, $hardConflictAllocations, $failFastLimit, $thresholds['max_hard_conflict_allocations'], $graceHardConflictAllocations),
        ];
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @return array<string, mixed>
     */
    private function summarizeRepairTelemetry(array $telemetry): array
    {
        return [
            'hard_penalty_before' => $telemetry['hard_penalty_before'] ?? null,
            'hard_penalty_after' => $telemetry['hard_penalty_after'] ?? null,
            'soft_penalty_after' => $telemetry['soft_penalty_after'] ?? null,
            'score_after' => $telemetry['score_after'] ?? null,
            'aborted' => $telemetry['aborted'] ?? false,
            'abort_reason' => $telemetry['abort_reason'] ?? null,
            'passes_without_progress' => $telemetry['passes_without_progress'] ?? null,
            'invalid_genes_before' => $telemetry['invalid_genes_before'] ?? null,
            'invalid_genes_after' => $telemetry['invalid_genes_after'] ?? null,
            'relocations' => $telemetry['relocations'] ?? 0,
            'swaps' => $telemetry['swaps'] ?? 0,
            'local_rebuilds' => $telemetry['local_rebuilds'] ?? 0,
            'pass_count' => count($telemetry['passes'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @param  array<string, mixed>  $extra
     */
    private function recordInitialPopulationAttempt(int $attempt, string $outcome, array $telemetry, float $attemptStartedAt, array $extra = []): void
    {
        $this->initialPopulationAttemptHistory[] = array_merge([
            'attempt' => $attempt,
            'outcome' => $outcome,
            'duration_ms' => (int) round(max(0, microtime(true) - $attemptStartedAt) * 1000),
            'allocations' => (int) ($telemetry['allocations'] ?? 0),
            'queue_size' => (int) ($telemetry['queue_size'] ?? 0),
            'forced_allocations' => (int) ($telemetry['forced_allocations'] ?? 0),
            'hard_conflict_allocations' => (int) ($telemetry['hard_conflict_allocations'] ?? 0),
            'dynamic_reorders' => (int) ($telemetry['dynamic_reorders'] ?? 0),
            'regret_selections' => (int) ($telemetry['regret_selections'] ?? 0),
            'attempt_limit' => (int) ($telemetry['attempt_limit'] ?? $this->currentBuildAttemptLimit),
            'avg_rcl_size' => $this->averageRclSize($telemetry['rcl_sizes'] ?? []),
        ], $extra);

        if (count($this->initialPopulationAttemptHistory) > self::MAX_BUILD_ATTEMPTS) {
            $this->initialPopulationAttemptHistory = array_slice($this->initialPopulationAttemptHistory, -1 * self::MAX_BUILD_ATTEMPTS);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInitialPopulationBottleneckSummary(): array
    {
        $hardestLessons = array_slice($this->cachedDiagnostics['diagnostics'] ?? [], 0, 3);
        $attempts = $this->initialPopulationAttemptHistory;

        if ($hardestLessons === [] && $attempts === []) {
            return [];
        }

        $durationValues = array_map(static fn (array $attempt): int => (int) ($attempt['duration_ms'] ?? 0), $attempts);
        $forcedValues = array_map(static fn (array $attempt): int => (int) ($attempt['forced_allocations'] ?? 0), $attempts);
        $hardConflictValues = array_map(static fn (array $attempt): int => (int) ($attempt['hard_conflict_allocations'] ?? 0), $attempts);
        $dynamicReorderValues = array_map(static fn (array $attempt): int => (int) ($attempt['dynamic_reorders'] ?? 0), $attempts);
        $regretValues = array_map(static fn (array $attempt): int => (int) ($attempt['regret_selections'] ?? 0), $attempts);
        $latestAttempt = $attempts === [] ? null : $attempts[array_key_last($attempts)];
        $queueSize = (int) ($latestAttempt['queue_size'] ?? 0);

        $likelyBottlenecks = [];

        if ($this->initialPopulationCounters['fail_fast'] > 0) {
            $likelyBottlenecks[] = 'Conflitos hard estao estourando o fail-fast logo nas tentativas iniciais.';
        }

        if (($durationValues !== []) && max($durationValues) >= 180000) {
            $likelyBottlenecks[] = 'Algumas tentativas de construcao estao caras demais e ficam muito tempo sem chegar a um candidato aceitavel.';
        }

        if (($forcedValues !== []) && max($forcedValues) >= 6) {
            $likelyBottlenecks[] = 'A construcao esta recorrendo a muitas alocacoes forcadas, sinal de baixa flexibilidade real durante a montagem.';
        }

        if (($hardConflictValues !== []) && max($hardConflictValues) >= 6) {
            $likelyBottlenecks[] = 'A fila de aulas mais criticas esta convergindo para colisoes cedo demais, antes do repair conseguir ajudar.';
        }

        if ($this->totalNogoodsLearned() > 0) {
            $likelyBottlenecks[] = 'O construtor ja aprendeu padroes ruins recorrentes e esta evitando recombinar parte desses conflitos.';
        }

        if ($hardestLessons !== []) {
            $likelyBottlenecks[] = 'As aulas mais dificeis concentram muitas ocorrencias para poucos dias/slots realmente seguros.';
        }

        $optimizationSuggestions = [];

        if ($this->initialPopulationCounters['fail_fast'] > 0) {
            $optimizationSuggestions[] = 'Introduzir construcao por arrependimento (regret-based insertion) para priorizar as aulas que mais perdem qualidade quando o melhor slot some.';
            $optimizationSuggestions[] = 'Adicionar memoria de nogoods para evitar recombinar os mesmos conflitos hard entre professor, turma e bloco de tempo.';
        }

        if (($durationValues !== []) && max($durationValues) >= 180000) {
            $optimizationSuggestions[] = 'Trocar parte do GRASP puro por seeds parciais viaveis com repair incremental, em vez de reiniciar a tentativa inteira do zero.';
        }

        if (($forcedValues !== []) && max($forcedValues) >= 6) {
            $optimizationSuggestions[] = 'Reordenar dinamicamente a fila a cada bloco de alocacoes usando pressao de conflito atual, nao apenas a dificuldade estatica calculada no inicio.';
        }

        $optimizationSuggestions[] = 'Avaliar uma construcao hibrida com CP/assignment para as aulas mais restritas antes de entrar no preenchimento estocastico do restante.';

        return [
            'headline' => $likelyBottlenecks[0] ?? 'Sem gargalo dominante identificado ainda.',
            'queue_size' => $queueSize,
            'attempts_recorded' => count($attempts),
            'base_attempt_limit' => $this->currentBuildAttemptLimitBase,
            'current_attempt_limit' => $this->currentBuildAttemptLimit,
            'attempt_limit_reduced' => $this->currentBuildAttemptLimit < $this->currentBuildAttemptLimitBase,
            'attempt_limit_reduction_criteria' => $this->currentBuildAttemptLimitReductionCriteria,
            'fail_fast_count' => $this->initialPopulationCounters['fail_fast'],
            'quality_gate_rejections' => $this->initialPopulationCounters['quality_gate_rejected'],
            'construct_failures' => $this->initialPopulationCounters['construct_failed'],
            'quality_gate_passed' => $this->initialPopulationCounters['quality_gate_passed'],
            'latest_attempt' => $latestAttempt,
            'slowest_attempt_ms' => $durationValues === [] ? null : max($durationValues),
            'avg_attempt_ms' => $durationValues === [] ? null : (int) round(array_sum($durationValues) / count($durationValues)),
            'peak_forced_allocations' => $forcedValues === [] ? 0 : max($forcedValues),
            'peak_hard_conflict_allocations' => $hardConflictValues === [] ? 0 : max($hardConflictValues),
            'peak_dynamic_reorders' => $dynamicReorderValues === [] ? 0 : max($dynamicReorderValues),
            'peak_regret_selections' => $regretValues === [] ? 0 : max($regretValues),
            'nogoods_learned' => $this->totalNogoodsLearned(),
            'hardest_lessons' => array_map(static fn (array $lesson): array => [
                    'lesson_id' => $lesson['lesson_id'],
                    'candidate_slots' => $lesson['candidate_slots'],
                    'weekly_occurrences' => $lesson['weekly_occurrences'],
                    'required_slots' => $lesson['required_slots'],
                    'professor_available_days' => $lesson['professor_available_days'],
                    'class_available_days' => $lesson['class_available_days'],
                ], $hardestLessons),
            'likely_bottlenecks' => array_values(array_unique($likelyBottlenecks)),
            'optimization_suggestions' => array_values(array_unique($optimizationSuggestions)),
        ];
    }

    private function assertNotCancelled(): void
    {
        if ($this->executionId === null) {
            return;
        }

        $now = microtime(true);

        if (
            $this->lastCancellationCheckAt !== null
            && ($now - $this->lastCancellationCheckAt) < self::CANCELLATION_CHECK_INTERVAL_SECONDS
        ) {
            if (in_array($this->lastKnownExecutionStatus, ['cancel_requested', 'cancelled'], true)) {
                throw ExecutionCancelledException::forExecution($this->executionId);
            }

            return;
        }

        $status = ScheduleExecution::query()
            ->whereKey($this->executionId)
            ->value('status');

        $this->lastCancellationCheckAt = $now;
        $this->lastKnownExecutionStatus = is_string($status) ? $status : null;

        if (in_array($status, ['cancel_requested', 'cancelled'], true)) {
            throw ExecutionCancelledException::forExecution($this->executionId);
        }
    }

    private function canUseSlot(LessonData $lesson, TimeSlot $slot, array $teacherBusy, array $classBusy): bool
    {
        if (! $this->slotSupportsDuration($lesson, $slot)) {
            return false;
        }

        for ($offset = 0; $offset < $lesson->requiredSlots; $offset++) {
            $key = $slot->day . '-' . ($slot->lessonNumber + $offset);

            if (isset($teacherBusy[$lesson->professorId][$key]) || isset($classBusy[$lesson->classId][$key])) {
                return false;
            }
        }

        return true;
    }

    private function slotSupportsDuration(LessonData $lesson, TimeSlot $slot): bool
    {
        return ($slot->lessonNumber + $lesson->requiredSlots - 1) <= $this->maxLessonNumber();
    }

    private function occupySlot(LessonData $lesson, TimeSlot $slot, array &$teacherBusy, array &$classBusy): void
    {
        for ($offset = 0; $offset < $lesson->requiredSlots; $offset++) {
            $key = $slot->day . '-' . ($slot->lessonNumber + $offset);

            $teacherBusy[$lesson->professorId][$key] = true;
            $classBusy[$lesson->classId][$key] = true;
        }
    }

    private function maxLessonNumber(): int
    {
        return max(array_map(static fn (TimeSlot $slot) => $slot->lessonNumber, $this->data->timeSlots));
    }
}
