<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\Generators;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Services\StackDetector;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\FileOwnership;
use CodingSunshine\Architect\Support\HashComputer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class ControllerGenerator implements GeneratorInterface
{
    public function __construct(
        private readonly StackDetector $stackDetector
    ) {}

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $basePath = app_path('Http/Controllers');

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $controllerName = $modelName.'Controller';
            $content = $this->renderController($modelName, $draft->actions);
            $path = "{$basePath}/{$controllerName}.php";

            File::ensureDirectoryExists($basePath);
            File::put($path, $content);

            $generated[$path] = [
                'path' => $path,
                'hash' => HashComputer::compute($content),
                'ownership' => FileOwnership::Regenerate->value,
            ];
        }

        return new BuildResult(generated: $generated);
    }

    public function supports(Draft $draft): bool
    {
        return $draft->modelNames() !== [];
    }

    private function resolveStack(): string
    {
        $stack = config('architect.stack', 'auto');
        if ($stack === 'auto') {
            return $this->stackDetector->detect();
        }

        return $stack;
    }

    /**
     * @param  array<string, array<string, mixed>>  $actions
     */
    private function renderController(string $modelName, array $actions): string
    {
        $stack = $this->resolveStack();

        return match ($stack) {
            'inertia-react', 'inertia-vue' => $this->renderInertiaController($modelName, $actions),
            default => $this->renderViewController($modelName, $actions, $stack),
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $actions
     */
    private function renderInertiaController(string $modelName, array $actions): string
    {
        $slug = Str::kebab(Str::plural($modelName));
        $pagePath = $slug;
        $createAction = 'Create'.$modelName;
        $updateAction = 'Update'.$modelName;
        $deleteAction = 'Delete'.$modelName;
        $storeRequest = 'Store'.$modelName.'Request';
        $updateRequest = 'Update'.$modelName.'Request';
        $deleteRequest = 'Delete'.$modelName.'Request';
        $hasCreate = isset($actions[$createAction]);
        $hasUpdate = isset($actions[$updateAction]);
        $hasDelete = isset($actions[$deleteAction]);

        $methods = [];
        $methods[] = $this->renderMethod('index', "Inertia::render('{$pagePath}/index')", 'Response', [], true);
        $methods[] = $this->renderMethod('create', "Inertia::render('{$pagePath}/create')", 'Response', [], true);
        if ($hasCreate) {
            $methods[] = $this->renderMethod('store', "// \$action->handle(\$request->validated());\n        return redirect()->route('{$slug}.index')", 'RedirectResponse', [
                ['type' => $storeRequest, 'name' => 'request'],
                ['type' => $createAction, 'name' => 'action'],
            ], false);
        }
        $methods[] = $this->renderMethod('show', "Inertia::render('{$pagePath}/show', ['{$slug}' => \${$slug}])", 'Response', [
            ['type' => $modelName, 'name' => $slug],
        ], true);
        if ($hasUpdate) {
            $methods[] = $this->renderMethod('edit', "Inertia::render('{$pagePath}/edit', ['{$slug}' => \${$slug}])", 'Response', [
                ['type' => $modelName, 'name' => $slug],
            ], true);
            $methods[] = $this->renderMethod('update', "// \$action->handle(\${$slug}, \$request->validated());\n        return redirect()->route('{$slug}.show', \${$slug})", 'RedirectResponse', [
                ['type' => $updateRequest, 'name' => 'request'],
                ['type' => $modelName, 'name' => $slug],
                ['type' => $updateAction, 'name' => 'action'],
            ], false);
        }
        if ($hasDelete) {
            $methods[] = $this->renderMethod('destroy', "// \$action->handle(\${$slug});\n        return redirect()->route('{$slug}.index')", 'RedirectResponse', [
                ['type' => $deleteRequest, 'name' => 'request'],
                ['type' => $modelName, 'name' => $slug],
                ['type' => $deleteAction, 'name' => 'action'],
            ], false);
        }

        $useLines = [
            'use App\\Models\\'.$modelName.';',
            'use Illuminate\\Http\\RedirectResponse;',
            'use Inertia\\Inertia;',
            'use Inertia\\Response;',
        ];
        if ($hasCreate) {
            $useLines[] = 'use App\\Actions\\'.$createAction.';';
            $useLines[] = 'use App\\Http\\Requests\\'.$storeRequest.';';
        }
        if ($hasUpdate) {
            $useLines[] = 'use App\\Actions\\'.$updateAction.';';
            $useLines[] = 'use App\\Http\\Requests\\'.$updateRequest.';';
        }
        if ($hasDelete) {
            $useLines[] = 'use App\\Actions\\'.$deleteAction.';';
            $useLines[] = 'use App\\Http\\Requests\\'.$deleteRequest.';';
        }

        return $this->buildControllerClass($modelName, $useLines, $methods);
    }

    /**
     * @param  array<string, array<string, mixed>>  $actions
     */
    private function renderViewController(string $modelName, array $actions, string $stack): string
    {
        $slug = Str::kebab(Str::plural($modelName));
        $createAction = 'Create'.$modelName;
        $updateAction = 'Update'.$modelName;
        $deleteAction = 'Delete'.$modelName;
        $storeRequest = 'Store'.$modelName.'Request';
        $updateRequest = 'Update'.$modelName.'Request';
        $deleteRequest = 'Delete'.$modelName.'Request';
        $hasCreate = isset($actions[$createAction]);
        $hasUpdate = isset($actions[$updateAction]);
        $hasDelete = isset($actions[$deleteAction]);

        $viewBase = $stack === 'blade' ? 'pages.'.$slug.'.' : 'livewire.'.$slug.'-';

        $methods = [];
        $methods[] = $this->renderMethod('index', "return view('{$viewBase}index')", '\\Illuminate\\View\\View', [], true);
        $methods[] = $this->renderMethod('create', "return view('{$viewBase}create')", '\\Illuminate\\View\\View', [], true);
        if ($hasCreate) {
            $methods[] = $this->renderMethod('store', "// \$action->handle(\$request->validated());\n        return redirect()->route('{$slug}.index')", 'RedirectResponse', [
                ['type' => $storeRequest, 'name' => 'request'],
                ['type' => $createAction, 'name' => 'action'],
            ], false);
        }
        $methods[] = $this->renderMethod('show', "return view('{$viewBase}show', ['{$slug}' => \${$slug}])", '\\Illuminate\\View\\View', [
            ['type' => $modelName, 'name' => $slug],
        ], true);
        if ($hasUpdate) {
            $methods[] = $this->renderMethod('edit', "return view('{$viewBase}edit', ['{$slug}' => \${$slug}])", '\\Illuminate\\View\\View', [
                ['type' => $modelName, 'name' => $slug],
            ], true);
            $methods[] = $this->renderMethod('update', "// \$action->handle(\${$slug}, \$request->validated());\n        return redirect()->route('{$slug}.show', \${$slug})", 'RedirectResponse', [
                ['type' => $updateRequest, 'name' => 'request'],
                ['type' => $modelName, 'name' => $slug],
                ['type' => $updateAction, 'name' => 'action'],
            ], false);
        }
        if ($hasDelete) {
            $methods[] = $this->renderMethod('destroy', "// \$action->handle(\${$slug});\n        return redirect()->route('{$slug}.index')", 'RedirectResponse', [
                ['type' => $deleteRequest, 'name' => 'request'],
                ['type' => $modelName, 'name' => $slug],
                ['type' => $deleteAction, 'name' => 'action'],
            ], false);
        }

        $useLines = [
            'use App\\Models\\'.$modelName.';',
            'use Illuminate\\Http\\RedirectResponse;',
        ];
        if ($hasCreate) {
            $useLines[] = 'use App\\Actions\\'.$createAction.';';
            $useLines[] = 'use App\\Http\\Requests\\'.$storeRequest.';';
        }
        if ($hasUpdate) {
            $useLines[] = 'use App\\Actions\\'.$updateAction.';';
            $useLines[] = 'use App\\Http\\Requests\\'.$updateRequest.';';
        }
        if ($hasDelete) {
            $useLines[] = 'use App\\Actions\\'.$deleteAction.';';
            $useLines[] = 'use App\\Http\\Requests\\'.$deleteRequest.';';
        }

        return $this->buildControllerClass($modelName, $useLines, $methods);
    }

    /**
     * @param  array<string>  $useLines
     * @param  array<string>  $methods
     */
    private function buildControllerClass(string $modelName, array $useLines, array $methods): string
    {
        $useBlock = implode("\n", $useLines);
        $methodsBlock = implode("\n\n", $methods);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

{$useBlock}

final readonly class {$modelName}Controller
{
{$methodsBlock}
}

PHP;
    }

    /**
     * @param  array<int, array{type: string, name: string}>  $params
     */
    private function renderMethod(string $name, string $body, string $returnType, array $params, bool $returnStatement): string
    {
        $paramStr = implode(', ', array_map(fn (array $p) => "{$p['type']} \${$p['name']}", $params));
        $ret = $returnStatement ? 'return ' : '';
        $semicolon = $returnStatement ? ';' : '';

        return "    public function {$name}({$paramStr}): {$returnType}\n    {\n        {$ret}{$body}{$semicolon}\n    }";
    }
}
