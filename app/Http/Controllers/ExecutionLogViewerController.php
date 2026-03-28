<?php

namespace App\Http\Controllers;

use App\Models\ScheduleExecution;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExecutionLogViewerController extends Controller
{
    public function __invoke(Request $request, ScheduleExecution $execution): Response
    {
        $tailLines = max(50, min(500, (int) $request->integer('lines', 180)));
        $chunks = [];

        foreach ($this->resolveLogFiles() as $label => $path) {
            $content = $this->filterRelevantLogLines(
                lines: $this->tailFile($path, $tailLines),
                execution: $execution
            );

            if ($content === []) {
                continue;
            }

            $chunks[] = sprintf(
                "===== %s (%s) =====\n%s",
                $label,
                basename($path),
                implode("\n", $content)
            );
        }

        if ($chunks === []) {
            $chunks[] = sprintf(
                "Nenhum trecho relevante encontrado para a execucao #%d nos logs atuais.\n\nArquivos inspecionados:\n- %s",
                $execution->id,
                implode("\n- ", array_values($this->resolveLogFiles()))
            );
        }

        return response(
            content: implode("\n\n", $chunks),
            status: 200,
            headers: ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    /**
     * @return array<string, string>
     */
    private function resolveLogFiles(): array
    {
        $files = [
            'Laravel' => storage_path('logs/laravel.log'),
        ];

        $gaFiles = glob(storage_path('logs/ga-*.log')) ?: [];
        rsort($gaFiles);

        if ($gaFiles !== []) {
            $files['GA'] = $gaFiles[0];
        }

        return array_filter($files, static fn (string $path): bool => is_file($path));
    }

    /**
     * @return list<string>
     */
    private function tailFile(string $path, int $tailLines): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES) ?: [];

        if ($lines === []) {
            return [];
        }

        return array_values(array_slice($lines, -1 * $tailLines));
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function filterRelevantLogLines(array $lines, ScheduleExecution $execution): array
    {
        $needles = [
            '"execution_id":'.$execution->id,
            '"execution_id": '.$execution->id,
            "'execution_id': ".$execution->id,
            '[ID: '.$execution->horario_id.']',
            'horario_id":'.$execution->horario_id,
            'horario_id": '.$execution->horario_id,
            "schedule_execution_id={$execution->id}",
        ];

        $matches = array_values(array_filter($lines, static function (string $line) use ($needles): bool {
            foreach ($needles as $needle) {
                if (str_contains($line, $needle)) {
                    return true;
                }
            }

            return false;
        }));

        return $matches === [] ? $lines : $matches;
    }
}
