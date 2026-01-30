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
     * @return array<int, array{type: string, name: string, description: string, command?: string, generator?: string, path_hint?: string}>
     */
    public function plan(Draft $draft): array
    {
        $steps = [];
        $basePath = app()->path();

        foreach ($draft->modelNames() as $modelName) {
            $steps[] = [
                'type' => 'artisan',
                'name' => 'model:'.$modelName,
                'description' => "Run make:model {$modelName} -m -f",
                'command' => "make:model {$modelName} -m -f",
                'path_hint' => $basePath.'/Models/'.$modelName.'.php',
            ];
            $steps[] = [
                'type' => 'generate',
                'name' => 'patch_model:'.$modelName,
                'description' => 'Patch model fillable/casts from draft',
                'generator' => 'model',
                'path_hint' => $basePath.'/Models/'.$modelName.'.php',
            ];
            $steps[] = [
                'type' => 'generate',
                'name' => 'patch_migration:'.$modelName,
                'description' => 'Patch migration from draft',
                'generator' => 'migration',
                'path_hint' => 'database/migrations/*_create_'.strtolower($modelName).'_table.php',
            ];
        }

        $steps[] = [
            'type' => 'generate',
            'name' => 'factory',
            'description' => 'Generate factories',
            'generator' => 'factory',
            'path_hint' => 'database/factories/',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'seeder',
            'description' => 'Generate seeders',
            'generator' => 'seeder',
            'path_hint' => 'database/seeders/',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'action',
            'description' => 'Generate actions',
            'generator' => 'action',
            'path_hint' => $basePath.'/Actions/',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'controller',
            'description' => 'Generate controllers',
            'generator' => 'controller',
            'path_hint' => $basePath.'/Http/Controllers/',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'request',
            'description' => 'Generate form requests',
            'generator' => 'request',
            'path_hint' => $basePath.'/Http/Requests/',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'route',
            'description' => 'Generate routes',
            'generator' => 'route',
            'path_hint' => 'routes/',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'page',
            'description' => 'Generate pages',
            'generator' => 'page',
            'path_hint' => 'resources/js/pages/',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'typescript',
            'description' => 'Generate TypeScript types',
            'generator' => 'typescript',
            'path_hint' => 'resources/js/types/',
        ];
        $steps[] = [
            'type' => 'generate',
            'name' => 'test',
            'description' => 'Generate tests',
            'generator' => 'test',
            'path_hint' => 'tests/',
        ];

        return $steps;
    }
}
