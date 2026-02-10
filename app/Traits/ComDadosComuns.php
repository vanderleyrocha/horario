<?php
// app/Traits/ComDadosComuns.php

namespace App\Traits;

use App\Models\Professor;
use App\Models\Disciplina;
use App\Models\Turma;

trait ComDadosComuns
{
    public function getProfessoresProperty()
    {
        return Professor::ativo()
            ->orderBy('nome')
            ->get();
    }

    public function getDisciplinasProperty()
    {
        return Disciplina::ativa()
            ->orderBy('nome')
            ->get();
    }

    public function getTurmasProperty()
    {
        return Turma::ativa()
            ->orderBy('nome')
            ->get();
    }
}
