<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Builders;

use App\Models\Horario;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use InvalidArgumentException;

final class GeneMapper
{
    public function toArray(Horario $horario, Gene $gene): array
    {
        $configuracao = $horario->configuracaoHorario;

        if (!$configuracao) {
            throw new InvalidArgumentException(
                "Configuração do horário não encontrada para o Horário ID {$horario->id}."
            );
        }

        $tempo = $gene->periodoDia();
        $duracaoTempos = $gene->duracaoTempos();

        $horarioInicio = $configuracao->getHorarioTempo($tempo);
        $horarioFim = $configuracao->getTemposFim($tempo + $duracaoTempos - 1);

        return [
            'aula_id' => $gene->aulaId(),
            'professor_id' => $gene->professorId(),
            'turma_id' => $gene->turmaId(),
            'disciplina_id' => $gene->disciplinaId(),
            'dia_semana' => $this->mapDiaSemana($gene->diaSemana()),
            'tempo' => $tempo,
            'duracao_tempos' => $duracaoTempos,
            'horario_inicio' => $horarioInicio,
            'horario_fim' => $horarioFim,
        ];
    }

    private function mapDiaSemana(int $diaSemana): string
    {
        return match ($diaSemana) {
            1 => 'segunda',
            2 => 'terca',
            3 => 'quarta',
            4 => 'quinta',
            5 => 'sexta',
            default => throw new InvalidArgumentException("Dia da semana inválido: {$diaSemana}."),
        };
    }
}
