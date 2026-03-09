<?php

namespace App\Modules\AG\Domain\Fitness\Dependency;

use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;

class RuleDependencyGraph {
    private array $map = [];

    public function register(string $ruleClass, array $dependencies): void {
        foreach ($dependencies as $dependency) {
            $this->map[$dependency->value][] = $ruleClass;
        }
    }

    public function affectedRules(AffectedRegion $region): array {
        $rules = [];

        if (!empty($region->professores)) {
            $rules = array_merge(
                $rules,
                $this->map[RuleDependency::PROFESSOR->value] ?? []
            );
        }

        if (!empty($region->turmas)) {
            $rules = array_merge(
                $rules,
                $this->map[RuleDependency::TURMA->value] ?? []
            );
        }

        if (!empty($region->dias) || !empty($region->periodos)) {
            $rules = array_merge(
                $rules,
                $this->map[RuleDependency::SLOT->value] ?? []
            );
        }

        return array_unique($rules);
    }
}
