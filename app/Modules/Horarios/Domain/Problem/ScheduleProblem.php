<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Problem;

use App\Models\Alocacao;
use App\Models\ScheduleExecution;
use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Evolution\IslandModel\IslandProfile;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\AG\Support\Exceptions\ExecutionCancelledException;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\Constraints\Analysis\ConstraintFeasibilityAnalyzer;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;
use Illuminate\Support\Facades\Log;

final class ScheduleProblem implements GeneticProblem
{
    private const MAX_BUILD_ATTEMPTS = 18;

    private const MIN_BUILD_ATTEMPTS = 4;

    private const RCL_MIN_SIZE = 3;

    private const RCL_ALPHA_MIN = 0.15;

    private const RCL_ALPHA_MAX = 0.25;  // ← Reduzido de 0.45 (mais greedy, menos aleatório)

    private const RCL_ALPHA_CONSERVATIVE_MAX = 0.185;

    private const RCL_ALPHA_BALANCED_MIN = 0.175;

    private const RCL_ALPHA_BALANCED_MAX = 0.215;

    private const RCL_ALPHA_EXPLORATORY_MIN = 0.21;

    private const TELEMETRY_EVERY_ALLOCATIONS = 25;

    private const DYNAMIC_QUEUE_REORDER_EVERY_ALLOCATIONS = 6;

    private const REGRET_FRONTIER_SIZE = 8;

    private const INITIAL_QUALITY_GATE_BASE_HARD_PENALTY = 12.0;

    private const INITIAL_QUALITY_GATE_CONFLICT_RATIO_START = 0.005;

    private const INITIAL_QUALITY_GATE_CONFLICT_RATIO_GROWTH = 0.0025;

    private const INITIAL_QUALITY_GATE_CONFLICT_RATIO_MAX = 0.03;

    private const INITIAL_QUALITY_GATE_FAIL_FAST_GRACE_RATIO = 0.005;

    private const REPAIR_TELEMETRY_SAMPLE_EVERY = 25;

    private const INITIAL_QUALITY_GATE_REPAIR_TIME_BUDGET_MS = 2500;

    private const INITIAL_QUALITY_GATE_REPAIR_MAX_PASSES_WITHOUT_PROGRESS = 2;

    private const INITIAL_QUALITY_GATE_VIABLE_SCORE_THRESHOLD = 50.0;

    private const EVOLUTION_REPAIR_HEARTBEAT_INTERVAL_SECONDS = 30;

    private const LONG_RUNNING_REPAIR_LOG_INTERVAL_SECONDS = 300;

    private const CANCELLATION_CHECK_INTERVAL_SECONDS = 2;

    private const SEED_REUSE_MAX_ATTEMPTS = 4;

    private const SEED_REUSE_PERTURBATION_RATIO = 0.12;

    private const SEED_REUSE_PERTURBATION_MIN = 2;

    private const SEED_REUSE_PERTURBATION_MAX = 18;

    private const SEED_REUSE_PERTURBATION_RATIO_GROWTH = 0.08;

    private const SEED_REUSE_PERTURBATION_MAX_GROWTH = 8;

    private const HISTORICAL_SEED_EXECUTION_LOOKBACK = 5;

    private const INITIAL_QUALITY_GATE_RELAXED_FROM_ATTEMPT = 12;

    private const INITIAL_QUALITY_GATE_EMERGENCY_RELAXED_FROM_ATTEMPT = 4;

    private const INITIAL_QUALITY_GATE_EMERGENCY_REJECTION_WINDOW = 3;

    private const INITIAL_QUALITY_GATE_EMERGENCY_HARD_PENALTY_MULTIPLIER = 3.0;

    private const INITIAL_QUALITY_GATE_SKIP_REPAIR_HARD_PENALTY_MULTIPLIER = 3.0;

    private const INITIAL_QUALITY_GATE_RELAXED_BASE_HARD_PENALTY = 400.0;

    private const INITIAL_QUALITY_GATE_RELAXED_CONFLICT_RATIO_MAX = 0.15;

    private const INITIAL_QUALITY_GATE_RELAXED_VIABLE_SCORE_THRESHOLD = 0.0;

    private string $lastBuildFailure = 'Falha ao montar individuo inicial.';

    private ?array $cachedPlacementQueue = null;

    private ?array $cachedDiagnostics = null;

    private ?array $cachedHistoricalSeed = null;

    private bool $historicalSeedResolved = false;

    private array $candidateSlotIdsByLesson = [];

    private array $availableDaysCountCache = [];

    private array $availabilitySlotCountCache = [];

    /**
     * @var array<int, array<string, int|float>>
     */
    private array $baseDifficultyByLesson = [];

    private array $demandByProfessorCache = [];

    private array $demandByClassCache = [];

    private ?int $resolvedHorarioId = null;

    private bool $resolvedHorarioIdLoaded = false;

    private int $repairTelemetryCounter = 0;

    private array $lastRepairTelemetry = [];

    private ?float $lastEvolutionRepairHeartbeatAt = null;

    private ?float $lastCancellationCheckAt = null;

    private ?string $lastKnownExecutionStatus = null;

    private ?Cromossomo $lastAcceptedInitialSeed = null;

    private ?string $lastInitialPopulationSource = null;

    /**
     * @var array{
     *     grasp_attempts_used: int,
     *     quality_gate_evaluations: int,
     *     quality_gate_passed: int,
     *     quality_gate_rejected: int,
     *     source: string
     * }|null
     */
    private ?array $lastInitialPopulationBuildStats = null;

    private int $currentInitialPopulationGraspAttempts = 0;

    private int $currentInitialPopulationQualityGateEvaluations = 0;

    private int $currentInitialPopulationQualityGatePasses = 0;

    private int $currentInitialPopulationQualityGateRejections = 0;

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
     *                                   Mapping de cromossomo signature -> fitness anterior
     *                                   Usado para calcular delta em vez de reavaliar completo
     */
    private array $previousFitnessCache = [];

    /**
     * Mapa de contenção de slots: slotId → quantas aulas no restante da fila podem usar este slot.
     * Recalculado a cada reordenação dinâmica da fila.
     *
     * @var array<int, int>
     */
    private array $currentSlotContention = [];

    // ─── Sprint 4: perfil de ilha ───────────────────────────────────────────

    private IslandProfile $islandProfile = IslandProfile::Balanced;

    // ─── Melhoria 2: portfólio de construtores (alpha profile history) ───────

    /**
     * Histórico de sucesso por perfil de alpha.
     * Usado para bias epsilon-greedy na seleção de perfil.
     *
     * @var array<string, array{success: int, attempts: int}>
     */
    private array $alphaProfileHistory = [
        'conservative' => ['success' => 0, 'attempts' => 0],
        'balanced' => ['success' => 0, 'attempts' => 0],
        'exploratory' => ['success' => 0, 'attempts' => 0],
    ];

    // ─── Melhoria 1: salvage de tentativas fracassadas ──────────────────────

    /** Genes livres de conflito da melhor tentativa rejeitada até o momento. */
    private array $bestSalvageGenes = [];

    /** Penalidade hard da tentativa que gerou $bestSalvageGenes. */
    private float $bestSalvagePenalty = INF;

    // ─── Melhoria 4: nogoods persistentes entre execuções ──────────────────

    private bool $nogoodsPersistenceLoaded = false;

    private bool $hybridConstructionTriggerLogged = false;

    public function __construct(private readonly ScheduleData $data, private readonly EvaluationContextBuilder $contextBuilder, private readonly FitnessEvaluator $fitnessEvaluator, private readonly GreedyRepairOperator $repairOperator, private readonly ?ProgressReporterInterface $progress = null, private readonly ?int $executionId = null)
    {
    }

    public function createIndividual(): Cromossomo
    {
        $this->assertNotCancelled();

        $this->hybridConstructionTriggerLogged = false;

        // Melhoria 4: carrega nogoods persistidos de execuções anteriores (lazy, once)
        $this->loadNogoodsFromPersistentCache();

        $this->lastInitialPopulationSource = null;
        $this->lastInitialPopulationBuildStats = null;
        $this->currentInitialPopulationGraspAttempts = 0;
        $this->currentInitialPopulationQualityGateEvaluations = 0;
        $this->currentInitialPopulationQualityGatePasses = 0;
        $this->currentInitialPopulationQualityGateRejections = 0;
        $queue = $this->buildPlacementQueue();
        $bestRejectedAttempt = null;
        $this->currentBuildAttemptLimit = $this->resolveAdaptiveBuildAttemptLimit();

        $this->runPreventiveDiagnosis($queue);

        if ($this->lastAcceptedInitialSeed !== null) {
            $seedCandidate = $this->tryCreateIndividualFromAcceptedSeed($queue);

            if ($seedCandidate !== null) {
                $this->captureLastInitialPopulationBuildStats($this->lastInitialPopulationSource ?? 'accepted_seed');

                return $seedCandidate;
            }
        }

        $historicalSeedCandidate = $this->tryCreateIndividualFromHistoricalSeed($queue);

        if ($historicalSeedCandidate !== null) {
            $this->captureLastInitialPopulationBuildStats($this->lastInitialPopulationSource ?? 'historical_seed');

            return $historicalSeedCandidate;
        }

        // Melhoria 1: tenta warm-start a partir de genes salvageable de tentativas anteriores
        if ($this->bestSalvageGenes !== []) {
            $salvageCandidate = $this->tryCreateIndividualFromSalvage($queue);

            if ($salvageCandidate !== null) {
                $this->captureLastInitialPopulationBuildStats('salvage');

                return $salvageCandidate;
            }
        }

        for ($attempt = 1; $attempt <= $this->currentBuildAttemptLimit; $attempt++) {
            $this->assertNotCancelled();
            $this->currentInitialPopulationGraspAttempts++;
            $attemptStartedAt = microtime(true);
            $teacherBusy = [];
            $classBusy = [];
            $assignedGenes = [];
            $alphaDecision = $this->resolveAdaptiveAlpha(attempt: $attempt, queueSize: count($queue));
            $alpha = (float) $alphaDecision['alpha'];
            $telemetry = [
                'attempt' => $attempt,
                'alpha' => round($alpha, 4),
                'alpha_profile' => $alphaDecision['alpha_profile'],
                'alpha_reason' => $alphaDecision['alpha_reason'],
                'alpha_pressure_score' => $alphaDecision['alpha_pressure_score'],
                'alpha_history_stress_score' => $alphaDecision['alpha_history_stress_score'],
                'alpha_queue_pressure_score' => $alphaDecision['alpha_queue_pressure_score'],
                'queue_size' => count($queue),
                'allocations' => 0,
                'forced_allocations' => 0,
                'hard_conflict_allocations' => 0,
                'dynamic_reorders' => 0,
                'regret_selections' => 0,
                'attempt_limit' => $this->currentBuildAttemptLimit,
                'attempt_started_at' => $attemptStartedAt,
                'rcl_sizes' => [],
            ];

            Log::info('schedule.initial_population.grasp.start', $telemetry);
            $this->reportInitialPopulationProgress([
                'stage' => 'grasp_start',
                'attempt' => $attempt,
                'alpha' => round($alpha, 4),
                'alpha_policy' => $telemetry['alpha_profile'],
                'alpha_reason' => $telemetry['alpha_reason'],
                'alpha_pressure_score' => $telemetry['alpha_pressure_score'],
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
                        'execution_id' => $this->executionId,
                        'attempt' => $attempt,
                        'queue_size' => count($queue),
                        'forced_allocations' => $telemetry['forced_allocations'],
                        'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                        'max_hard_conflict_allocations' => $failFast['max_hard_conflict_allocations'],
                        'grace_hard_conflict_allocations' => $failFast['grace_hard_conflict_allocations'],
                        'elapsed_ms' => $this->attemptElapsedMs($telemetry),
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

                $candidate = new Cromossomo($assignedGenes);
                $qualityGate = $this->evaluateInitialPopulationQualityGate(
                    candidate: $candidate,
                    attempt: $attempt,
                    queueSize: count($queue),
                    telemetry: $telemetry,
                    recordEvaluation: false,
                );
                $repairSkipped = false;

                if (! $qualityGate['passes'] && ! $this->shouldSkipInitialQualityGateRepair($qualityGate, $attempt)) {
                    $candidate = $this->repairWithTelemetry($candidate, reportProgress: true, source: 'initial_population_quality_gate', progressContext: [
                        'attempt' => $attempt,
                        'queue_size' => count($queue),
                        'forced_allocations' => $telemetry['forced_allocations'],
                        'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                        'fill_ratio' => 1,
                    ]);
                    $qualityGate = $this->evaluateInitialPopulationQualityGate(candidate: $candidate, attempt: $attempt, queueSize: count($queue), telemetry: $telemetry);
                } elseif (! $qualityGate['passes']) {
                    $repairSkipped = true;
                    $this->lastRepairTelemetry = [];

                    Log::warning('schedule.initial_population.repair.skipped', [
                        'execution_id' => $this->executionId,
                        'attempt' => $attempt,
                        'queue_size' => count($queue),
                        'hard_penalty' => $qualityGate['hard_penalty'],
                        'max_hard_penalty' => $qualityGate['max_hard_penalty'],
                        'skip_multiplier' => self::INITIAL_QUALITY_GATE_SKIP_REPAIR_HARD_PENALTY_MULTIPLIER,
                    ]);

                    $this->evaluateInitialPopulationQualityGate(
                        candidate: $candidate,
                        attempt: $attempt,
                        queueSize: count($queue),
                        telemetry: $telemetry,
                    );
                } else {
                    $this->evaluateInitialPopulationQualityGate(
                        candidate: $candidate,
                        attempt: $attempt,
                        queueSize: count($queue),
                        telemetry: $telemetry,
                    );
                }

                if ($qualityGate['passes']) {
                    $this->initialPopulationCounters['quality_gate_passed']++;
                    $this->recordInitialPopulationAttempt(attempt: $attempt, outcome: 'quality_gate_passed', telemetry: $telemetry, attemptStartedAt: $attemptStartedAt, extra: [
                        'hard_penalty' => $qualityGate['hard_penalty'],
                        'soft_penalty' => $qualityGate['soft_penalty'],
                        'score' => $qualityGate['score'],
                        'max_hard_penalty' => $qualityGate['max_hard_penalty'],
                        'repair_skipped' => $repairSkipped,
                    ]);
                    Log::info('schedule.initial_population.quality_gate.passed', [
                        'execution_id' => $this->executionId,
                        'attempt' => $attempt,
                        'queue_size' => count($queue),
                        'elapsed_ms' => $this->attemptElapsedMs($telemetry),
                        'hard_penalty' => $qualityGate['hard_penalty'],
                        'soft_penalty' => $qualityGate['soft_penalty'],
                        'score' => $qualityGate['score'],
                        'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                        'max_hard_penalty' => $qualityGate['max_hard_penalty'],
                        'max_hard_conflict_allocations' => $qualityGate['max_hard_conflict_allocations'],
                        'viable' => $qualityGate['viable'],
                        'viable_score_threshold' => $qualityGate['viable_score_threshold'],
                        'repair_skipped' => $repairSkipped,
                        'repair_summary' => $this->summarizeRepairTelemetry($this->lastRepairTelemetry),
                    ]);
                    $this->reportInitialPopulationProgress([
                        'stage' => 'grasp_completed',
                        'attempt' => $attempt,
                        'alpha' => round($alpha, 4),
                        'alpha_policy' => $telemetry['alpha_profile'],
                        'alpha_reason' => $telemetry['alpha_reason'],
                        'alpha_pressure_score' => $telemetry['alpha_pressure_score'],
                        'alpha_impact' => $this->alphaImpactSummary($telemetry),
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
                        'viable' => $qualityGate['viable'],
                        'viable_score_threshold' => $qualityGate['viable_score_threshold'],
                        'repair_skipped' => $repairSkipped,
                    ]);

                    $this->lastAcceptedInitialSeed = $candidate->copy();
                    $this->lastInitialPopulationSource = 'grasp';
                    $this->captureLastInitialPopulationBuildStats('grasp');

                    // Melhoria 2: registra sucesso no portfólio de alpha profiles
                    $this->recordAlphaProfileOutcome($telemetry['alpha_profile'], true);

                    return $candidate;
                }

                $this->initialPopulationCounters['quality_gate_rejected']++;
                $this->lastBuildFailure = $this->formatInitialQualityGateFailureMessage($attempt, $qualityGate, $telemetry);
                $this->rememberNogoodsFromAssignedGenes($candidate->genes());
                $this->recordInitialPopulationAttempt(attempt: $attempt, outcome: 'quality_gate_rejected', telemetry: $telemetry, attemptStartedAt: $attemptStartedAt, extra: [
                    'hard_penalty' => $qualityGate['hard_penalty'],
                    'soft_penalty' => $qualityGate['soft_penalty'],
                    'score' => $qualityGate['score'],
                    'max_hard_penalty' => $qualityGate['max_hard_penalty'],
                    'repair_skipped' => $repairSkipped,
                    'message' => $this->lastBuildFailure,
                ]);

                // Melhoria 2: registra falha no portfólio de alpha profiles
                $this->recordAlphaProfileOutcome($telemetry['alpha_profile'], false);

                // Melhoria 1: tenta extrair gene salvageable desta tentativa fracassada
                $this->updateSalvageFromCandidate($candidate, (float) $qualityGate['hard_penalty'], count($queue));

                // Melhoria 4: persiste nogoods aprendidos nesta tentativa
                $this->persistNogoodsToPersistentCache();

                if (
                    $bestRejectedAttempt === null ||
                    $qualityGate['hard_penalty'] < $bestRejectedAttempt['hard_penalty'] ||
                    ($qualityGate['hard_penalty'] === $bestRejectedAttempt['hard_penalty'] &&
                        $qualityGate['soft_penalty'] < $bestRejectedAttempt['soft_penalty'])
                ) {
                    $bestRejectedAttempt = $qualityGate;
                }

                Log::warning('schedule.initial_population.quality_gate.rejected', [
                    'execution_id' => $this->executionId,
                    'attempt' => $attempt,
                    'queue_size' => count($queue),
                    'elapsed_ms' => $this->attemptElapsedMs($telemetry),
                    'hard_penalty' => $qualityGate['hard_penalty'],
                    'soft_penalty' => $qualityGate['soft_penalty'],
                    'score' => $qualityGate['score'],
                    'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                    'max_hard_penalty' => $qualityGate['max_hard_penalty'],
                    'max_hard_conflict_allocations' => $qualityGate['max_hard_conflict_allocations'],
                    'viable' => $qualityGate['viable'],
                    'viable_score_threshold' => $qualityGate['viable_score_threshold'],
                    'repair_skipped' => $repairSkipped,
                    'rejection_reasons' => $qualityGate['rejection_reasons'],
                    'repair_summary' => $this->summarizeRepairTelemetry($this->lastRepairTelemetry),
                ]);

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
                    'viable' => $qualityGate['viable'],
                    'viable_score_threshold' => $qualityGate['viable_score_threshold'],
                    'repair_skipped' => $repairSkipped,
                    'rejection_reasons' => $qualityGate['rejection_reasons'],
                    'message' => $this->lastBuildFailure,
                ]);
            } else {
                $this->initialPopulationCounters['construct_failed']++;
                $this->recordInitialPopulationAttempt(attempt: $attempt, outcome: 'construct_failed', telemetry: $telemetry, attemptStartedAt: $attemptStartedAt, extra: [
                    'message' => $this->lastBuildFailure,
                ]);

                Log::warning('schedule.initial_population.grasp.construct_failed', [
                    'execution_id' => $this->executionId,
                    'attempt' => $attempt,
                    'queue_size' => count($queue),
                    'elapsed_ms' => $this->attemptElapsedMs($telemetry),
                    'allocations' => $telemetry['allocations'],
                    'forced_allocations' => $telemetry['forced_allocations'],
                    'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                    'message' => $this->lastBuildFailure,
                ]);
            }

            Log::warning('schedule.initial_population.retry', [
                'execution_id' => $this->executionId,
                'attempt' => $attempt,
                'reason' => $this->lastBuildFailure,
                'alpha' => round($alpha, 4),
                'alpha_profile' => $telemetry['alpha_profile'],
                'alpha_reason' => $telemetry['alpha_reason'],
                'alpha_pressure_score' => $telemetry['alpha_pressure_score'],
                'alpha_impact' => $this->alphaImpactSummary($telemetry),
                'elapsed_ms' => $this->attemptElapsedMs($telemetry),
                'allocations' => $telemetry['allocations'],
                'queue_size' => $telemetry['queue_size'],
                'forced_allocations' => $telemetry['forced_allocations'],
                'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                'dynamic_reorders' => $telemetry['dynamic_reorders'],
                'regret_selections' => $telemetry['regret_selections'],
                'avg_rcl_size' => $this->averageRclSize($telemetry['rcl_sizes'] ?? []),
                'fill_ratio' => round($telemetry['allocations'] / max(1, count($queue)), 4),
            ]);

            $this->reportInitialPopulationProgress([
                'stage' => 'grasp_retry',
                'attempt' => $attempt,
                'alpha' => round($alpha, 4),
                'alpha_policy' => $telemetry['alpha_profile'],
                'alpha_reason' => $telemetry['alpha_reason'],
                'alpha_pressure_score' => $telemetry['alpha_pressure_score'],
                'alpha_impact' => $this->alphaImpactSummary($telemetry),
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
            $this->lastBuildFailure = sprintf('%s Melhor tentativa rejeitada: hard_penalty=%.4f, soft_penalty=%.4f, score=%.4f, motivos=%s.', $this->lastBuildFailure, $bestRejectedAttempt['hard_penalty'], $bestRejectedAttempt['soft_penalty'], $bestRejectedAttempt['score'], implode(', ', $bestRejectedAttempt['rejection_reasons'] ?? ['desconhecido']));
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

            $candidate = $this->perturbAcceptedSeed($attempt);

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
                $this->lastInitialPopulationSource = 'accepted_seed';

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

    private function tryCreateIndividualFromHistoricalSeed(array $queue): ?Cromossomo
    {
        $historicalSeed = $this->resolveHistoricalInitialSeed();

        if ($historicalSeed === null) {
            return null;
        }

        $progressContext = [
            'attempt' => 1,
            'queue_size' => count($queue),
            'forced_allocations' => 0,
            'hard_conflict_allocations' => count($this->countSeedHardConflicts($historicalSeed['chromosome'])),
            'fill_ratio' => 1,
            'seed_source' => 'historical_execution',
            'seed_execution_id' => $historicalSeed['execution_id'],
        ];

        $this->reportInitialPopulationProgress([
            'stage' => 'historical_seed_start',
            'queue_size' => count($queue),
            'seed_execution_id' => $historicalSeed['execution_id'],
            'seed_best_fitness' => $historicalSeed['best_fitness'],
        ]);

        $candidate = $this->repairWithTelemetry(
            $historicalSeed['chromosome']->copy(),
            reportProgress: true,
            source: 'initial_population_quality_gate',
            progressContext: $progressContext,
        );

        $hardConflicts = count($this->countSeedHardConflicts($candidate));
        $qualityGate = $this->evaluateInitialPopulationQualityGate(candidate: $candidate, attempt: 1, queueSize: count($queue), telemetry: [
            'hard_conflict_allocations' => $hardConflicts,
        ]);

        if ($qualityGate['passes']) {
            $this->lastAcceptedInitialSeed = $candidate->copy();
            $this->lastInitialPopulationSource = 'historical_seed';

            Log::info('schedule.initial_population.historical_seed.passed', [
                'execution_id' => $this->executionId,
                'seed_execution_id' => $historicalSeed['execution_id'],
                'queue_size' => count($queue),
                'hard_penalty' => $qualityGate['hard_penalty'],
                'soft_penalty' => $qualityGate['soft_penalty'],
                'score' => $qualityGate['score'],
                'hard_conflicts' => $hardConflicts,
            ]);

            $this->reportInitialPopulationProgress([
                'stage' => 'historical_seed_passed',
                'queue_size' => count($queue),
                'seed_execution_id' => $historicalSeed['execution_id'],
                'hard_penalty' => $qualityGate['hard_penalty'],
                'soft_penalty' => $qualityGate['soft_penalty'],
                'fitness_score' => $qualityGate['score'],
            ]);

            return $candidate;
        }

        Log::warning('schedule.initial_population.historical_seed.rejected', [
            'execution_id' => $this->executionId,
            'seed_execution_id' => $historicalSeed['execution_id'],
            'queue_size' => count($queue),
            'hard_penalty' => $qualityGate['hard_penalty'],
            'soft_penalty' => $qualityGate['soft_penalty'],
            'score' => $qualityGate['score'],
            'hard_conflicts' => $hardConflicts,
            'rejection_reasons' => $qualityGate['rejection_reasons'],
        ]);

        $this->reportInitialPopulationProgress([
            'stage' => 'historical_seed_rejected',
            'queue_size' => count($queue),
            'seed_execution_id' => $historicalSeed['execution_id'],
            'hard_penalty' => $qualityGate['hard_penalty'],
            'soft_penalty' => $qualityGate['soft_penalty'],
            'fitness_score' => $qualityGate['score'],
            'message' => 'Seed historico nao passou no quality gate atual.',
        ]);

        $previousAcceptedSeed = $this->lastAcceptedInitialSeed;
        $this->lastAcceptedInitialSeed = $historicalSeed['chromosome']->copy();
        $perturbedCandidate = $this->tryCreateIndividualFromAcceptedSeed($queue);

        if ($perturbedCandidate !== null) {
            $this->lastInitialPopulationSource = 'historical_seed';

            return $perturbedCandidate;
        }

        $this->lastAcceptedInitialSeed = $previousAcceptedSeed;

        return null;
    }

    private function perturbAcceptedSeed(int $attempt = 1): ?Cromossomo
    {
        $seed = $this->lastAcceptedInitialSeed?->copy();

        if ($seed === null || $seed->count() === 0) {
            return null;
        }

        $working = $seed->copy();
        $indexes = $this->randomSeedPerturbationIndexes($working->count(), $attempt);

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
    private function randomSeedPerturbationIndexes(int $geneCount, int $attempt = 1): array
    {
        if ($geneCount <= 0) {
            return [];
        }

        $ratio = self::SEED_REUSE_PERTURBATION_RATIO
            + (max(0, $attempt - 1) * self::SEED_REUSE_PERTURBATION_RATIO_GROWTH);
        $targetCount = (int) ceil($geneCount * min(0.55, $ratio));
        $targetCount = max(self::SEED_REUSE_PERTURBATION_MIN, $targetCount);
        $targetCap = self::SEED_REUSE_PERTURBATION_MAX
            + min(self::SEED_REUSE_PERTURBATION_MAX_GROWTH, max(0, $attempt - 1) * 2);
        $targetCount = min($targetCap, $targetCount, $geneCount);

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
        AffectedRegion $region,
    ): FitnessResult {
        $signature = $individual->signature();

        // Se não temos fitness anterior, fazer avaliação completa
        if (! isset($this->previousFitnessCache[$signature])) {
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

    public function lastInitialPopulationSource(): ?string
    {
        return $this->lastInitialPopulationSource;
    }

    /**
     * @return array{
     *     grasp_attempts_used: int,
     *     quality_gate_evaluations: int,
     *     quality_gate_passed: int,
     *     quality_gate_rejected: int,
     *     source: string
     * }
     */
    public function lastInitialPopulationBuildStats(): array
    {
        return $this->lastInitialPopulationBuildStats ?? [
            'grasp_attempts_used' => 0,
            'quality_gate_evaluations' => 0,
            'quality_gate_passed' => 0,
            'quality_gate_rejected' => 0,
            'source' => $this->lastInitialPopulationSource ?? 'unknown',
        ];
    }

    // ─── Sprint 4: perfil de ilha ─────────────────────────────────────────────

    /**
     * Sprint 4: Define o perfil da ilha que controlará os limites de alpha GRASP.
     *
     * Conservative → alpha baixo / Exploratory → alpha alto / Balanced → padrão
     */
    public function setIslandProfile(IslandProfile $profile): void
    {
        $this->islandProfile = $profile;
    }

    // ─── Melhoria 2: portfólio de construtores adaptativo ─────────────────────

    /**
     * Melhoria 2: Registra resultado de um perfil de alpha para bias futuro.
     */
    public function recordAlphaProfileOutcome(string $profile, bool $success): void
    {
        if (! isset($this->alphaProfileHistory[$profile])) {
            return;
        }

        $this->alphaProfileHistory[$profile]['attempts']++;

        if ($success) {
            $this->alphaProfileHistory[$profile]['success']++;
        }
    }

    /**
     * 🔧 PRIORIDADE 10: Avaliar com delta se AffectedRegion está disponível.
     * Para uso em contextos onde sabemos exatamente qual região foi modificada.
     *
     * Fallback automático para evaluate() se delta não for possível.
     */
    public function evaluateWithAffectedRegion(
        Cromossomo $individual,
        ?AffectedRegion $region = null,
    ): FitnessResult {
        $signature = $individual->signature();

        // Se região não foi fornecida, fazer avaliação completa
        if ($region === null) {
            $result = $this->evaluate($individual);
            $this->previousFitnessCache[$signature] = $result;

            return $result;
        }

        // Se não temos fitness anterior, fazer avaliação completa
        if (! isset($this->previousFitnessCache[$signature])) {
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
        $probe = function (Cromossomo $candidate): array {
            $result = $this->evaluate($candidate);

            return [
                'hard_penalty' => $result->hardPenalty(),
                'soft_penalty' => $result->softPenalty(),
                'score' => $result->score(),
            ];
        };
        $heartbeat = null;
        $repairStartedAt = microtime(true);
        $lastLongRunningRepairLogAt = null;

        if ($reportProgress && $source === 'initial_population_quality_gate') {
            $this->reportInitialPopulationProgress($progressContext + [
                'stage' => 'quality_gate_repair_started',
            ]);

            Log::info('schedule.initial_population.repair.started', [
                'execution_id' => $this->executionId,
                'source' => $source,
                'attempt' => $progressContext['attempt'] ?? null,
                'queue_size' => $progressContext['queue_size'] ?? null,
                'forced_allocations' => $progressContext['forced_allocations'] ?? null,
                'hard_conflict_allocations' => $progressContext['hard_conflict_allocations'] ?? null,
            ]);

            $heartbeat = function (array $heartbeatPayload) use ($progressContext, $source, $repairStartedAt, &$lastLongRunningRepairLogAt): void {
                $this->logLongRunningRepairOperation($source, $progressContext, $heartbeatPayload, $repairStartedAt, $lastLongRunningRepairLogAt);
                $this->logInitialPopulationRepairHeartbeat($source, $progressContext, $heartbeatPayload, $repairStartedAt);
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
                    'repair_target_summary_before' => $heartbeatPayload['repair_target_summary_before'] ?? null,
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
                    'repair_target_summary_before' => $heartbeatPayload['repair_target_summary_before'] ?? null,
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
            Log::info('schedule.initial_population.repair.finished', [
                'execution_id' => $this->executionId,
                'source' => $source,
                'attempt' => $progressContext['attempt'] ?? null,
                'queue_size' => $progressContext['queue_size'] ?? null,
                'elapsed_ms' => (int) round(max(0, microtime(true) - $repairStartedAt) * 1000),
                'summary' => $this->summarizeRepairTelemetry($this->lastRepairTelemetry),
            ]);

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
     * @param array<string, mixed> $progressContext
     * @param array<string, mixed> $heartbeatPayload
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

                if ($telemetry['forced_allocations'] <= 3 || $telemetry['forced_allocations'] % 5 === 0) {
                    Log::warning('schedule.initial_population.grasp.fallback', [
                        'execution_id' => $this->executionId,
                        'attempt' => $telemetry['attempt'],
                        'lesson_id' => $lesson->id,
                        'occurrence' => $occurrence,
                        'class_id' => $lesson->classId,
                        'professor_id' => $lesson->professorId,
                        'day' => $slot->day,
                        'period' => $slot->lessonNumber,
                        'forced_allocations' => $telemetry['forced_allocations'],
                        'elapsed_ms' => $this->attemptElapsedMs($telemetry),
                    ]);
                }
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
                Log::info('schedule.initial_population.grasp.progress', [
                    'execution_id' => $this->executionId,
                    'attempt' => $telemetry['attempt'],
                    'alpha' => $telemetry['alpha'],
                    'allocations' => $telemetry['allocations'],
                    'queue_size' => $telemetry['queue_size'],
                    'remaining_queue' => max(0, $telemetry['queue_size'] - $telemetry['allocations']),
                    'forced_allocations' => $telemetry['forced_allocations'],
                    'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                    'dynamic_reorders' => $telemetry['dynamic_reorders'],
                    'regret_selections' => $telemetry['regret_selections'],
                    'avg_rcl_size' => $this->averageRclSize($telemetry['rcl_sizes'] ?? []),
                    'fill_ratio' => round($telemetry['allocations'] / max(1, $telemetry['queue_size']), 4),
                    'elapsed_ms' => $this->attemptElapsedMs($telemetry),
                ]);
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
            $professorAvailability = $this->availabilitySlotCount($lesson->professorId, true);
            $classAvailability = $this->availabilitySlotCount($lesson->classId, false);
            $professorDemand = $this->totalDemandForProfessor($lesson->professorId);
            $classDemand = $this->totalDemandForClass($lesson->classId);
            $candidateSpanBase = max(1, min($professorAvailability, $classAvailability));
            $preferencePressure = count($lesson->preferredDays) + count($lesson->preferredPeriods) + ($lesson->maxPerDay !== null ? 1 : 0);
            $candidateSpanRatio = round($candidateCount / $candidateSpanBase, 4);
            $professorUtilization = round($professorDemand / max(1, $professorAvailability), 4);
            $classUtilization = round($classDemand / max(1, $classAvailability), 4);
            $structuralTightness = max($professorUtilization, $classUtilization);
            $baseDifficultyScore = $this->computeBaseDifficultyScore(
                candidateCount: $candidateCount,
                candidateSpanRatio: $candidateSpanRatio,
                professorDays: $professorDays,
                classDays: $classDays,
                professorSlack: $professorAvailability - $professorDemand,
                classSlack: $classAvailability - $classDemand,
                professorUtilization: $professorUtilization,
                classUtilization: $classUtilization,
                preferencePressure: $preferencePressure,
                requiresConsecutive: $lesson->requiresConsecutive,
                demand: $demand,
                duration: $lesson->requiredSlots,
            );

            $difficulty[$lesson->id] = [
                'candidate_count' => $candidateCount,
                'candidate_span_ratio' => $candidateSpanRatio,
                'demand' => $demand,
                'duration' => $lesson->requiredSlots,
                'weekly_occurrences' => $lesson->weeklyOccurrences,
                'professor_days' => $professorDays,
                'class_days' => $classDays,
                'professor_slack' => $professorAvailability - $professorDemand,
                'class_slack' => $classAvailability - $classDemand,
                'professor_utilization' => $professorUtilization,
                'class_utilization' => $classUtilization,
                'structural_tightness' => $structuralTightness,
                'preference_pressure' => $preferencePressure,
                'requires_consecutive' => $lesson->requiresConsecutive ? 1 : 0,
                'base_difficulty_score' => $baseDifficultyScore,
                'tie_breaker' => mt_rand(1, 1000),
            ];
        }

        $this->baseDifficultyByLesson = $difficulty;

        usort($lessons, function (LessonData $a, LessonData $b) use ($difficulty) {
            $scoreA = $difficulty[$a->id];
            $scoreB = $difficulty[$b->id];

            return
                [
                    $scoreA['candidate_count'],
                    $scoreA['candidate_span_ratio'],
                    $scoreA['professor_slack'],
                    $scoreA['class_slack'],
                    $scoreA['professor_days'],
                    $scoreA['class_days'],
                    -$scoreA['requires_consecutive'],
                    -$scoreA['preference_pressure'],
                    -$scoreA['professor_utilization'],
                    -$scoreA['class_utilization'],
                    -$scoreA['demand'],
                    -$scoreA['duration'],
                    -$scoreA['weekly_occurrences'],
                    $scoreA['tie_breaker'],
                ]
                <=>
                [
                    $scoreB['candidate_count'],
                    $scoreB['candidate_span_ratio'],
                    $scoreB['professor_slack'],
                    $scoreB['class_slack'],
                    $scoreB['professor_days'],
                    $scoreB['class_days'],
                    -$scoreB['requires_consecutive'],
                    -$scoreB['preference_pressure'],
                    -$scoreB['professor_utilization'],
                    -$scoreB['class_utilization'],
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

    /**
     * Computa quantas aulas no restante da fila podem usar cada slot atualmente livre.
     * Retorna array<slotId, contagem> — usado para detectar slots contestados.
     *
     * @return array<int, int>
     */
    private function computeSlotContention(array $queue, array $teacherBusy, array $classBusy): array
    {
        $contention = [];

        foreach ($queue as $task) {
            if (! is_array($task) || ! isset($task['lesson'])) {
                continue;
            }

            /** @var LessonData $lesson */
            $lesson = $task['lesson'];

            foreach ($this->getStaticCandidateSlotIds($lesson) as $slotId) {
                $slot = $this->data->timeSlots[$slotId] ?? null;

                if ($slot !== null && $this->canUseSlot($lesson, $slot, $teacherBusy, $classBusy)) {
                    $contention[$slotId] = ($contention[$slotId] ?? 0) + 1;
                }
            }
        }

        return $contention;
    }

    private function reorderPlacementQueueDynamically(array $queue, array $teacherBusy, array $classBusy, array $assignedGenes): array
    {
        // Recalcula contenção de slots a cada reordenação — informa quais slots são disputados.
        $this->currentSlotContention = $this->computeSlotContention($queue, $teacherBusy, $classBusy);

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
                -$left['priority']['live_tightness'],
                -$left['priority']['static_difficulty_score'],
                -$left['priority']['structural_tightness'],
                -$left['priority']['avg_slot_contention'],
                -$left['priority']['nogood_pressure'],
                -$left['priority']['same_entity_pressure'],
                -$left['priority']['same_discipline_opportunity'],
                -$left['priority']['demand'],
                $left['priority']['candidate_count'],
                $left['priority']['tie_breaker'],
            ] <=> [
                $right['priority']['feasible_slots'],
                -$right['priority']['live_tightness'],
                -$right['priority']['static_difficulty_score'],
                -$right['priority']['structural_tightness'],
                -$right['priority']['avg_slot_contention'],
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
                'static_difficulty_score' => $this->baseDifficultyScoreForLesson($lesson),
            ];

            if (
                $bestSelection === null
                || [$selection['regret'], $selection['static_difficulty_score'], -$selection['feasible_slots'], $selection['demand'], -$selection['candidate_count'], -$selection['index']]
                    > [$bestSelection['regret'], $bestSelection['static_difficulty_score'], -$bestSelection['feasible_slots'], $bestSelection['demand'], -$bestSelection['candidate_count'], -$bestSelection['index']]
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
        $totalContentionSum = 0;
        $exclusiveFeasibleSlots = 0;
        $staticDifficulty = $this->baseDifficultyMetricsForLesson($lesson);

        foreach ($this->getStaticCandidateSlotIds($lesson) as $slotId) {
            $slot = $this->data->timeSlots[$slotId] ?? null;

            if ($slot === null) {
                continue;
            }

            $nogoodPressure += $this->nogoodPenalty($lesson, $slot);

            if ($this->canUseSlot($lesson, $slot, $teacherBusy, $classBusy)) {
                $feasibleSlots++;

                $slotContest = $this->currentSlotContention[$slotId] ?? 1;
                $totalContentionSum += $slotContest;

                if ($slotContest <= 1) {
                    $exclusiveFeasibleSlots++;
                }

                if ($this->sameDisciplineAdjacencyPenalty($lesson, $slot, $assignedGenes) < 0) {
                    $sameDisciplineOpportunity++;
                }
            }
        }

        $avgSlotContention = $feasibleSlots > 0
            ? round($totalContentionSum / $feasibleSlots, 2)
            : 0.0;

        // Tensão dinâmica: quanto da demanda restante do professor/turma ainda precisa ser alocada
        // em relação aos slots ainda disponíveis. Mais próximo de 1.0 = mais crítico.
        $professorAssigned = count($teacherBusy[$lesson->professorId] ?? []);
        $classAssigned = count($classBusy[$lesson->classId] ?? []);
        $profTotalAvailable = $this->availabilitySlotCount($lesson->professorId, true);
        $classTotalAvailable = $this->availabilitySlotCount($lesson->classId, false);
        $profRemainingDemand = max(0, $this->totalDemandForProfessor($lesson->professorId) - $professorAssigned);
        $profRemainingSlots = max(1, $profTotalAvailable - $professorAssigned);
        $classRemainingDemand = max(0, $this->totalDemandForClass($lesson->classId) - $classAssigned);
        $classRemainingSlots = max(1, $classTotalAvailable - $classAssigned);
        $liveTightness = round(max(
            $profRemainingDemand / $profRemainingSlots,
            $classRemainingDemand / $classRemainingSlots,
        ), 4);

        return [
            'feasible_slots' => $feasibleSlots,
            'live_tightness' => $liveTightness,
            'static_difficulty_score' => $staticDifficulty['base_difficulty_score'] ?? 0.0,
            'structural_tightness' => $staticDifficulty['structural_tightness'] ?? 0.0,
            'avg_slot_contention' => $avgSlotContention,
            'exclusive_feasible_slots' => $exclusiveFeasibleSlots,
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
            if (! empty($this->cachedDiagnostics['structural_infeasibilities'])) {
                $first = $this->cachedDiagnostics['structural_infeasibilities'][0];
                $this->lastBuildFailure = $first['message'];

                throw new \RuntimeException($this->lastBuildFailure);
            }

            if (! empty($this->cachedDiagnostics['constraint_infeasibilities'])) {
                $first = $this->cachedDiagnostics['constraint_infeasibilities'][0];
                $this->lastBuildFailure = $first['message'];

                throw new \RuntimeException($this->lastBuildFailure);
            }

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

        $structuralInfeasibilities = $this->buildStructuralInfeasibilityDiagnostics();
        $classLoadPressure = $this->buildEntityLoadPressureSummary(false);
        $professorLoadPressure = $this->buildEntityLoadPressureSummary(true);
        $constraintFeasibility = (new ConstraintFeasibilityAnalyzer())->analyze($this->data);
        $constraintInfeasibilities = $constraintFeasibility->blockingIssues();
        $constraintWarnings = $constraintFeasibility->warnings();

        Log::info('schedule.initial_population.diagnosis', [
            'queue_size' => count($queue),
            'hardest_lessons' => array_slice($diagnostics, 0, 10),
            'structural_infeasibilities' => $structuralInfeasibilities,
            'constraint_infeasibilities' => array_slice($constraintInfeasibilities, 0, 10),
            'constraint_warnings' => array_slice($constraintWarnings, 0, 10),
            'constraint_risk_contribution' => $constraintFeasibility->riskContribution(),
            'tightest_classes' => array_slice($classLoadPressure, 0, 5),
            'tightest_professors' => array_slice($professorLoadPressure, 0, 5),
        ]);
        $this->reportInitialPopulationProgress([
            'stage' => 'diagnosis',
            'queue_size' => count($queue),
            'hardest_lessons' => array_slice($diagnostics, 0, 5),
            'structural_infeasibilities' => array_slice($structuralInfeasibilities, 0, 5),
            'constraint_infeasibilities' => array_slice($constraintInfeasibilities, 0, 5),
            'constraint_warnings' => array_slice($constraintWarnings, 0, 5),
        ]);

        $blocked = array_filter($diagnostics, static fn (array $item) => $item['candidate_slots'] < $item['weekly_occurrences']);
        $blocked = array_values($blocked);
        $this->cachedDiagnostics = [
            'diagnostics' => $diagnostics,
            'blocked' => $blocked,
            'structural_infeasibilities' => $structuralInfeasibilities,
            'constraint_infeasibilities' => $constraintInfeasibilities,
            'constraint_warnings' => $constraintWarnings,
            'tightest_classes' => $classLoadPressure,
            'tightest_professors' => $professorLoadPressure,
        ];

        if (! empty($structuralInfeasibilities)) {
            $first = $structuralInfeasibilities[0];
            $this->lastBuildFailure = $first['message'];

            throw new \RuntimeException($this->lastBuildFailure);
        }

        if (! empty($constraintInfeasibilities)) {
            $first = $constraintInfeasibilities[0];
            $this->lastBuildFailure = $first['message'];

            throw new \RuntimeException($this->lastBuildFailure);
        }

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

        // Penalidade leve por contenção: prefere slots menos disputados, preservando os
        // mais contestados para aulas que têm menos alternativas disponíveis.
        if (! empty($this->currentSlotContention)) {
            $contention = $this->currentSlotContention[$slot->id] ?? 1;
            $score += ($contention - 1) * 0.12;
        }

        $score += mt_rand(0, 100) / 1000;

        return $score;
    }

    private function futureFlexibilityScore(TimeSlot $slot, array $queue, int $currentIndex, LessonData $currentLesson, array $teacherBusy, array $classBusy): float
    {
        $teacherBusySimulated = $teacherBusy;
        $classBusySimulated = $classBusy;

        $this->occupySlot($currentLesson, $slot, $teacherBusySimulated, $classBusySimulated);

        $rewardScore = 0.0;
        $blockingPenalty = 0.0;

        foreach (array_slice($queue, $currentIndex + 1, 3) as $task) {
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

                    if ($options >= 7) {
                        break; // saída antecipada: já temos evidência suficiente
                    }
                }
            }

            $rewardScore += min($options, 6);

            // Melhoria 3: penalidade de lookahead crítico
            // Se esta alocação deixaria a aula seguinte sem nenhum slot viável → grande penalidade.
            if ($options === 0) {
                $blockingPenalty += 15.0; // bloqueia completamente: penalidade crítica
            } elseif ($options === 1) {
                $blockingPenalty += 2.5;  // quase bloqueia: penalidade moderada
            }
        }

        // Retorna reward menos penalty. Quando penalty domina, o valor fica negativo,
        // e como scoreCandidateSlot faz "score -= futureFlexibilityScore()", isso
        // se converte numa penalidade positiva no score total do slot.
        return $rewardScore - $blockingPenalty;
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

    private function availabilitySlotCount(int $entityId, bool $isProfessor): int
    {
        $cacheKey = ($isProfessor ? 'professor:' : 'class:') . $entityId;

        if (array_key_exists($cacheKey, $this->availabilitySlotCountCache)) {
            return $this->availabilitySlotCountCache[$cacheKey];
        }

        $slotIds = $isProfessor
            ? ($this->data->availableSlotsByProfessor[$entityId] ?? [])
            : ($this->data->availableSlotsByClass[$entityId] ?? []);

        return $this->availabilitySlotCountCache[$cacheKey] = count($slotIds);
    }

    private function totalDemandForProfessor(int $professorId): int
    {
        if (array_key_exists($professorId, $this->demandByProfessorCache)) {
            return $this->demandByProfessorCache[$professorId];
        }

        $lessonIds = $this->data->lessonsByProfessor[$professorId] ?? [];
        $demand = 0;

        foreach ($lessonIds as $lessonId) {
            $lesson = $this->data->lessons[$lessonId] ?? null;

            if ($lesson === null) {
                continue;
            }

            $demand += $lesson->weeklyOccurrences * $lesson->requiredSlots;
        }

        return $this->demandByProfessorCache[$professorId] = $demand;
    }

    private function totalDemandForClass(int $classId): int
    {
        if (array_key_exists($classId, $this->demandByClassCache)) {
            return $this->demandByClassCache[$classId];
        }

        return $this->demandByClassCache[$classId] = (int) ($this->data->expectedLoadByLesson[$classId] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildStructuralInfeasibilityDiagnostics(): array
    {
        $issues = [];

        foreach ($this->data->classes as $classId => $class) {
            $requiredLoad = $this->totalDemandForClass($classId);
            $availableSlots = $this->availabilitySlotCount($classId, false);

            if ($requiredLoad > $availableSlots) {
                $issues[] = [
                    'type' => 'class_overload',
                    'entity_id' => $classId,
                    'required_load' => $requiredLoad,
                    'available_slots' => $availableSlots,
                    'slack' => $availableSlots - $requiredLoad,
                    'message' => "Diagnostico preventivo: turma {$classId} exige {$requiredLoad} tempos para apenas {$availableSlots} slots disponiveis.",
                ];
            }
        }

        foreach ($this->data->professors as $professorId => $professor) {
            $requiredLoad = $this->totalDemandForProfessor($professorId);
            $availableSlots = $this->availabilitySlotCount($professorId, true);

            if ($requiredLoad > $availableSlots) {
                $issues[] = [
                    'type' => 'professor_overload',
                    'entity_id' => $professorId,
                    'required_load' => $requiredLoad,
                    'available_slots' => $availableSlots,
                    'slack' => $availableSlots - $requiredLoad,
                    'message' => "Diagnostico preventivo: professor {$professorId} exige {$requiredLoad} tempos para apenas {$availableSlots} slots disponiveis.",
                ];
            }
        }

        usort($issues, static fn (array $left, array $right): int => [$left['slack'], $left['available_slots']] <=> [$right['slack'], $right['available_slots']]);

        return array_values($issues);
    }

    /**
     * @return list<array<string, int|float|bool>>
     */
    private function buildEntityLoadPressureSummary(bool $isProfessor): array
    {
        $summary = [];
        $entities = $isProfessor ? $this->data->professors : $this->data->classes;

        foreach ($entities as $entityId => $entity) {
            $requiredLoad = $isProfessor
                ? $this->totalDemandForProfessor($entityId)
                : $this->totalDemandForClass($entityId);
            $availableSlots = $this->availabilitySlotCount($entityId, $isProfessor);

            $summary[] = [
                'entity_id' => $entityId,
                'required_load' => $requiredLoad,
                'available_slots' => $availableSlots,
                'slack' => $availableSlots - $requiredLoad,
                'utilization' => round($requiredLoad / max(1, $availableSlots), 4),
                'overloaded' => $requiredLoad > $availableSlots,
            ];
        }

        usort($summary, static fn (array $left, array $right): int => [$left['slack'], -$left['utilization'], $left['entity_id']] <=> [$right['slack'], -$right['utilization'], $right['entity_id']]);

        return $summary;
    }

    /**
     * @return array{
     *     alpha: float,
     *     alpha_min: float,
     *     alpha_max: float,
     *     alpha_profile: string,
     *     alpha_reason: string,
     *     alpha_pressure_score: float,
     *     alpha_history_stress_score: float,
     *     alpha_queue_pressure_score: float
     * }
     */
    private function resolveAdaptiveAlpha(int $attempt, int $queueSize): array
    {
        $queuePressureScore = $this->initialQueuePressureScore($queueSize);
        $historyStressScore = $this->recentBuildStressScore();
        $attemptPressureScore = min(1.0, max(0.0, ($attempt - 1) / max(1, $this->currentBuildAttemptLimit - 1)));
        $pressureScore = round(min(1.0, ($queuePressureScore * 0.45) + ($historyStressScore * 0.40) + ($attemptPressureScore * 0.15)), 4);

        $profile = 'balanced';
        $alphaMin = self::RCL_ALPHA_BALANCED_MIN;
        $alphaMax = self::RCL_ALPHA_BALANCED_MAX;
        $reason = 'Pressao intermediaria; equilibrio entre exploracao e convergencia.';

        if ($pressureScore >= 0.68) {
            $profile = 'conservative';
            $alphaMin = self::RCL_ALPHA_MIN;
            $alphaMax = self::RCL_ALPHA_CONSERVATIVE_MAX;
            $reason = 'Pressao alta de fila/historico; priorizando construcao mais gulosa para reduzir colisoes precoces.';
        } elseif ($pressureScore < 0.38) {
            $profile = 'exploratory';
            $alphaMin = self::RCL_ALPHA_EXPLORATORY_MIN;
            $alphaMax = self::RCL_ALPHA_MAX;
            $reason = 'Pressao controlada; ampliando exploracao para diversificar sementes.';
        }

        if ($attempt >= (int) ceil($this->currentBuildAttemptLimit * 0.75) && $historyStressScore < 0.35 && $queuePressureScore < 0.5) {
            $profile = 'exploratory';
            $alphaMin = self::RCL_ALPHA_EXPLORATORY_MIN;
            $alphaMax = self::RCL_ALPHA_MAX;
            $reason = 'Fim da janela de tentativas com baixo estresse recente; aumentando diversidade para escapar de padrao local.';
        }

        // Melhoria 2: bias pelo portfólio (epsilon-greedy, 15% de exploração)
        $portfolioProfile = $this->portfolioBiasedAlphaProfile();

        if ($portfolioProfile !== null && (mt_rand() / mt_getrandmax()) >= 0.15) {
            if ($portfolioProfile !== $profile) {
                $profile = $portfolioProfile;
                [$alphaMin, $alphaMax] = match ($profile) {
                    'conservative' => [self::RCL_ALPHA_MIN, self::RCL_ALPHA_CONSERVATIVE_MAX],
                    'exploratory' => [self::RCL_ALPHA_EXPLORATORY_MIN, self::RCL_ALPHA_MAX],
                    default => [self::RCL_ALPHA_BALANCED_MIN, self::RCL_ALPHA_BALANCED_MAX],
                };
                $reason .= ' [portfolio_bias:' . $profile . ']';
            }
        }

        // Sprint 4: aplica limites do perfil de ilha (restringe ou expande a faixa de alpha)
        if ($this->islandProfile !== IslandProfile::Balanced) {
            $profileMin = $this->islandProfile->graspAlphaMin();
            $profileMax = $this->islandProfile->graspAlphaMax();

            // Cruza a faixa calculada com a faixa permitida pelo perfil da ilha
            $alphaMin = max($alphaMin, $profileMin);
            $alphaMax = min($alphaMax, $profileMax);

            if ($alphaMin > $alphaMax) {
                // Sem sobreposição: usa a faixa do perfil de ilha como autoridade
                $alphaMin = $profileMin;
                $alphaMax = $profileMax;
                $profile = $this->islandProfile->value;
            }

            $reason .= ' [ilha:' . $this->islandProfile->value . ']';
        }

        $alpha = $this->randomAlphaBetween($alphaMin, $alphaMax);

        return [
            'alpha' => $alpha,
            'alpha_min' => $alphaMin,
            'alpha_max' => $alphaMax,
            'alpha_profile' => $profile,
            'alpha_reason' => $reason,
            'alpha_pressure_score' => $pressureScore,
            'alpha_history_stress_score' => $historyStressScore,
            'alpha_queue_pressure_score' => $queuePressureScore,
        ];
    }

    private function randomAlphaBetween(float $min, float $max): float
    {
        $rand = mt_rand() / mt_getrandmax();

        return round($min + ($rand * ($max - $min)), 4);
    }

    private function initialQueuePressureScore(int $queueSize): float
    {
        $hardest = $this->cachedDiagnostics['diagnostics'][0] ?? null;
        $scarcityScore = 0.0;

        if (is_array($hardest)) {
            $candidateSlots = (int) ($hardest['candidate_slots'] ?? 0);
            $weeklyOccurrences = (int) ($hardest['weekly_occurrences'] ?? 1);
            $scarcityScore = min(1.0, $weeklyOccurrences / max(1, $candidateSlots));
        }

        $queueLoadScore = min(1.0, $queueSize / max(1, $this->data->totalTimeSlots));

        return round(($scarcityScore * 0.65) + ($queueLoadScore * 0.35), 4);
    }

    private function recentBuildStressScore(): float
    {
        $recentAttempts = array_slice($this->initialPopulationAttemptHistory, -6);

        if ($recentAttempts === []) {
            return 0.0;
        }

        $recentCount = count($recentAttempts);
        $failFastRatio = count(array_filter($recentAttempts, static fn (array $attempt): bool => ($attempt['outcome'] ?? null) === 'fail_fast')) / max(1, $recentCount);
        $qualityGateRejectedRatio = count(array_filter($recentAttempts, static fn (array $attempt): bool => ($attempt['outcome'] ?? null) === 'quality_gate_rejected')) / max(1, $recentCount);

        $forcedRatios = array_map(static fn (array $attempt): float => ((int) ($attempt['forced_allocations'] ?? 0)) / max(1, (int) ($attempt['queue_size'] ?? 0)), $recentAttempts);
        $hardConflictRatios = array_map(static fn (array $attempt): float => ((int) ($attempt['hard_conflict_allocations'] ?? 0)) / max(1, (int) ($attempt['queue_size'] ?? 0)), $recentAttempts);

        $avgForcedRatio = $forcedRatios === [] ? 0.0 : (array_sum($forcedRatios) / count($forcedRatios));
        $avgHardConflictRatio = $hardConflictRatios === [] ? 0.0 : (array_sum($hardConflictRatios) / count($hardConflictRatios));

        return round(min(1.0, ($failFastRatio * 0.45) + ($qualityGateRejectedRatio * 0.20) + ($avgForcedRatio * 0.20) + ($avgHardConflictRatio * 0.15)), 4);
    }

    /**
     * @param array<string, mixed> $telemetry
     * @return array<string, float|int>
     */
    private function alphaImpactSummary(array $telemetry): array
    {
        $queueSize = max(1, (int) ($telemetry['queue_size'] ?? 0));
        $forcedAllocations = (int) ($telemetry['forced_allocations'] ?? 0);
        $hardConflicts = (int) ($telemetry['hard_conflict_allocations'] ?? 0);
        $forcedRatio = round($forcedAllocations / $queueSize, 4);
        $hardConflictRatio = round($hardConflicts / $queueSize, 4);
        $fillRatio = round(((int) ($telemetry['allocations'] ?? 0)) / $queueSize, 4);
        $effectiveness = round(max(0.0, 1.0 - (($forcedRatio * 0.55) + ($hardConflictRatio * 0.45))), 4);

        return [
            'forced_ratio' => $forcedRatio,
            'hard_conflict_ratio' => $hardConflictRatio,
            'fill_ratio' => $fillRatio,
            'avg_rcl_size' => $this->averageRclSize($telemetry['rcl_sizes'] ?? []),
            'effectiveness' => $effectiveness,
        ];
    }

    private function randomAlpha(): float
    {
        return $this->randomAlphaBetween(self::RCL_ALPHA_MIN, self::RCL_ALPHA_MAX);
    }

    private function averageRclSize(array $sizes): float
    {
        if (empty($sizes)) {
            return 0.0;
        }

        return round(array_sum($sizes) / count($sizes), 2);
    }

    private function computeBaseDifficultyScore(
        int $candidateCount,
        float $candidateSpanRatio,
        int $professorDays,
        int $classDays,
        int $professorSlack,
        int $classSlack,
        float $professorUtilization,
        float $classUtilization,
        int $preferencePressure,
        bool $requiresConsecutive,
        int $demand,
        int $duration,
    ): float {
        $daysPressure = max(0.0, 5.0 - min($professorDays, $classDays));
        $slackPressure = max(0, 6 - min($professorSlack, $classSlack));

        return round(
            (40 / max(1, $candidateCount))
            + (max(0.0, 1.0 - $candidateSpanRatio) * 14)
            + ($daysPressure * 1.8)
            + ($slackPressure * 1.2)
            + (max($professorUtilization, $classUtilization) * 10)
            + ($preferencePressure * 1.5)
            + ($requiresConsecutive ? 5.0 : 0.0)
            + ($demand * 0.35)
            + ($duration * 1.5),
            4,
        );
    }

    /**
     * @return array<string, int|float>
     */
    private function baseDifficultyMetricsForLesson(LessonData $lesson): array
    {
        return $this->baseDifficultyByLesson[$lesson->id] ?? [];
    }

    private function baseDifficultyScoreForLesson(LessonData $lesson): float
    {
        return (float) ($this->baseDifficultyByLesson[$lesson->id]['base_difficulty_score'] ?? 0.0);
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

    /**
     * @return array{chromosome:Cromossomo,execution_id:int,best_fitness:float|null}|null
     */
    private function resolveHistoricalInitialSeed(): ?array
    {
        if ($this->historicalSeedResolved) {
            return $this->cachedHistoricalSeed;
        }

        $this->historicalSeedResolved = true;

        $horarioId = $this->resolveHorarioId();

        if ($horarioId === null) {
            return null;
        }

        $executions = ScheduleExecution::query()
            ->select(['id', 'horario_id', 'best_fitness', 'status', 'end_time'])
            ->where('horario_id', $horarioId)
            ->where('status', 'finished')
            ->when($this->executionId !== null, fn ($query) => $query->where('id', '!=', $this->executionId))
            ->whereHas('alocacoes')
            ->with(['alocacoes' => function ($query): void {
                $query->select([
                    'id',
                    'execution_id',
                    'aula_id',
                    'turma_id',
                    'disciplina_id',
                    'professor_id',
                    'dia_semana',
                    'tempo',
                    'duracao_tempos',
                ])->orderBy('aula_id')->orderBy('dia_semana')->orderBy('tempo');
            }])
            ->orderByDesc('best_fitness')
            ->orderByDesc('end_time')
            ->limit(self::HISTORICAL_SEED_EXECUTION_LOOKBACK)
            ->get();

        $bestCandidate = null;

        foreach ($executions as $execution) {
            $candidate = $this->buildHistoricalSeedFromExecution($execution);

            if ($candidate === null) {
                continue;
            }

            $currentFitness = $this->evaluate($candidate);
            $hardConflicts = count($this->countSeedHardConflicts($candidate));
            $seedMetrics = [
                'chromosome' => $candidate,
                'execution_id' => (int) $execution->id,
                'best_fitness' => $execution->best_fitness,
                'current_hard_conflicts' => $hardConflicts,
                'current_hard_penalty' => $currentFitness->hardPenalty(),
                'current_soft_penalty' => $currentFitness->softPenalty(),
                'current_score' => $currentFitness->score(),
            ];

            if (
                $bestCandidate === null
                || [
                    $seedMetrics['current_hard_conflicts'],
                    $seedMetrics['current_hard_penalty'],
                    -1 * (float) ($seedMetrics['best_fitness'] ?? 0.0),
                    -1 * $seedMetrics['current_score'],
                ] < [
                    $bestCandidate['current_hard_conflicts'],
                    $bestCandidate['current_hard_penalty'],
                    -1 * (float) ($bestCandidate['best_fitness'] ?? 0.0),
                    -1 * $bestCandidate['current_score'],
                ]
            ) {
                $bestCandidate = $seedMetrics;
            }

            Log::info('schedule.initial_population.historical_seed.candidate', [
                'execution_id' => $this->executionId,
                'seed_execution_id' => $execution->id,
                'seed_best_fitness' => $execution->best_fitness,
                'current_hard_conflicts' => $hardConflicts,
                'current_hard_penalty' => $currentFitness->hardPenalty(),
                'current_soft_penalty' => $currentFitness->softPenalty(),
                'current_score' => $currentFitness->score(),
            ]);
        }

        if ($bestCandidate !== null) {
            $this->cachedHistoricalSeed = $bestCandidate;

            Log::info('schedule.initial_population.historical_seed.loaded', [
                'execution_id' => $this->executionId,
                'seed_execution_id' => $bestCandidate['execution_id'],
                'seed_best_fitness' => $bestCandidate['best_fitness'],
                'gene_count' => $bestCandidate['chromosome']->count(),
                'current_hard_conflicts' => $bestCandidate['current_hard_conflicts'],
                'current_hard_penalty' => $bestCandidate['current_hard_penalty'],
                'current_score' => $bestCandidate['current_score'],
            ]);

            return $this->cachedHistoricalSeed;
        }

        return null;
    }

    private function resolveHorarioId(): ?int
    {
        if ($this->resolvedHorarioIdLoaded) {
            return $this->resolvedHorarioId;
        }

        $this->resolvedHorarioIdLoaded = true;

        if ($this->executionId === null) {
            return null;
        }

        $this->resolvedHorarioId = ScheduleExecution::query()
            ->whereKey($this->executionId)
            ->value('horario_id');

        return $this->resolvedHorarioId;
    }

    private function buildHistoricalSeedFromExecution(ScheduleExecution $execution): ?Cromossomo
    {
        $genes = [];
        $expectedOccurrencesByLesson = [];

        foreach ($this->data->lessons as $lessonId => $lesson) {
            $expectedOccurrencesByLesson[$lessonId] = $lesson->weeklyOccurrences;
        }

        foreach ($execution->alocacoes as $allocation) {
            if (! $allocation instanceof Alocacao) {
                continue;
            }

            $lesson = $this->data->lessons[$allocation->aula_id] ?? null;

            if ($lesson === null) {
                Log::warning('schedule.initial_population.historical_seed.skipped', [
                    'execution_id' => $this->executionId,
                    'seed_execution_id' => $execution->id,
                    'reason' => 'missing_lesson_in_current_dataset',
                    'aula_id' => $allocation->aula_id,
                ]);

                return null;
            }

            $day = $this->mapPersistedDayToInternalDay($allocation->dia_semana);

            if ($day === null) {
                Log::warning('schedule.initial_population.historical_seed.skipped', [
                    'execution_id' => $this->executionId,
                    'seed_execution_id' => $execution->id,
                    'reason' => 'invalid_persisted_day',
                    'dia_semana' => $allocation->dia_semana,
                ]);

                return null;
            }

            if (
                (int) $allocation->professor_id !== $lesson->professorId
                || (int) $allocation->turma_id !== $lesson->classId
                || (int) $allocation->disciplina_id !== $lesson->disciplinaId
                || (int) $allocation->duracao_tempos !== $lesson->requiredSlots
            ) {
                Log::warning('schedule.initial_population.historical_seed.skipped', [
                    'execution_id' => $this->executionId,
                    'seed_execution_id' => $execution->id,
                    'reason' => 'lesson_signature_changed',
                    'aula_id' => $allocation->aula_id,
                ]);

                return null;
            }

            $slot = $this->resolveSlotByDayAndPeriod($day, (int) $allocation->tempo);

            if ($slot === null || ! $this->slotSupportsDuration($lesson, $slot)) {
                Log::warning('schedule.initial_population.historical_seed.skipped', [
                    'execution_id' => $this->executionId,
                    'seed_execution_id' => $execution->id,
                    'reason' => 'slot_not_supported_anymore',
                    'aula_id' => $allocation->aula_id,
                    'day' => $day,
                    'period' => $allocation->tempo,
                ]);

                return null;
            }

            if (! in_array($slot->id, $this->getStaticCandidateSlotIds($lesson), true)) {
                Log::warning('schedule.initial_population.historical_seed.skipped', [
                    'execution_id' => $this->executionId,
                    'seed_execution_id' => $execution->id,
                    'reason' => 'slot_not_available_under_current_restrictions',
                    'aula_id' => $allocation->aula_id,
                    'day' => $day,
                    'period' => $allocation->tempo,
                ]);

                return null;
            }

            $genes[] = new Gene(
                aulaId: $lesson->id,
                professorId: $lesson->professorId,
                turmaId: $lesson->classId,
                disciplinaId: $lesson->disciplinaId,
                diaSemana: $day,
                periodoDia: (int) $allocation->tempo,
                duracaoTempos: $lesson->requiredSlots,
            );

            $expectedOccurrencesByLesson[$lesson->id]--;
        }

        if ($genes === []) {
            return null;
        }

        foreach ($expectedOccurrencesByLesson as $lessonId => $remainingOccurrences) {
            if ($remainingOccurrences !== 0) {
                Log::warning('schedule.initial_population.historical_seed.skipped', [
                    'execution_id' => $this->executionId,
                    'seed_execution_id' => $execution->id,
                    'reason' => 'occurrence_count_mismatch',
                    'aula_id' => $lessonId,
                    'remaining_occurrences' => $remainingOccurrences,
                ]);

                return null;
            }
        }

        return new Cromossomo($genes);
    }

    private function mapPersistedDayToInternalDay(string $persistedDay): ?int
    {
        return match ($persistedDay) {
            'segunda' => 1,
            'terca' => 2,
            'quarta' => 3,
            'quinta' => 4,
            'sexta' => 5,
            default => null,
        };
    }

    private function resolveSlotByDayAndPeriod(int $day, int $period): ?TimeSlot
    {
        foreach ($this->data->timeSlots as $slot) {
            if ($slot->day === $day && $slot->lessonNumber === $period) {
                return $slot;
            }
        }

        return null;
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
     * @param list<int> $ignoredIndexes
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
     * @param Gene[] $genes
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
            'repair_target_summary_before' => $telemetry['repair_target_summary_before'] ?? null,
            'repair_target_summary_after' => $telemetry['repair_target_summary_after'] ?? null,
            'unrepairable_workload_classes' => $telemetry['unrepairable_workload_classes'] ?? [],
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

    private function evaluateInitialPopulationQualityGate(Cromossomo $candidate, int $attempt, int $queueSize, array $telemetry, bool $recordEvaluation = true): array
    {
        $result = $this->evaluate($candidate);
        $thresholds = $this->initialQualityGateThresholds($attempt, $queueSize);

        $isRelaxedPhase = $this->isInitialQualityGateRelaxedPhase($attempt);

        $viableScoreThreshold = $isRelaxedPhase
            ? self::INITIAL_QUALITY_GATE_RELAXED_VIABLE_SCORE_THRESHOLD
            : self::INITIAL_QUALITY_GATE_VIABLE_SCORE_THRESHOLD;

        $fitnessScoreViable = $result->score() >= $viableScoreThreshold;
        $rejectionReasons = [];

        if ($result->hardPenalty() > $thresholds['max_hard_penalty']) {
            $rejectionReasons[] = 'hard_penalty_above_limit';
        }

        if (($telemetry['hard_conflict_allocations'] ?? 0) > $thresholds['max_hard_conflict_allocations']) {
            $rejectionReasons[] = 'hard_conflicts_above_limit';
        }

        if (! $fitnessScoreViable) {
            $rejectionReasons[] = 'score_below_viable_threshold';
        }

        $qualityGate = [
            'passes' => $result->hardPenalty() <= $thresholds['max_hard_penalty']
                && ($telemetry['hard_conflict_allocations'] ?? 0) <= $thresholds['max_hard_conflict_allocations'],
            'hard_penalty' => $result->hardPenalty(),
            'soft_penalty' => $result->softPenalty(),
            'score' => $result->score(),
            'max_hard_penalty' => $thresholds['max_hard_penalty'],
            'max_hard_conflict_allocations' => $thresholds['max_hard_conflict_allocations'],
            'viable' => $fitnessScoreViable,
            'viable_score_threshold' => $viableScoreThreshold,
            'rejection_reasons' => $rejectionReasons,
            'relaxed_phase' => $isRelaxedPhase,
        ];

        if ($recordEvaluation) {
            $this->currentInitialPopulationQualityGateEvaluations++;

            if ($qualityGate['passes']) {
                $this->currentInitialPopulationQualityGatePasses++;
            } else {
                $this->currentInitialPopulationQualityGateRejections++;
            }
        }

        return $qualityGate;
    }

    /**
     * @param array<string, mixed> $qualityGate
     * @param array<string, mixed> $telemetry
     */
    private function formatInitialQualityGateFailureMessage(int $attempt, array $qualityGate, array $telemetry): string
    {
        $reasons = $qualityGate['rejection_reasons'] ?? [];
        $reasonSummary = $reasons === [] ? 'motivo_nao_identificado' : implode(', ', $reasons);

        return sprintf(
            'Quality gate rejeitou tentativa %d: motivos=%s; hard_penalty=%.4f (limite=%.4f), hard_conflicts=%d (limite=%d), score=%.4f (faixa viavel >= %.1f).',
            $attempt,
            $reasonSummary,
            $qualityGate['hard_penalty'],
            $qualityGate['max_hard_penalty'],
            $telemetry['hard_conflict_allocations'],
            $qualityGate['max_hard_conflict_allocations'],
            $qualityGate['score'],
            $qualityGate['viable_score_threshold'],
        );
    }

    /**
     * @param array<string, mixed> $telemetry
     */
    private function attemptElapsedMs(array $telemetry): int
    {
        $startedAt = $telemetry['attempt_started_at'] ?? null;

        if (! is_float($startedAt) && ! is_int($startedAt)) {
            return 0;
        }

        return (int) round(max(0, microtime(true) - (float) $startedAt) * 1000);
    }

    /**
     * @param array<string, mixed> $progressContext
     * @param array<string, mixed> $heartbeatPayload
     */
    private function logInitialPopulationRepairHeartbeat(string $source, array $progressContext, array $heartbeatPayload, float $repairStartedAt): void
    {
        $event = $heartbeatPayload['event'] ?? null;

        if (! in_array($event, ['pass_started', 'pass_progress', 'pass_finished', 'repair_aborted'], true)) {
            return;
        }

        $levelMethod = $event === 'repair_aborted' ? 'warning' : 'info';

        Log::$levelMethod('schedule.initial_population.repair.heartbeat', [
            'execution_id' => $this->executionId,
            'source' => $source,
            'event' => $event,
            'attempt' => $progressContext['attempt'] ?? null,
            'queue_size' => $progressContext['queue_size'] ?? null,
            'elapsed_ms' => (int) round(max(0, microtime(true) - $repairStartedAt) * 1000),
            'repair_pass' => $heartbeatPayload['pass'] ?? null,
            'repair_abort_reason' => $heartbeatPayload['abort_reason'] ?? null,
            'repair_processed_invalid_genes' => $heartbeatPayload['processed_invalid_genes'] ?? null,
            'repair_total_invalid_genes' => $heartbeatPayload['total_invalid_genes'] ?? null,
            'repair_invalid_genes_before' => $heartbeatPayload['invalid_genes_before'] ?? null,
            'repair_invalid_genes_after' => $heartbeatPayload['invalid_genes_after'] ?? null,
            'repair_hard_penalty_before' => $heartbeatPayload['hard_penalty_before'] ?? null,
            'repair_hard_penalty_after' => $heartbeatPayload['hard_penalty_after'] ?? null,
            'repair_hard_penalty_delta' => $heartbeatPayload['hard_penalty_delta'] ?? null,
            'repair_target_summary_before' => $heartbeatPayload['repair_target_summary_before'] ?? null,
            'repair_relocations' => $heartbeatPayload['relocations'] ?? 0,
            'repair_swaps' => $heartbeatPayload['swaps'] ?? 0,
            'repair_local_rebuilds' => $heartbeatPayload['local_rebuilds'] ?? 0,
        ]);
    }

    private function initialQualityGateThresholds(int $attempt, int $queueSize): array
    {
        $isRelaxedPhase = $this->isInitialQualityGateRelaxedPhase($attempt);

        $conflictRatioMax = $isRelaxedPhase
            ? self::INITIAL_QUALITY_GATE_RELAXED_CONFLICT_RATIO_MAX
            : self::INITIAL_QUALITY_GATE_CONFLICT_RATIO_MAX;

        $conflictRatio = min(
            $conflictRatioMax,
            self::INITIAL_QUALITY_GATE_CONFLICT_RATIO_START
                + (($attempt - 1) * self::INITIAL_QUALITY_GATE_CONFLICT_RATIO_GROWTH),
        );

        $maxHardConflictAllocations = max(1, (int) ceil($queueSize * $conflictRatio));

        $maxHardPenalty = $isRelaxedPhase
            ? self::INITIAL_QUALITY_GATE_RELAXED_BASE_HARD_PENALTY
            : max(self::INITIAL_QUALITY_GATE_BASE_HARD_PENALTY, $maxHardConflictAllocations * 6.0);

        return [
            'max_hard_conflict_allocations' => $maxHardConflictAllocations,
            'max_hard_penalty' => $maxHardPenalty,
        ];
    }

    private function isInitialQualityGateRelaxedPhase(int $attempt): bool
    {
        if ($attempt >= self::INITIAL_QUALITY_GATE_RELAXED_FROM_ATTEMPT) {
            return true;
        }

        return $this->shouldActivateEmergencyInitialQualityGateRelaxation($attempt);
    }

    private function shouldActivateEmergencyInitialQualityGateRelaxation(int $attempt): bool
    {
        if ($attempt < self::INITIAL_QUALITY_GATE_EMERGENCY_RELAXED_FROM_ATTEMPT) {
            return false;
        }

        $recentAttempts = array_slice($this->initialPopulationAttemptHistory, -self::INITIAL_QUALITY_GATE_EMERGENCY_REJECTION_WINDOW);

        if (count($recentAttempts) < self::INITIAL_QUALITY_GATE_EMERGENCY_REJECTION_WINDOW) {
            return false;
        }

        foreach ($recentAttempts as $recentAttempt) {
            if (($recentAttempt['outcome'] ?? null) !== 'quality_gate_rejected') {
                return false;
            }

            $hardPenalty = $recentAttempt['hard_penalty'] ?? null;
            $maxHardPenalty = $recentAttempt['max_hard_penalty'] ?? null;

            if (! is_numeric($hardPenalty) || ! is_numeric($maxHardPenalty) || (float) $maxHardPenalty <= 0.0) {
                return false;
            }

            if (((float) $hardPenalty / max(0.001, (float) $maxHardPenalty)) < self::INITIAL_QUALITY_GATE_EMERGENCY_HARD_PENALTY_MULTIPLIER) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $qualityGate
     */
    private function shouldSkipInitialQualityGateRepair(array $qualityGate, int $attempt): bool
    {
        if ($this->isInitialQualityGateRelaxedPhase($attempt)) {
            return false;
        }

        $hardPenalty = (float) ($qualityGate['hard_penalty'] ?? 0.0);
        $maxHardPenalty = (float) ($qualityGate['max_hard_penalty'] ?? 0.0);

        if ($maxHardPenalty <= 0.0) {
            return false;
        }

        return $hardPenalty >= ($maxHardPenalty * self::INITIAL_QUALITY_GATE_SKIP_REPAIR_HARD_PENALTY_MULTIPLIER);
    }

    /**
     * @param array<string, mixed> $telemetry
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
     * @param array<string, mixed> $telemetry
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
            'repair_target_summary_before' => $telemetry['repair_target_summary_before'] ?? null,
            'repair_target_summary_after' => $telemetry['repair_target_summary_after'] ?? null,
            'unrepairable_workload_classes' => $telemetry['unrepairable_workload_classes'] ?? [],
            'pass_count' => count($telemetry['passes'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $telemetry
     * @param array<string, mixed> $extra
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
            'alpha' => (float) ($telemetry['alpha'] ?? 0.0),
            'alpha_profile' => (string) ($telemetry['alpha_profile'] ?? 'unknown'),
            'alpha_reason' => (string) ($telemetry['alpha_reason'] ?? ''),
            'alpha_pressure_score' => (float) ($telemetry['alpha_pressure_score'] ?? 0.0),
            'alpha_history_stress_score' => (float) ($telemetry['alpha_history_stress_score'] ?? 0.0),
            'alpha_queue_pressure_score' => (float) ($telemetry['alpha_queue_pressure_score'] ?? 0.0),
            'alpha_impact' => $this->alphaImpactSummary($telemetry),
            'avg_rcl_size' => $this->averageRclSize($telemetry['rcl_sizes'] ?? []),
        ], $extra);

        if (count($this->initialPopulationAttemptHistory) > self::MAX_BUILD_ATTEMPTS) {
            $this->initialPopulationAttemptHistory = array_slice($this->initialPopulationAttemptHistory, -1 * self::MAX_BUILD_ATTEMPTS);
        }
    }

    private function captureLastInitialPopulationBuildStats(string $source): void
    {
        $this->lastInitialPopulationBuildStats = [
            'grasp_attempts_used' => $this->currentInitialPopulationGraspAttempts,
            'quality_gate_evaluations' => $this->currentInitialPopulationQualityGateEvaluations,
            'quality_gate_passed' => $this->currentInitialPopulationQualityGatePasses,
            'quality_gate_rejected' => $this->currentInitialPopulationQualityGateRejections,
            'source' => $source,
        ];
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
        $hybridSignal = $this->resolveHybridCpAssignmentSignal($hardConflictValues, $queueSize);

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

        if ($hybridSignal['suggest']) {
            $optimizationSuggestions[] = 'Avaliar uma construcao hibrida com CP/assignment para as aulas mais restritas antes de entrar no preenchimento estocastico do restante.';
        }

        if ($hybridSignal['trigger_armed']) {
            $optimizationSuggestions[] = 'Feature flag AG_HYBRID_CP_ASSIGNMENT_ENABLED esta ativa e o gatilho inicial do modo hibrido foi armado para este perfil de gargalo.';
        }

        $this->logHybridTriggerIfNeeded($hybridSignal);

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
            'hybrid_cp_assignment' => $hybridSignal,
            'likely_bottlenecks' => array_values(array_unique($likelyBottlenecks)),
            'optimization_suggestions' => array_values(array_unique($optimizationSuggestions)),
        ];
    }

    /**
     * @param array<int, int> $hardConflictValues
     * @return array<string, mixed>
     */
    private function resolveHybridCpAssignmentSignal(array $hardConflictValues, int $queueSize): array
    {
        $enabled = (bool) config('ag.initial_population.hybrid_cp_assignment.enabled', false);
        $minQualityGateRejections = max(1, (int) config('ag.initial_population.hybrid_cp_assignment.min_quality_gate_rejections', 3));
        $minPeakHardConflicts = max(1, (int) config('ag.initial_population.hybrid_cp_assignment.min_peak_hard_conflicts', 4));
        $requireAttemptLimitReduced = (bool) config('ag.initial_population.hybrid_cp_assignment.require_attempt_limit_reduced', true);

        $qualityGateRejections = (int) ($this->initialPopulationCounters['quality_gate_rejected'] ?? 0);
        $peakHardConflicts = $hardConflictValues === [] ? 0 : max($hardConflictValues);
        $attemptLimitReduced = $this->currentBuildAttemptLimit < $this->currentBuildAttemptLimitBase;
        $hasRelevantQueue = $queueSize >= 60;

        $criteria = [
            'quality_gate_rejections' => $qualityGateRejections >= $minQualityGateRejections,
            'peak_hard_conflicts' => $peakHardConflicts >= $minPeakHardConflicts,
            'attempt_limit_reduced' => ! $requireAttemptLimitReduced || $attemptLimitReduced,
            'queue_size_relevant' => $hasRelevantQueue,
        ];

        $suggest = ! in_array(false, $criteria, true);

        return [
            'feature_enabled' => $enabled,
            'trigger_armed' => $enabled && $suggest,
            'suggest' => $suggest,
            'criteria' => $criteria,
            'min_quality_gate_rejections' => $minQualityGateRejections,
            'min_peak_hard_conflicts' => $minPeakHardConflicts,
            'require_attempt_limit_reduced' => $requireAttemptLimitReduced,
            'observed_quality_gate_rejections' => $qualityGateRejections,
            'observed_peak_hard_conflicts' => $peakHardConflicts,
            'observed_attempt_limit_reduced' => $attemptLimitReduced,
            'observed_queue_size' => $queueSize,
        ];
    }

    /**
     * @param array<string, mixed> $hybridSignal
     */
    private function logHybridTriggerIfNeeded(array $hybridSignal): void
    {
        if (! ($hybridSignal['trigger_armed'] ?? false) || $this->hybridConstructionTriggerLogged) {
            return;
        }

        $this->hybridConstructionTriggerLogged = true;

        Log::info('schedule.initial_population.hybrid_mode.trigger_armed', [
            'execution_id' => $this->executionId,
            'horario_id' => $this->resolveHorarioId(),
            'criteria' => $hybridSignal['criteria'] ?? [],
            'observed_quality_gate_rejections' => $hybridSignal['observed_quality_gate_rejections'] ?? null,
            'observed_peak_hard_conflicts' => $hybridSignal['observed_peak_hard_conflicts'] ?? null,
            'observed_attempt_limit_reduced' => $hybridSignal['observed_attempt_limit_reduced'] ?? null,
            'observed_queue_size' => $hybridSignal['observed_queue_size'] ?? null,
        ]);
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

    // ─── Sprint 4: resolveAdaptiveAlpha com suporte a IslandProfile ──────────

    /**
     * Sprint 4: Retorna o perfil de alpha sugerido pelo portfólio (Melhoria 2).
     * Usa epsilon-greedy: 15% de exploração aleatória, 85% de exploitação do melhor perfil.
     * Retorna null se não houver histórico suficiente.
     */
    private function portfolioBiasedAlphaProfile(): ?string
    {
        $minAttempts = 3;
        $bestProfile = null;
        $bestRate = -1.0;

        foreach ($this->alphaProfileHistory as $profile => $stats) {
            if ($stats['attempts'] < $minAttempts) {
                return null; // histórico insuficiente para qualquer perfil
            }

            $rate = $stats['attempts'] > 0
                ? $stats['success'] / $stats['attempts']
                : 0.0;

            if ($rate > $bestRate) {
                $bestRate = $rate;
                $bestProfile = $profile;
            }
        }

        return $bestProfile;
    }

    // ─── Melhoria 1: salvage de tentativas fracassadas ────────────────────────

    /**
     * Melhoria 1: Extrai genes livres de conflito da tentativa rejeitada.
     *
     * Um gene é "limpo" se nenhum outro gene compartilha o mesmo par (entidade, slot).
     * Dessa forma, as turmas/professores sem conflito podem ser usados como warm-start.
     *
     * @param Gene[] $genes
     * @return Gene[]
     */
    private function extractConflictFreeGenes(array $genes): array
    {
        $teacherClaims = [];
        $classClaims = [];

        foreach ($genes as $gene) {
            if (! $gene instanceof Gene) {
                continue;
            }

            for ($offset = 0; $offset < $gene->duracaoTempos(); $offset++) {
                $key = $gene->diaSemana() . '-' . ($gene->periodoDia() + $offset);
                $teacherClaims[$gene->professorId()][$key] = ($teacherClaims[$gene->professorId()][$key] ?? 0) + 1;
                $classClaims[$gene->turmaId()][$key] = ($classClaims[$gene->turmaId()][$key] ?? 0) + 1;
            }
        }

        $clean = [];

        foreach ($genes as $gene) {
            if (! $gene instanceof Gene) {
                continue;
            }

            $conflict = false;

            for ($offset = 0; $offset < $gene->duracaoTempos(); $offset++) {
                $key = $gene->diaSemana() . '-' . ($gene->periodoDia() + $offset);

                if (($teacherClaims[$gene->professorId()][$key] ?? 0) > 1
                    || ($classClaims[$gene->turmaId()][$key] ?? 0) > 1
                ) {
                    $conflict = true;
                    break;
                }
            }

            if (! $conflict) {
                $clean[] = $gene;
            }
        }

        return $clean;
    }

    /**
     * Melhoria 1: Atualiza o salvage se a tentativa rejeitada for melhor do que o atual.
     *
     * Critérios:
     * - Hard penalty < 4× o limiar base (tentativa marginalmente ruim)
     * - Genes limpos cobrem >= 40% da fila
     */
    private function updateSalvageFromCandidate(Cromossomo $candidate, float $hardPenalty, int $queueSize): void
    {
        $salvageThreshold = self::INITIAL_QUALITY_GATE_BASE_HARD_PENALTY * 4.0;

        if ($hardPenalty > $salvageThreshold) {
            return; // tentativa muito ruim para ser aproveitada
        }

        $cleanGenes = $this->extractConflictFreeGenes($candidate->genes());
        $minCoverage = (int) ceil($queueSize * 0.40);

        if (count($cleanGenes) < $minCoverage) {
            return; // cobertura insuficiente para warm-start útil
        }

        if ($hardPenalty < $this->bestSalvagePenalty) {
            $this->bestSalvagePenalty = $hardPenalty;
            $this->bestSalvageGenes = $cleanGenes;
        }
    }

    /**
     * Melhoria 1: Tenta criar indivíduo usando genes salvageados como warm-start.
     *
     * Pré-aloca os genes limpos e continua GRASP apenas para as aulas restantes.
     * Se o resultado passar no quality gate, retorna o cromossomo; caso contrário null.
     */
    private function tryCreateIndividualFromSalvage(array $queue): ?Cromossomo
    {
        if ($this->bestSalvageGenes === []) {
            return null;
        }

        $this->reportInitialPopulationProgress([
            'stage' => 'salvage_start',
            'salvage_gene_count' => count($this->bestSalvageGenes),
            'queue_size' => count($queue),
        ]);

        // Identifica lições já cobertas pelo salvage (aulaId → ocorrências cobertas)
        $coveredLessonOccurrences = [];

        foreach ($this->bestSalvageGenes as $gene) {
            $coveredLessonOccurrences[$gene->aulaId()] = ($coveredLessonOccurrences[$gene->aulaId()] ?? 0) + 1;
        }

        // Constrói fila restante (tarefas ainda não cobertas)
        $remainingQueue = [];
        $tempCoverageCounts = [];

        foreach ($queue as $task) {
            if (! is_array($task) || ! isset($task['lesson'])) {
                continue;
            }

            /** @var LessonData $lesson */
            $lesson = $task['lesson'];
            $covered = $tempCoverageCounts[$lesson->id] ?? 0;
            $available = $coveredLessonOccurrences[$lesson->id] ?? 0;

            if ($covered < $available) {
                $tempCoverageCounts[$lesson->id] = $covered + 1;
                // skip: já coberto pelo salvage
            } else {
                $remainingQueue[] = $task;
            }
        }

        // Reconstrói ocupação a partir dos genes salvageados
        $savedTeacherBusy = [];
        $savedClassBusy = [];

        foreach ($this->bestSalvageGenes as $gene) {
            for ($offset = 0; $offset < $gene->duracaoTempos(); $offset++) {
                $key = $gene->diaSemana() . '-' . ($gene->periodoDia() + $offset);
                $savedTeacherBusy[$gene->professorId()][$key] = true;
                $savedClassBusy[$gene->turmaId()][$key] = true;
            }
        }

        // Continua GRASP apenas para as lições restantes
        $assignedGenes = $this->bestSalvageGenes;
        $teacherBusy = $savedTeacherBusy;
        $classBusy = $savedClassBusy;
        $alphaDecision = $this->resolveAdaptiveAlpha(attempt: 1, queueSize: count($queue));
        $alpha = (float) $alphaDecision['alpha'];
        $telemetry = [
            'attempt' => 0,
            'alpha' => round($alpha, 4),
            'alpha_profile' => $alphaDecision['alpha_profile'],
            'alpha_reason' => $alphaDecision['alpha_reason'],
            'alpha_pressure_score' => $alphaDecision['alpha_pressure_score'],
            'alpha_history_stress_score' => $alphaDecision['alpha_history_stress_score'],
            'alpha_queue_pressure_score' => $alphaDecision['alpha_queue_pressure_score'],
            'queue_size' => count($remainingQueue),
            'allocations' => count($this->bestSalvageGenes),
            'forced_allocations' => 0,
            'hard_conflict_allocations' => 0,
            'dynamic_reorders' => 0,
            'regret_selections' => 0,
            'attempt_limit' => $this->currentBuildAttemptLimit,
            'attempt_started_at' => microtime(true),
            'rcl_sizes' => [],
        ];

        if (! $this->constructWithGrasp($remainingQueue, $alpha, $assignedGenes, $teacherBusy, $classBusy, $telemetry)) {
            return null;
        }

        $candidate = new Cromossomo($assignedGenes);
        $qualityGate = $this->evaluateInitialPopulationQualityGate(
            candidate: $candidate,
            attempt:   self::MAX_BUILD_ATTEMPTS,
            queueSize: count($queue),
            telemetry: $telemetry,
        );

        if ($qualityGate['passes']) {
            $this->lastAcceptedInitialSeed = $candidate->copy();
            $this->lastInitialPopulationSource = 'salvage';

            $this->reportInitialPopulationProgress([
                'stage' => 'salvage_passed',
                'hard_penalty' => $qualityGate['hard_penalty'],
                'soft_penalty' => $qualityGate['soft_penalty'],
                'fitness_score' => $qualityGate['score'],
                'queue_size' => count($queue),
            ]);

            Log::info('schedule.initial_population.salvage.passed', [
                'execution_id' => $this->executionId,
                'salvage_gene_count' => count($this->bestSalvageGenes),
                'queue_size' => count($queue),
                'hard_penalty' => $qualityGate['hard_penalty'],
                'score' => $qualityGate['score'],
            ]);

            return $candidate;
        }

        $this->reportInitialPopulationProgress([
            'stage' => 'salvage_rejected',
            'hard_penalty' => $qualityGate['hard_penalty'],
            'queue_size' => count($queue),
        ]);

        return null;
    }

    // ─── Melhoria 4: nogoods persistentes entre execuções ────────────────────

    /**
     * Melhoria 4: Carrega nogoods persistidos de execuções anteriores para o horário atual.
     *
     * Lazy: executado no máximo uma vez por Request/Job.
     */
    private function loadNogoodsFromPersistentCache(): void
    {
        if ($this->nogoodsPersistenceLoaded) {
            return;
        }

        $this->nogoodsPersistenceLoaded = true;

        $horarioId = $this->resolveHorarioId();

        if ($horarioId === null) {
            return;
        }

        $cached = cache()->get("ag.nogoods.{$horarioId}");

        if (! is_array($cached)) {
            return;
        }

        foreach (['lesson_slot', 'professor_slot', 'class_slot'] as $type) {
            if (! is_array($cached[$type] ?? null)) {
                continue;
            }

            foreach ($cached[$type] as $key => $count) {
                $this->initialPopulationNogoods[$type][(string) $key] =
                    ($this->initialPopulationNogoods[$type][(string) $key] ?? 0) + (int) $count;
            }
        }

        $totalLoaded = array_sum(array_map('count', $this->initialPopulationNogoods));

        Log::info('schedule.initial_population.nogoods.loaded', [
            'execution_id' => $this->executionId,
            'horario_id' => $horarioId,
            'lesson_slot_count' => count($this->initialPopulationNogoods['lesson_slot']),
            'professor_slot_count' => count($this->initialPopulationNogoods['professor_slot']),
            'class_slot_count' => count($this->initialPopulationNogoods['class_slot']),
            'total_loaded' => $totalLoaded,
        ]);
    }

    /**
     * Melhoria 4: Persiste nogoods aprendidos para uso em execuções futuras do mesmo horário.
     *
     * Limita a 500 entradas por tipo (top por frequência) para evitar crescimento ilimitado.
     * TTL: 7 dias.
     */
    private function persistNogoodsToPersistentCache(): void
    {
        $totalNogoods = $this->totalNogoodsLearned();

        if ($totalNogoods === 0) {
            return;
        }

        $horarioId = $this->resolveHorarioId();

        if ($horarioId === null) {
            return;
        }

        $capped = $this->capNogoodsForPersistence($this->initialPopulationNogoods);
        $ttl = now()->addDays(7);

        cache()->put("ag.nogoods.{$horarioId}", $capped, $ttl);

        Log::debug('schedule.initial_population.nogoods.persisted', [
            'execution_id' => $this->executionId,
            'horario_id' => $horarioId,
            'total_nogoods' => $totalNogoods,
        ]);
    }

    /**
     * Limita nogoods para persistência: mantém top-500 por tipo (decrescente por count).
     *
     * @param array<string, array<string, int>> $nogoods
     * @return array<string, array<string, int>>
     */
    private function capNogoodsForPersistence(array $nogoods): array
    {
        $maxPerType = 500;
        $capped = [];

        foreach (['lesson_slot', 'professor_slot', 'class_slot'] as $type) {
            $entries = $nogoods[$type] ?? [];
            arsort($entries);
            $capped[$type] = array_slice($entries, 0, $maxPerType, true);
        }

        return $capped;
    }
}
