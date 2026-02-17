<?php

namespace App\Services\GeneticAlgorithm\Genetico;

use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;
use Illuminate\Support\Facades\Log;

final class PopulationGenerator {
    public function __construct(private readonly array $aulas, private readonly GeneticAlgorithmConfigDTO $configAG) {}

    public function generate(): array {

        $population = [];

        for ($i = 0; $i < $this->configAG->tamanhoPopulacao; $i++) {
            $population[] = $this->createDiverseCromossomo($i);
        }

        return $population;
    }

    private function createDiverseCromossomo(int $seedOffset): Cromossomo {
        $genes = [];
        $aulas = $this->aulas;

        // Estratégia 1: Ordenar aulas de forma inteligente
        $aulas = $this->ordenarAulasPorDificuldade($aulas);

        $horariosBase = $this->configAG->horariosDisponiveis;

        $ocupacaoProfessor = [];
        $ocupacaoTurma = [];

        // Estratégia 2: Tentar múltiplas vezes se falhar
        $maxTentativas = 30;
        $tentativa = 0;

        while ($tentativa < $maxTentativas) {
            try {
                $genes = [];
                $ocupacaoProfessor = [];
                $ocupacaoTurma = [];

                // Estratégia 3: Diferentes ordens de processamento
                if ($tentativa % 2 == 0) {
                    // Ordem original
                    $aulasProcessar = $aulas;
                } else {
                    // Ordem reversa para diversidade
                    $aulasProcessar = array_reverse($aulas);
                }

                // Estratégia 4: Variação nos horários base
                $horarios = $horariosBase;
                if ($tentativa % 3 == 0) {
                    // Shuffle nos horários para cada tentativa
                    shuffle($horarios);
                }

                $sucesso = $this->tentarAlocarAulas($aulasProcessar, $horarios, $ocupacaoProfessor, $ocupacaoTurma, $genes);

                if ($sucesso) {
                    return new Cromossomo($genes);
                }

                $tentativa++;
            } catch (\RuntimeException $e) {
                $tentativa++;
                continue;
            }
        }

        // Se falhou todas as tentativas, log detalhado para debug
        Log::error("Falha ao gerar cromossomo após $maxTentativas tentativas", [
            'total_aulas' => count($this->aulas),
            'horarios_disponiveis' => count($horariosBase),
            'aulas_por_dia' => $this->configAG->aulasPorDia,
            'tamanho_populacao' => $this->configAG->tamanhoPopulacao,
            'seed_offset' => $seedOffset
        ]);

        throw new \RuntimeException("Configuração inviável: não há espaço suficiente para gerar população inicial válida.", 1);
    }

    private function tentarAlocarAulas(array $aulas, array $horarios, array &$ocupacaoProfessor, array &$ocupacaoTurma, array &$genes): bool {

        foreach ($aulas as $aula) {
            $numAlocacoes = (int) $aula->aulas_semana;
            $duracao = $this->getDuracaoTempos($aula->tipo);

            for ($aloc = 0; $aloc < $numAlocacoes; $aloc++) {

                $horarioEscolhido = $this->encontrarHorarioViavel($aula, $horarios, $duracao, $ocupacaoProfessor, $ocupacaoTurma);

                if (!$horarioEscolhido) {
                    return false;
                }

                $dia = $horarioEscolhido['dia'];
                $tempo = $horarioEscolhido['tempo'];

                // Registrar ocupação
                for ($d = 0; $d < $duracao; $d++) {
                    $ocupacaoProfessor[$aula->professor->id][$dia][$tempo + $d] = true;
                    $ocupacaoTurma[$aula->turma->id][$dia][$tempo + $d] = true;
                }

                $genes[] = new Gene(
                    aulaId: $aula->id,
                    professorId: $aula->professor->id,
                    turmaId: $aula->turma->id,
                    disciplinaId: $aula->disciplina->id,
                    diaSemana: $dia,
                    periodoDia: $tempo,
                    duracaoTempos: $duracao
                );
            }
        }

        return true;
    }

    private function encontrarHorarioViavel($aula, array $horarios, int $duracao, array $ocupacaoProfessor, array $ocupacaoTurma): ?array {

        // Estratégia 5: Tentar horários em ordem diferente
        $horariosTestar = $horarios;
        shuffle($horariosTestar);

        // Estratégia 6: Priorizar horários que mantêm continuidade
        $horariosPriorizados = $this->priorizarHorariosViaveis($horariosTestar, $aula, $duracao, $ocupacaoProfessor, $ocupacaoTurma);

        foreach ($horariosPriorizados as $horario) {
            $dia = $horario['dia'];
            $tempo = $horario['tempo'];

            // Verificar overflow
            if ($tempo + $duracao - 1 > $this->configAG->aulasPorDia) {
                continue;
            }

            // Verificar conflitos
            if (!$this->temConflitoBasico($aula->professor->id, $aula->turma->id, $dia, $tempo, $duracao, $ocupacaoProfessor, $ocupacaoTurma)) {
                return $horario;
            }
        }

        return null;
    }

    private function priorizarHorariosViaveis(array $horarios, $aula, int $duracao, array $ocupacaoProfessor, array $ocupacaoTurma): array {

        $horariosComPontuacao = [];

        foreach ($horarios as $horario) {
            $dia = $horario['dia'];
            $tempo = $horario['tempo'];

            // Verificar overflow básico
            if ($tempo + $duracao - 1 > $this->configAG->aulasPorDia) {
                continue;
            }

            $pontuacao = 0;

            // Verificar disponibilidade do professor neste horário
            $profLivre = true;
            $turmaLivre = true;

            for ($i = 0; $i < $duracao; $i++) {
                if (isset($ocupacaoProfessor[$aula->professor->id][$dia][$tempo + $i])) {
                    $profLivre = false;
                    break;
                }
                if (isset($ocupacaoTurma[$aula->turma->id][$dia][$tempo + $i])) {
                    $turmaLivre = false;
                    break;
                }
            }

            // Pontuação maior para horários completamente livres
            if ($profLivre && $turmaLivre) {
                $pontuacao += 100;
            }

            // Verificar se é um horário que permite blocos contínuos (útil para aulas duplas/triplas)
            if ($duracao > 1) {
                $proximosLivres = true;
                for ($i = 1; $i < $duracao; $i++) {
                    if ($tempo + $i > $this->configAG->aulasPorDia) {
                        $proximosLivres = false;
                        break;
                    }
                }
                if ($proximosLivres) {
                    $pontuacao += 50;
                }
            }

            $horariosComPontuacao[] = [
                'horario' => $horario,
                'pontuacao' => $pontuacao
            ];
        }

        // Ordenar por pontuação (maior primeiro)
        usort($horariosComPontuacao, function ($a, $b) {
            return $b['pontuacao'] <=> $a['pontuacao'];
        });

        return array_column($horariosComPontuacao, 'horario');
    }

    private function ordenarAulasPorDificuldade(array $aulas): array {
        // Estratégia: ordenar por maior duração primeiro (mais restritivas)
        usort($aulas, function ($a, $b) {
            $duracaoA = $this->getDuracaoTempos($a->tipo);
            $duracaoB = $this->getDuracaoTempos($b->tipo);

            if ($duracaoA !== $duracaoB) {
                return $duracaoB <=> $duracaoA; // Maior duração primeiro
            }

            // Se mesma duração, ordenar por número de alocações (aulas_semana)
            return $b->aulas_semana <=> $a->aulas_semana;
        });

        return $aulas;
    }

    private function getDuracaoTempos(string $tipo): int {
        return match ($tipo) {
            'simples' => 1,
            'dupla' => 2,
            'tripla' => 3,
            default => 1,
        };
    }

    private function temConflitoBasico(int $professorId, int $turmaId, int $dia, int $tempo, int $duracao, array $ocupacaoProfessor, array $ocupacaoTurma): bool {
        for ($i = 0; $i < $duracao; $i++) {
            if (isset($ocupacaoProfessor[$professorId][$dia][$tempo + $i]) || isset($ocupacaoTurma[$turmaId][$dia][$tempo + $i])) {
                return true;
            }
        }
        return false;
    }
}
