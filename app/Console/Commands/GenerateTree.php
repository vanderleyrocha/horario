<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DirectoryIterator;

class GenerateTree extends Command {
    protected $signature = 'tree:generate 
        {path : Caminho da pasta base}
        {--output=tree.txt : Arquivo de saída}
        {--exclude=* : Nomes de subpastas a excluir da listagem (pode ser usado múltiplas vezes)}';

    protected $description = 'Gera um arquivo .txt com a árvore de diretórios e arquivos, com opção de excluir pastas.';

    private array $excludedDirs = [];

    public function handle(): int {
        $basePath = realpath($this->argument('path'));

        if (!$basePath || !is_dir($basePath)) {
            $this->error('Caminho inválido.');
            return Command::FAILURE;
        }

        $this->excludedDirs = $this->option('exclude') ?? [];

        $lines   = [];
        $lines[] = basename($basePath);

        $this->renderDirectory($basePath, $lines);

        $output = storage_path($this->option('output'));
        file_put_contents($output, implode(PHP_EOL, $lines) . PHP_EOL);

        $this->info("Árvore gerada com sucesso em:");
        $this->line($output);

        return Command::SUCCESS;
    }

    /**
     * Renderiza um diretório respeitando:
     * - hierarquia real
     * - pastas primeiro
     * - ordem alfabética
     * - exclusão de pastas configuradas em $excludedDirs
     */
    private function renderDirectory(string $path, array &$lines, string $prefix = ''): void {
        $directories = [];
        $files       = [];

        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot()) {
                continue;
            }

            $name = $item->getFilename();

            if ($item->isDir()) {
                // Se o nome da pasta estiver na lista de exclusão, ignore completamente
                if (in_array($name, $this->excludedDirs)) {
                    continue;
                }
                $directories[] = $name;
            } else {
                $files[] = $name;
            }
        }

        sort($directories, SORT_NATURAL | SORT_FLAG_CASE);
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        $entries = array_merge(
            array_map(fn($d) => ['type' => 'dir', 'name' => $d], $directories),
            array_map(fn($f) => ['type' => 'file', 'name' => $f], $files)
        );

        $total = count($entries);

        foreach ($entries as $index => $entry) {
            $isLast = $index === $total - 1;

            $connector = $isLast ? '└── ' : '├── ';
            $lines[]   = $prefix . $connector . $entry['name'];

            if ($entry['type'] === 'dir') {
                $this->renderDirectory(
                    $path . DIRECTORY_SEPARATOR . $entry['name'],
                    $lines,
                    $prefix . ($isLast ? '    ' : '│   ')
                );
            }
        }
    }
}
