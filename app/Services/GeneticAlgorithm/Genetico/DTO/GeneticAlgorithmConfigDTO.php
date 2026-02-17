<?php

namespace App\Services\GeneticAlgorithm\Genetico\DTO;

use App\Models\Horario;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class GeneticAlgorithmConfigDTO
{
    /*
    |--------------------------------------------------------------------------
    | PARÂMETROS DO GA
    |--------------------------------------------------------------------------
    */

    public int $tamanhoPopulacao;
    public int $numeroGeracoes;
    public float $taxaMutacao;
    public float $taxaCrossover;
    public int $elitismCount;
    public float $targetFitness;
    public int $maxGenerationsWithoutImprovement;

    /*
    |--------------------------------------------------------------------------
    | CONFIGURAÇÕES DO HORÁRIO
    |--------------------------------------------------------------------------
    */

    public string $horarioId;
    public string $nomeEscola;
    public int $aulasPorDia;
    public int $diasSemana;
    public string $horarioInicio; // H:i
    public string $horarioFim;    // H:i
    public int $duracaoAulaMinutos;
    public int $duracaoIntervaloMinutos;

    /** @var int[] */
    public array $horariosIntervalos;

    /** @var int[] */
    public array $duracoesIntervalos;

    public bool $permitirJanelas;
    public bool $agruparDisciplinas;
    public int $maxAulasSeguidas;

    /** @var array<int, array{dia:int, tempo:int}> */
    public array $horariosDisponiveis;

    private function __construct(
        int $tamanhoPopulacao,
        int $numeroGeracoes,
        float $taxaMutacao,
        float $taxaCrossover,
        int $elitismCount,
        float $targetFitness,
        int $maxGenerationsWithoutImprovement,
        int $horarioId,
        string $nomeEscola,
        int $aulasPorDia,
        int $diasSemana,
        string $horarioInicio,
        string $horarioFim,
        int $duracaoAulaMinutos,
        int $duracaoIntervaloMinutos,
        array $horariosIntervalos,
        array $duracoesIntervalos,
        bool $permitirJanelas,
        bool $agruparDisciplinas,
        int $maxAulasSeguidas,
        array $horariosDisponiveis
    ) {
        self::assertPositive($tamanhoPopulacao, 'tamanhoPopulacao');
        self::assertPositive($numeroGeracoes, 'numeroGeracoes');
        self::assertRange($taxaMutacao, 0.0, 1.0, 'taxaMutacao');
        self::assertRange($taxaCrossover, 0.0, 1.0, 'taxaCrossover');

        $this->tamanhoPopulacao = $tamanhoPopulacao;
        $this->numeroGeracoes = $numeroGeracoes;
        $this->taxaMutacao = $taxaMutacao;
        $this->taxaCrossover = $taxaCrossover;
        $this->elitismCount = $elitismCount;
        $this->targetFitness = $targetFitness;
        $this->maxGenerationsWithoutImprovement = $maxGenerationsWithoutImprovement;

        $this->horarioId = $horarioId;
        $this->nomeEscola = $nomeEscola;
        $this->aulasPorDia = $aulasPorDia;
        $this->diasSemana = $diasSemana;
        $this->horarioInicio = $horarioInicio;
        $this->horarioFim = $horarioFim;
        $this->duracaoAulaMinutos = $duracaoAulaMinutos;
        $this->duracaoIntervaloMinutos = $duracaoIntervaloMinutos;

        $this->horariosIntervalos = $horariosIntervalos;
        $this->duracoesIntervalos = $duracoesIntervalos;
        $this->permitirJanelas = $permitirJanelas;
        $this->agruparDisciplinas = $agruparDisciplinas;
        $this->maxAulasSeguidas = $maxAulasSeguidas;

        $this->horariosDisponiveis = $horariosDisponiveis;
    }

    public static function fromModels(Horario $horario): self
    {
        $configAG = $horario->configuracao ?? [];
        $config = $horario->configuracaoHorario;

        if (!$config) {
            throw new InvalidArgumentException(
                "Configuração do horário não encontrada para Horário ID {$horario->id}"
            );
        }

        $tamanhoPopulacao = (int) ($configAG['populacao'] ?? 100);
        $numeroGeracoes = (int) ($configAG['geracoes'] ?? 500);
        $taxaMutacao = (float) ($configAG['taxa_mutacao'] ?? 0.1);
        $taxaCrossover = (float) ($configAG['taxa_crossover'] ?? 0.7);
        $elitismCount = (int) ($configAG['elitismo'] ?? max(1, (int)($tamanhoPopulacao * 0.1)));
        $targetFitness = (float) ($configAG['target_fitness'] ?? 0.0);
        $maxGenerationsWithoutImprovement = (int) ($configAG['geracoes_sem_melhoria'] ?? 50);

        $aulasPorDia = (int) $config->aulas_por_dia;
        $diasSemana = (int) $config->dias_semana;

        $horariosIntervalos = array_map(
            'intval',
            is_array($config->horarios_intervalos)
                ? $config->horarios_intervalos
                : (json_decode($config->horarios_intervalos ?? '[]', true) ?? [])
        );

        $duracoesIntervalos = array_map(
            'intval',
            is_array($config->duracoes_intervalos)
                ? $config->duracoes_intervalos
                : (json_decode($config->duracoes_intervalos ?? '[]', true) ?? [])
        );

        $horariosDisponiveis = [];

        for ($dia = 1; $dia <= $diasSemana; $dia++) {
            for ($tempo = 1; $tempo <= $aulasPorDia; $tempo++) {
                $horariosDisponiveis[] = [
                    'dia' => $dia,
                    'tempo' => $tempo,
                ];
            }
        }

        return new self(
            tamanhoPopulacao: $tamanhoPopulacao,
            numeroGeracoes: $numeroGeracoes,
            taxaMutacao: $taxaMutacao,
            taxaCrossover: $taxaCrossover,
            elitismCount: $elitismCount,
            targetFitness: $targetFitness,
            maxGenerationsWithoutImprovement: $maxGenerationsWithoutImprovement,
            horarioId: (int) $horario->id,
            nomeEscola: (string) $config->nome_escola,
            aulasPorDia: $aulasPorDia,
            diasSemana: $diasSemana,
            horarioInicio: CarbonImmutable::parse($config->horario_inicio)->format('H:i'),
            horarioFim: CarbonImmutable::parse($config->horario_fim)->format('H:i'),
            duracaoAulaMinutos: (int) $config->duracao_aula_minutos,
            duracaoIntervaloMinutos: (int) $config->duracao_intervalo_minutos,
            horariosIntervalos: $horariosIntervalos,
            duracoesIntervalos: $duracoesIntervalos,
            permitirJanelas: (bool) $config->permitir_janelas,
            agruparDisciplinas: (bool) $config->agrupar_disciplinas,
            maxAulasSeguidas: (int) $config->max_aulas_seguidas,
            horariosDisponiveis: $horariosDisponiveis
        );
    }

    private static function assertPositive(int $value, string $field): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("$field must be > 0");
        }
    }

    private static function assertRange(float $value, float $min, float $max, string $field): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("$field must be between $min and $max");
        }
    }
}