<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use CodingSunshine\Architect\Support\Draft;

final class BuildPlanner
{
    /**
     * Returns a sequence of build steps for the given draft.
     * When orchestration.command_first is true, the orchestrator can run
     * artisan steps first, then patch or generate.
     *
     * @return array<int, array{type: string, name: string, description: string, command?: string, generator?: string}>
     */
    public function plan(Draft $draft): array
    {
        $steps = [];

        foreach ($draft->modelNames() as $modelName) {
            $steps[] = [
                'type' => 'artisan',
                'name' => 'model:' . $modelName,
                'description' => "Run make:model {$modelName} -m -f",
                'command' => "make:model {$modelName} -m -f",
            ];
            $steps[] = [
                'type' => 'generate',
                'name' => 'patch_model:' . $modelName,
                'description' => "Patch model fillable/casts from draft",
                'generator' => 'model',
            ];
            $steps[] = [
                'type' => 'generate',
                'name' => 'patch_migration:' . $modelName,
                'description' => "Patch migration from draft",
                'generator' => 'migration',
            ];
        }

        $steps[] = [
            'type' => 'generate',
            'name' => 'factory',
            'description' => 'Generate factories',
            'generator' => 'factory',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'seeder',
            'description' => 'Generate seeders',
            'generator' => 'seeder',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'action',
            'description' => 'Generate actions',
            'generator' => 'action',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'controller',
            'description' => 'Generate controllers',
            'generator' => 'controller',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'request',
            'description' => 'Generate form requests',
            'generator' => 'request',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'route',
            'description' => 'Generate routes',
            'generator' => 'route',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'page',
            'description' => 'Generate pages',
            'generator' => 'page',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'typescript',
            'description' => 'Generate TypeScript types',
            'generator' => 'typescript',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'test',
            'description' => 'Generate tests',
            'generator' => 'test',
        ];

        return $steps;
    }
}
