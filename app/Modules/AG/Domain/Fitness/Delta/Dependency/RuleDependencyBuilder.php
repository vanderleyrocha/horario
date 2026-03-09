<?php

namespace App\Modules\AG\Domain\Fitness\Dependency;

class RuleDependencyBuilder {
    public static function build(array $rules): RuleDependencyGraph {
        $graph = new RuleDependencyGraph();

        foreach ($rules as $rule) {

            if (!method_exists($rule, 'dependencies')) {
                continue;
            }

            $deps = $rule->dependencies();

            $graph->register(
                $rule::class,
                $deps
            );
        }

        return $graph;
    }
}
