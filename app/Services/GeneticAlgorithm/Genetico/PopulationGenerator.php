<?php

namespace App\Services\GeneticAlgorithm\Genetico;

use App\Models\Aula;
use App\Models\ConfiguracaoHorario; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene; // ✅ ADICIONADO
use Illuminate\Support\Collection;

class PopulationGenerator
{
    private ConfiguracaoHorario $configuracaoHorario; // ✅ ADICIONADO
    
    private Collection $aulas;
    private GeneticAlgorithmConfigDTO $configAG;

    public function __construct()
    {
        // As dependências serão setadas via setContext()
    }

    public function setContext(ConfiguracaoHorario $configuracaoHorario, Collection $aulas, GeneticAlgorithmConfigDTO $configAG): void {
        $this->configuracaoHorario = $configuracaoHorario;
        $this->aulas = $aulas;
        $this->configAG = $configAG;
    }

    public function generate(int $populationSize): Collection
    {
        if (!isset($this->configuracaoHorario, $this->aulas, $this->configAG)) {
            throw new \Exception("PopulationGenerator context not set. Call setContext() before generate().");
        }

        $population = new Collection();
        for ($i = 0; $i < $populationSize; $i++) {
            $population->add($this->createRandomCromossomo());
        }
        return $population;
    }

    protected function createRandomCromossomo(): Cromossomo
    {
        $cromossomo = new Cromossomo($this->configuracaoHorario);

        foreach ($this->aulas as $aula) {
            $numAlocacoes = $aula->aulas_semana;
            $duracaoTempos = $this->getDuracaoTempos($aula->tipo);

            $alocacoesFeitas = 0;
            $tentativas = 0;
            $maxTentativas = 100; // Limite para evitar loops infinitos em casos impossíveis

            while ($alocacoesFeitas < $numAlocacoes && $tentativas < $maxTentativas) {
                $tentativas++;

                // Escolhe um horário aleatório (respeitando preferências e evitando conflitos)
                $horario = $this->escolherHorarioAleatorio($aula, $cromossomo->genes);

                if ($horario) {
                    $gene = new Gene(
                        $aula,
                        $horario['dia'],
                        $horario['tempo'],
                        $duracaoTempos,
                        $aula->professor,
                        $aula->disciplina,
                        $aula->turma
                    );
                    $cromossomo->addGene($gene);
                    $alocacoesFeitas++;
                }
            }
        }

        return $cromossomo;
    }

    protected function escolherHorarioAleatorio(Aula $aula, Collection $genesExistentes): ?array
    {
        $horariosValidos = [];
        $horariosDisponiveis = $this->configAG->horariosDisponiveis;

        if (empty($horariosDisponiveis)) {
            return null;
        }

        foreach ($horariosDisponiveis as $horario) {
            $dia = $horario['dia'];
            $tempo = $horario['tempo'];

            // Verificar preferências de dias
            if (!empty($aula->dias_preferidos) && !in_array($dia, json_decode($aula->dias_preferidos, true))) {
                continue;
            }

            // Verificar preferências de tempos
            if (!empty($aula->tempos_preferidos) && !in_array($tempo, json_decode($aula->tempos_preferidos, true))) {
                continue;
            }

            // Verificar se não há conflito com genes existentes (mesma turma ou professor no mesmo dia/tempo)
            $temConflito = false;
            $duracaoNovaAula = $this->getDuracaoTempos($aula->tipo);

            // Itera sobre cada slot que a nova aula ocuparia
            for ($i = 0; $i < $duracaoNovaAula; $i++) {
                $currentPeriodoNovaAula = $tempo + $i;

                foreach ($genesExistentes as $geneExistente) {
                    // Itera sobre cada slot que o gene existente ocupa
                    for ($j = 0; $j < $geneExistente->duracaoTempos; $j++) {
                        $currentPeriodoGeneExistente = $geneExistente->periodoDia + $j;

                        // Verifica se há sobreposição de dia e período
                        if ($geneExistente->diaSemana == $dia && $currentPeriodoGeneExistente == $currentPeriodoNovaAula) {
                            // Verifica conflito de turma ou professor
                            if (($geneExistente->turma && $geneExistente->turma->id == $aula->turma_id) ||
                                ($geneExistente->professor && $geneExistente->professor->id == $aula->professor_id)) {
                                $temConflito = true;
                                break 3; // Sai dos três loops (i, j, foreach genesExistentes)
                            }
                        }
                    }
                }
            }

            if (!$temConflito) {
                $horariosValidos[] = $horario;
            }
        }

        if (empty($horariosValidos)) {
            // Se não encontrou horário válido com preferências, tentar qualquer horário disponível
            return $horariosDisponiveis[array_rand($horariosDisponiveis)] ?? null;
        }

        return $horariosValidos[array_rand($horariosValidos)];
    }

    protected function getDuracaoTempos(string $tipo): int
    {
        return match($tipo) {
            'simples' => 1,
            'dupla' => 2,
            'tripla' => 3,
            default => 1,
        };
    }
}
