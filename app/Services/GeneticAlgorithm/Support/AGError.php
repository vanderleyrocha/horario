<?php

namespace App\Services\GeneticAlgorithm\Support;

final class AGError {
    public function __construct(
        public string $codigo,
        public string $categoria,
        public string $mensagem,
        public string $severidade,
        public array $dados = [],
        public ?string $sugestao = null,
    ) {
    }

    public function toArray(): array {
        return [
            'codigo' => $this->codigo,
            'categoria' => $this->categoria,
            'mensagem' => $this->mensagem,
            'severidade' => $this->severidade,
            'dados' => $this->dados,
            'sugestao' => $this->sugestao,
        ];
    }
}
