<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\Disciplina;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuário admin
        // User::factory()->create([
        //     'name' => 'Admin',
        //     'email' => 'admin@horarios.com',
        //     'password' => bcrypt('password'),
        // ]);

        // // Professores de exemplo
        // Professor::create([
        //     'nome' => 'João Silva',
        //     'email' => 'joao@escola.com',
        //     'telefone' => '(11) 98765-4321',
        //     'dias_disponiveis' => ['segunda', 'terca', 'quarta', 'quinta', 'sexta'],
        //     'carga_horaria_maxima' => 40,
        //     'ativo' => true,
        // ]);

        // Professor::create([
        //     'nome' => 'Maria Santos',
        //     'email' => 'maria@escola.com',
        //     'telefone' => '(11) 98765-1234',
        //     'dias_disponiveis' => ['segunda', 'quarta', 'sexta'],
        //     'carga_horaria_maxima' => 30,
        //     'ativo' => true,
        // ]);

        // Professor::create([
        //     'nome' => 'Carlos Oliveira',
        //     'email' => 'carlos@escola.com',
        //     'dias_disponiveis' => ['terca', 'quinta'],
        //     'carga_horaria_maxima' => 20,
        //     'ativo' => true,
        // ]);

        // Turmas
        // Turma::create([
        //     'nome' => '1º Ano A',
        //     'codigo' => '1A',
        //     'turno' => 'matutino',
        //     'numero_alunos' => 35,
        //     'ano' => 2026,
        //     'ativa' => true,
        // ]);

        // Turma::create([
        //     'nome' => '1º Ano B',
        //     'codigo' => '1B',
        //     'turno' => 'matutino',
        //     'numero_alunos' => 32,
        //     'ano' => 2026,
        //     'ativa' => true,
        // ]);

        // Turma::create([
        //     'nome' => '2º Ano A',
        //     'codigo' => '2A',
        //     'turno' => 'vespertino',
        //     'numero_alunos' => 30,
        //     'ano' => 2026,
        //     'ativa' => true,
        // ]);

        // Turma::create([
        //     'nome' => '3º Ano A',
        //     'codigo' => '3A',
        //     'turno' => 'noturno',
        //     'numero_alunos' => 28,
        //     'ano' => 2026,
        //     'ativa' => true,
        // ]);

        // Turma::create([
        //     'nome' => '3º Ano B',
        //     'codigo' => '3B',
        //     'turno' => 'noturno',
        //     'numero_alunos' => 25,
        //     'ano' => 2026,
        //     'ativa' => true,
        // ]);

        // Disciplinas
        Disciplina::create([
            'nome' => 'Matemática',
            'codigo' => 'MAT',
            'carga_horaria_semanal' => 5,
            'descricao' => 'Disciplina de matemática básica e avançada',
            'cor' => '#3B82F6',
            'ativa' => true,
        ]);

        Disciplina::create([
            'nome' => 'Português',
            'codigo' => 'POR',
            'carga_horaria_semanal' => 4,
            'descricao' => 'Língua portuguesa e literatura',
            'cor' => '#10B981',
            'ativa' => true,
        ]);

        Disciplina::create([
            'nome' => 'História',
            'codigo' => 'HIS',
            'carga_horaria_semanal' => 3,
            'descricao' => 'História do Brasil e geral',
            'cor' => '#F59E0B',
            'ativa' => true,
        ]);

        Disciplina::create([
            'nome' => 'Geografia',
            'codigo' => 'GEO',
            'carga_horaria_semanal' => 3,
            'descricao' => 'Geografia física e humana',
            'cor' => '#06B6D4',
            'ativa' => true,
        ]);

        Disciplina::create([
            'nome' => 'Física',
            'codigo' => 'FIS',
            'carga_horaria_semanal' => 4,
            'descricao' => 'Física clássica e moderna',
            'cor' => '#8B5CF6',
            'ativa' => true,
        ]);

        Disciplina::create([
            'nome' => 'Química',
            'codigo' => 'QUI',
            'carga_horaria_semanal' => 4,
            'descricao' => 'Química orgânica e inorgânica',
            'cor' => '#EC4899',
            'ativa' => true,
        ]);

        Disciplina::create([
            'nome' => 'Biologia',
            'codigo' => 'BIO',
            'carga_horaria_semanal' => 3,
            'descricao' => 'Ciências biológicas',
            'cor' => '#14B8A6',
            'ativa' => true,
        ]);

        Disciplina::create([
            'nome' => 'Inglês',
            'codigo' => 'ING',
            'carga_horaria_semanal' => 2,
            'descricao' => 'Língua inglesa',
            'cor' => '#EF4444',
            'ativa' => true,
        ]);

        Disciplina::create([
            'nome' => 'Educação Física',
            'codigo' => 'EDF',
            'carga_horaria_semanal' => 2,
            'descricao' => 'Atividades físicas e esportivas',
            'cor' => '#F97316',
            'ativa' => true,
        ]);

        Disciplina::create([
            'nome' => 'Artes',
            'codigo' => 'ART',
            'carga_horaria_semanal' => 2,
            'descricao' => 'Artes visuais e música',
            'cor' => '#6366F1',
            'ativa' => true,
        ]);
    }
}
