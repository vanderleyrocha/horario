<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CreateStructureFromTxt extends Command
{
    protected $signature = 'make:structure {file : Caminho para o arquivo .txt contendo a árvore de diretórios}';

    protected $description = 'Cria a estrutura de pastas baseada em uma representação em árvore contida em um arquivo .txt';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (! File::exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");

            return 1;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $this->error("Falha ao ler o arquivo: {$filePath}");

            return 1;
        }

        $stack = []; // armazena os nomes das pastas em cada nível de profundidade
        $basePath = base_path(); // raiz do projeto Laravel

        foreach ($lines as $line) {
            $line = rtrim($line); // remove espaços do final, mas mantém o início

            if (trim($line) === '') {
                continue;
            }

            $result = $this->parseTreeLine($line);
            if ($result === null) {
                $this->warn("Linha ignorada (não foi possível interpretar): {$line}");

                continue;
            }

            [$depth, $name] = $result;

            // Determina se é um diretório ou arquivo
            $endsWithSlash = str_ends_with($name, '/');
            $hasDot = strpos($name, '.') !== false;

            if ($endsWithSlash) {
                // É um diretório: remove a barra final
                $dirName = rtrim($name, '/');
            } elseif ($hasDot) {
                // É um arquivo (possui '.' + extensão) → ignorar
                $this->info("Ignorando arquivo: {$name}");

                continue;
            } else {
                // Sem barra e sem ponto → assume-se diretório
                $dirName = $name;
            }

            // Atualiza a pilha para a profundidade atual
            $stack[$depth] = $dirName;
            // Remove níveis mais profundos (caso existam de itens anteriores)
            $stack = array_slice($stack, 0, $depth + 1);

            // Constrói o caminho completo
            $path = $basePath.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $stack);

            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
                $this->info("Diretório criado: {$path}");
            } else {
                $this->line("Diretório já existe: {$path}");
            }
        }

        $this->info('Estrutura criada com sucesso.');

        return 0;
    }

    /**
     * Interpreta uma linha da árvore e retorna [profundidade, nome] ou null.
     */
    private function parseTreeLine(string $line): ?array
    {
        // Unidades de indentação (4 caracteres cada)
        $indentSpace = '    ';          // quatro espaços
        $indentBar = '│   ';          // barra vertical + três espaços

        // Símbolos de bifurcação (4 caracteres cada)
        $branchMid = '├── ';          // meio
        $branchLast = '└── ';          // último

        $pos = 0;
        $len = mb_strlen($line, 'UTF-8');
        $depth = 0;

        // Consome unidades de indentação
        while ($pos + 4 <= $len) {
            $chunk = mb_substr($line, $pos, 4, 'UTF-8');
            if ($chunk === $indentSpace || $chunk === $indentBar) {
                $depth++;
                $pos += 4;
            } else {
                break;
            }
        }

        // Verifica se há um símbolo de bifurcação
        if ($pos + 4 <= $len) {
            $chunk = mb_substr($line, $pos, 4, 'UTF-8');
            if ($chunk === $branchMid || $chunk === $branchLast) {
                $name = trim(mb_substr($line, $pos + 4, null, 'UTF-8'));

                return [$depth + 1, $name];
            }
        }

        // Se não havia indentação e nem bifurcação, é um item raiz (ex: "app/")
        if ($depth === 0) {
            $name = trim($line);

            return [0, $name];
        }

        // Linha com indentação mas sem bifurcação (provavelmente linhas de continuação "│")
        return null;
    }
}
