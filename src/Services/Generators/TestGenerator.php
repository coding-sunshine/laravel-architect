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

final class TestGenerator implements GeneratorInterface
{
    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $basePath = base_path('tests/Feature/Controllers');

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $slug = Str::kebab(Str::plural($modelName));
            $controllerName = $modelName . 'Controller';
            $testName = $controllerName . 'Test';
            $content = $this->renderTest($modelName, $slug, $controllerName, $testName);
            $path = "{$basePath}/{$testName}.php";

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
        return config('architect.conventions.generate_tests', true)
            && ($draft->modelNames() !== [] || $draft->actions !== []);
    }

    private function renderTest(string $modelName, string $slug, string $controllerName, string $testName): string
    {
        $modelFqcn = 'App\\Models\\' . $modelName;

        return <<<PHP
<?php

declare(strict_types=1);

use {$modelFqcn};

it('renders index page', function (): void {
    \$response = \$this->get(route('{$slug}.index'));

    \$response->assertOk()
        ->assertInertia(fn (\$page) => \$page->component('{$slug}/index'));
});

it('renders create page', function (): void {
    \$response = \$this->get(route('{$slug}.create'));

    \$response->assertOk()
        ->assertInertia(fn (\$page) => \$page->component('{$slug}/create'));
});

PHP;
    }
}
