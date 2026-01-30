<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\Generators;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Services\GeneratorVariantResolver;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\FileOwnership;
use CodingSunshine\Architect\Support\HashComputer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class TestGenerator implements GeneratorInterface
{
    public function __construct(
        private readonly GeneratorVariantResolver $variantResolver,
    ) {}

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $basePath = base_path('tests/Feature/Controllers');
        $testFramework = $this->variantResolver->resolveTestFramework();
        $stack = $this->variantResolver->resolveStack();

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $slug = Str::kebab(Str::plural($modelName));
            $controllerName = $modelName.'Controller';
            $testName = $controllerName.'Test';

            $content = $testFramework === GeneratorVariantResolver::TEST_PEST
                ? $this->renderPestTest($modelName, $slug, $stack)
                : $this->renderPhpUnitTest($modelName, $slug, $controllerName, $testName, $stack);

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

    private function renderPestTest(string $modelName, string $slug, string $stack): string
    {
        $modelFqcn = 'App\\Models\\'.$modelName;
        $assertions = $this->getAssertions($stack, $slug);

        return <<<PHP
<?php

declare(strict_types=1);

use {$modelFqcn};

it('renders index page', function (): void {
    \$response = \$this->get(route('{$slug}.index'));

    \$response->assertOk(){$assertions['index']};
});

it('renders create page', function (): void {
    \$response = \$this->get(route('{$slug}.create'));

    \$response->assertOk(){$assertions['create']};
});

it('stores a new {$slug}', function (): void {
    \$data = {$modelName}::factory()->make()->toArray();

    \$response = \$this->post(route('{$slug}.store'), \$data);

    \$response->assertRedirect(route('{$slug}.index'));
    \$this->assertDatabaseHas('{$slug}', \$data);
});

it('renders show page', function (): void {
    \${$slug} = {$modelName}::factory()->create();

    \$response = \$this->get(route('{$slug}.show', \${$slug}));

    \$response->assertOk(){$assertions['show']};
});

it('renders edit page', function (): void {
    \${$slug} = {$modelName}::factory()->create();

    \$response = \$this->get(route('{$slug}.edit', \${$slug}));

    \$response->assertOk(){$assertions['edit']};
});

it('updates an existing {$slug}', function (): void {
    \${$slug} = {$modelName}::factory()->create();
    \$data = {$modelName}::factory()->make()->toArray();

    \$response = \$this->put(route('{$slug}.update', \${$slug}), \$data);

    \$response->assertRedirect(route('{$slug}.index'));
    \$this->assertDatabaseHas('{$slug}', \$data);
});

it('deletes a {$slug}', function (): void {
    \${$slug} = {$modelName}::factory()->create();

    \$response = \$this->delete(route('{$slug}.destroy', \${$slug}));

    \$response->assertRedirect(route('{$slug}.index'));
    \$this->assertDatabaseMissing('{$slug}', ['id' => \${$slug}->id]);
});

PHP;
    }

    private function renderPhpUnitTest(string $modelName, string $slug, string $controllerName, string $testName, string $stack): string
    {
        $modelFqcn = 'App\\Models\\'.$modelName;
        $assertions = $this->getAssertions($stack, $slug);
        $singularSlug = Str::singular($slug);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\\Feature\\Controllers;

use {$modelFqcn};
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

final class {$testName} extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_renders(): void
    {
        \$response = \$this->get(route('{$slug}.index'));

        \$response->assertOk(){$assertions['index']};
    }

    public function test_create_page_renders(): void
    {
        \$response = \$this->get(route('{$slug}.create'));

        \$response->assertOk(){$assertions['create']};
    }

    public function test_store_creates_new_record(): void
    {
        \$data = {$modelName}::factory()->make()->toArray();

        \$response = \$this->post(route('{$slug}.store'), \$data);

        \$response->assertRedirect(route('{$slug}.index'));
        \$this->assertDatabaseHas('{$slug}', \$data);
    }

    public function test_show_page_renders(): void
    {
        \${$singularSlug} = {$modelName}::factory()->create();

        \$response = \$this->get(route('{$slug}.show', \${$singularSlug}));

        \$response->assertOk(){$assertions['show']};
    }

    public function test_edit_page_renders(): void
    {
        \${$singularSlug} = {$modelName}::factory()->create();

        \$response = \$this->get(route('{$slug}.edit', \${$singularSlug}));

        \$response->assertOk(){$assertions['edit']};
    }

    public function test_update_modifies_existing_record(): void
    {
        \${$singularSlug} = {$modelName}::factory()->create();
        \$data = {$modelName}::factory()->make()->toArray();

        \$response = \$this->put(route('{$slug}.update', \${$singularSlug}), \$data);

        \$response->assertRedirect(route('{$slug}.index'));
        \$this->assertDatabaseHas('{$slug}', \$data);
    }

    public function test_destroy_deletes_record(): void
    {
        \${$singularSlug} = {$modelName}::factory()->create();

        \$response = \$this->delete(route('{$slug}.destroy', \${$singularSlug}));

        \$response->assertRedirect(route('{$slug}.index'));
        \$this->assertDatabaseMissing('{$slug}', ['id' => \${$singularSlug}->id]);
    }
}

PHP;
    }

    /**
     * Get stack-specific test assertions.
     *
     * @return array<string, string>
     */
    private function getAssertions(string $stack, string $slug): array
    {
        return match ($stack) {
            GeneratorVariantResolver::STACK_INERTIA_REACT,
            GeneratorVariantResolver::STACK_INERTIA_VUE => [
                'index' => "\n        ->assertInertia(fn (\$page) => \$page->component('{$slug}/index'))",
                'create' => "\n        ->assertInertia(fn (\$page) => \$page->component('{$slug}/create'))",
                'show' => "\n        ->assertInertia(fn (\$page) => \$page->component('{$slug}/show'))",
                'edit' => "\n        ->assertInertia(fn (\$page) => \$page->component('{$slug}/edit'))",
            ],
            GeneratorVariantResolver::STACK_LIVEWIRE,
            GeneratorVariantResolver::STACK_VOLT => [
                'index' => "\n        ->assertSeeLivewire('{$slug}-index')",
                'create' => "\n        ->assertSeeLivewire('{$slug}-create')",
                'show' => "\n        ->assertSeeLivewire('{$slug}-show')",
                'edit' => "\n        ->assertSeeLivewire('{$slug}-edit')",
            ],
            default => [
                'index' => "\n        ->assertViewIs('pages.{$slug}.index')",
                'create' => "\n        ->assertViewIs('pages.{$slug}.create')",
                'show' => "\n        ->assertViewIs('pages.{$slug}.show')",
                'edit' => "\n        ->assertViewIs('pages.{$slug}.edit')",
            ],
        };
    }
}
