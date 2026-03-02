<?php

declare(strict_types=1);

namespace App\Modules\AG\Support;

use InvalidArgumentException;

final class AGError {
    private string $codigo;
    private string $categoria;
    private string $mensagem;
    private string $severidade;
    private array $dados;
    private ?string $sugestao;
    private string $timestamp;

    private const SEVERIDADES_VALIDAS = [
        'BAIXA',
        'MEDIA',
        'ALTA',
        'CRITICA',
    ];

    public function __construct(
        string $codigo,
        string $categoria,
        string $mensagem,
        string $severidade,
        array $dados = [],
        ?string $sugestao = null,
        ?string $timestamp = null,
    ) {
        $this->assertSeveridadeValida($severidade);

        $this->codigo = $codigo;
        $this->categoria = $categoria;
        $this->mensagem = $mensagem;
        $this->severidade = $severidade;
        $this->dados = $dados;
        $this->sugestao = $sugestao;
        $this->timestamp = $timestamp ?? now()->toDateTimeString();
    }

    /* ============================================================
     |  GETTERS
     ============================================================ */

    public function codigo(): string {
        return $this->codigo;
    }

    public function categoria(): string {
        return $this->categoria;
    }

    public function mensagem(): string {
        return $this->mensagem;
    }

    public function severidade(): string {
        return $this->severidade;
    }

    public function dados(): array {
        return $this->dados;
    }

    public function sugestao(): ?string {
        return $this->sugestao;
    }

    public function timestamp(): string {
        return $this->timestamp;
    }

    /* ============================================================
     |  CLASSIFICAÇÕES AUXILIARES
     ============================================================ */

    public function isCritico(): bool {
        return $this->severidade === 'CRITICA';
    }

    public function isEstrutural(): bool {
        return str_contains($this->categoria, 'ESTRUTURAL');
    }

    /* ============================================================
     |  SERIALIZAÇÃO
     ============================================================ */

    public function toArray(): array {
        return [
            'codigo' => $this->codigo,
            'categoria' => $this->categoria,
            'mensagem' => $this->mensagem,
            'severidade' => $this->severidade,
            'timestamp' => $this->timestamp,
            'dados' => $this->dados,
            'sugestao' => $this->sugestao,
        ];
    }

    /* ============================================================
     |  VALIDAÇÃO INTERNA
     ============================================================ */

    private function assertSeveridadeValida(string $severidade): void {
        if (!in_array($severidade, self::SEVERIDADES_VALIDAS, true)) {
            throw new InvalidArgumentException(
                "Severidade inválida: {$severidade}"
            );
        }
    }
}
