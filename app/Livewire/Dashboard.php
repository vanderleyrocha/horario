<?php

namespace App\Livewire;

use App\Models\Disciplina;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\ScheduleExecution;
use App\Models\Turma;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    public function getStatsProperty(): array
    {
        return [
            [
                'label' => 'Professores',
                'value' => Professor::count(),
                'ativos' => Professor::where('ativo', true)->count(),
                'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                'color' => 'blue',
                'route' => 'professores.index',
            ],
            [
                'label' => 'Turmas',
                'value' => Turma::count(),
                'ativos' => Turma::where('ativa', true)->count(),
                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                'color' => 'green',
                'route' => 'turmas.index',
            ],
            [
                'label' => 'Disciplinas',
                'value' => Disciplina::count(),
                'ativos' => Disciplina::where('ativa', true)->count(),
                'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                'color' => 'purple',
                'route' => 'disciplinas.index',
            ],
            [
                'label' => 'Horarios',
                'value' => Horario::count(),
                'ativos' => Horario::where('status', 'ativo')->count(),
                'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                'color' => 'orange',
                'route' => 'horarios.index',
            ],
            [
                'label' => 'Execucoes do Solver',
                'value' => ScheduleExecution::count(),
                'ativos' => ScheduleExecution::where('status', 'running')->count(),
                'icon' => 'M13 10V3L4 14h7v7l9-11h-7z',
                'color' => 'indigo',
                'route' => 'horarios.index',
            ],
        ];
    }

    public function getRecentActivitiesProperty(): array
    {
        $activities = [];

        foreach (Horario::latest()->take(3)->get() as $horario) {
            $activities[] = [
                'type' => 'horario',
                'message' => "Horario '{$horario->nome}' ".($horario->status === 'concluido' ? 'gerado com sucesso' : 'criado'),
                'time' => $horario->created_at?->diffForHumans(),
                'sort' => $horario->created_at?->getTimestamp() ?? 0,
                'color' => $horario->status === 'concluido' ? 'green' : 'blue',
            ];
        }

        foreach (Professor::latest()->take(2)->get() as $professor) {
            $activities[] = [
                'type' => 'professor',
                'message' => "Professor '{$professor->nome}' cadastrado",
                'time' => $professor->created_at?->diffForHumans(),
                'sort' => $professor->created_at?->getTimestamp() ?? 0,
                'color' => 'blue',
            ];
        }

        foreach (Turma::latest()->take(2)->get() as $turma) {
            $activities[] = [
                'type' => 'turma',
                'message' => "Turma '{$turma->nome}' cadastrada",
                'time' => $turma->created_at?->diffForHumans(),
                'sort' => $turma->created_at?->getTimestamp() ?? 0,
                'color' => 'green',
            ];
        }

        usort($activities, fn (array $a, array $b) => $b['sort'] <=> $a['sort']);

        return array_map(function (array $activity) {
            unset($activity['sort']);

            return $activity;
        }, array_slice($activities, 0, 5));
    }

    public function getCargaHorariaInfoProperty(): array
    {
        $totalCargaHoraria = Disciplina::sum('carga_horaria_semanal');
        $totalProfessores = Professor::where('ativo', true)->count();
        $totalTurmas = Turma::where('ativa', true)->count();

        return [
            'total' => $totalCargaHoraria,
            'media_professor' => $totalProfessores > 0 ? round($totalCargaHoraria / $totalProfessores, 1) : 0,
            'total_professores' => $totalProfessores,
            'total_turmas' => $totalTurmas,
        ];
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
