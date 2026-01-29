<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\Generators;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\FileOwnership;
use CodingSunshine\Architect\Support\HashComputer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class RouteGenerator implements GeneratorInterface
{
    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $routesPath = base_path('routes/architect.php');

        if ($draft->modelNames() === [] && $draft->routes === []) {
            return new BuildResult;
        }

        $content = $this->renderRoutesFile($draft);
        File::ensureDirectoryExists(dirname($routesPath));
        File::put($routesPath, $content);

        $generated[$routesPath] = [
            'path' => $routesPath,
            'hash' => HashComputer::compute($content),
            'ownership' => FileOwnership::Regenerate->value,
        ];

        return new BuildResult(generated: $generated);
    }

    public function supports(Draft $draft): bool
    {
        return $draft->modelNames() !== [] || $draft->routes !== [];
    }

    private function renderRoutesFile(Draft $draft): string
    {
        $resourceBlocks = [];
        foreach ($draft->modelNames() as $modelName) {
            $slug = Str::kebab(Str::plural($modelName));
            $controller = $modelName.'Controller';
            $name = Str::camel(Str::singular($modelName));
            $resourceBlocks[] = "    Route::resource('{$slug}', {$controller}::class)->names('{$name}');";
        }

        $routesBlock = implode("\n", $resourceBlocks);
        $useLines = [];
        foreach ($draft->modelNames() as $modelName) {
            $useLines[] = "use App\\Http\\Controllers\\{$modelName}Controller;";
        }
        $useBlock = implode("\n", $useLines);

        return <<<PHP
<?php

declare(strict_types=1);

{$useBlock}
use Illuminate\Support\Facades\Route;

/*
| Architect-generated resource routes. Include this file from routes/web.php:
| require base_path('routes/architect.php');
*/

Route::middleware(['auth'])->group(function (): void {
{$routesBlock}
});

PHP;
    }
}
