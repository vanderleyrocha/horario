<?php

namespace App\Services\GeneticAlgorithm\Genetico\DTO;

use App\Models\Horario;
use Carbon\CarbonImmutable;

class GeneticAlgorithmConfigDTO
{
    // Parâmetros do Algoritmo Genético
    public int $tamanhoPopulacao;
    public int $numeroGeracoes;
    public float $taxaMutacao;
    public float $taxaCrossover;
    public int $elitismCount; // ✅ ADICIONADO
    public float $targetFitness; // ✅ ADICIONADO
    public int $maxGenerationsWithoutImprovement; // ✅ ADICIONADO

    // Configurações do Horário Escolar
    public string $nomeEscola;
    public int $aulasPorDia;
    public int $diasSemana;
    public string $horarioInicio; // Formato H:i
    public string $horarioFim;    // Formato H:i
    public int $duracaoAulaMinutos;
    public int $duracaoIntervaloMinutos;
    public array $horariosIntervalos; // Ex: [2, 4] para intervalos após a 2ª e 4ª aula
    public array $duracoesIntervalos; // Ex: [15, 20] para durações específicas
    public bool $permitirJanelas;
    public bool $agruparDisciplinas;
    public int $maxAulasSeguidas;
    public array $horariosDisponiveis = []; // ✅ ADICIONADO: Para ser preenchido dinamicamente

    private function __construct(
        int $tamanhoPopulacao,
        int $numeroGeracoes,
        float $taxaMutacao,
        float $taxaCrossover,
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
        int $elitismCount,
        float $targetFitness,
        int $maxGenerationsWithoutImprovement
    ) {
        $this->tamanhoPopulacao = $tamanhoPopulacao;
        $this->numeroGeracoes = $numeroGeracoes;
        $this->taxaMutacao = $taxaMutacao;
        $this->taxaCrossover = $taxaCrossover;
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
        $this->elitismCount = $elitismCount;
        $this->targetFitness = $targetFitness;
        $this->maxGenerationsWithoutImprovement = $maxGenerationsWithoutImprovement;
    }

    /**
     * Cria um DTO a partir dos modelos Horario e ConfiguracaoHorario.
     *
     * @param Horario $horario O modelo Horario contendo as configurações do AG (JSON) e o relacionamento configuracaoHorario.
     * @return self
     * @throws \Exception Se a ConfiguracaoHorario não for encontrada.
     */
    public static function fromModels(Horario $horario): self
    {
        // Configurações do Algoritmo Genético (campo JSON)
        $configAG = $horario->configuracao ?? [];
        $tamanhoPopulacao = $configAG['populacao'] ?? 100;
        $numeroGeracoes = $configAG['geracoes'] ?? 500;
        $taxaMutacao = $configAG['taxa_mutacao'] ?? 0.1;
        $taxaCrossover = $configAG['taxa_crossover'] ?? 0.7;
        $elitismCount = $configAG['elitismo'] ?? (int)($tamanhoPopulacao * 0.1); // Valor padrão
        $targetFitness = $configAG['target_fitness'] ?? 95.0; // Valor padrão
        $maxGenerationsWithoutImprovement = $configAG['geracoes_sem_melhoria'] ?? 50; // Valor padrão

        // Configuração do horário escolar (relacionamento)
        $configuracaoHorario = $horario->configuracaoHorario;

        if (!$configuracaoHorario) {
            throw new \Exception('Configuração do horário não encontrada para o Horário ID: ' . $horario->id . '. Configure o horário antes de gerar.');
        }

        return new self(
            $tamanhoPopulacao,
            $numeroGeracoes,
            $taxaMutacao,
            $taxaCrossover,
            $configuracaoHorario->nome_escola,
            $configuracaoHorario->aulas_por_dia,
            $configuracaoHorario->dias_semana,
            CarbonImmutable::parse($configuracaoHorario->horario_inicio)->format('H:i'),
            CarbonImmutable::parse($configuracaoHorario->horario_fim)->format('H:i'),
            $configuracaoHorario->duracao_aula_minutos,
            $configuracaoHorario->duracao_intervalo_minutos,
            $configuracaoHorario->horarios_intervalos ?? [2, 4],
            $configuracaoHorario->duracoes_intervalos ?? [],
            $configuracaoHorario->permitir_janelas,
            $configuracaoHorario->agrupar_disciplinas,
            $configuracaoHorario->max_aulas_seguidas,
            $elitismCount,
            $targetFitness,
            $maxGenerationsWithoutImprovement
        );
    }
}
