<?php

namespace App\Modules\AG\Domain\Fitness\Dependency;

enum RuleDependency: string {
    case PROFESSOR = 'professor';
    case TURMA = 'turma';
    case DIA = 'dia';
    case PERIODO = 'periodo';
    case SLOT = 'slot';
}
