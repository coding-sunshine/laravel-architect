<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\Generators;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Services\GeneratorVariantResolver;
use CodingSunshine\Architect\Services\PackageDiscovery;
use CodingSunshine\Architect\Services\StackDetector;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\FileOwnership;
use CodingSunshine\Architect\Support\HashComputer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class PageGenerator implements GeneratorInterface
{
    private const RESOURCE_VIEWS = ['index', 'create', 'show', 'edit'];

    private const POWER_GRID_PACKAGES = ['power-grid/laravel-powergrid', 'power-grid/powergrid'];

    public function __construct(
        private readonly StackDetector $stackDetector,
        private readonly GeneratorVariantResolver $variantResolver,
        private readonly PackageDiscovery $packageDiscovery,
    ) {}

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $stack = $this->resolveStack();
        $crudVariant = $this->variantResolver->resolveCrudVariant();
        $generated = [];

        foreach (array_keys($draft->pages) as $pageKey) {
            $slug = Str::kebab($pageKey);
            foreach (self::RESOURCE_VIEWS as $view) {
                $artifacts = $this->generateForStack($stack, $crudVariant, $pageKey, $slug, $view);
                foreach ($artifacts as $path => $content) {
                    File::ensureDirectoryExists(dirname($path));
                    File::put($path, $content);
                    $generated[$path] = [
                        'path' => $path,
                        'hash' => HashComputer::compute($content),
                        'ownership' => FileOwnership::Regenerate->value,
                    ];
                }
            }
        }

        return new BuildResult(generated: $generated);
    }

    public function supports(Draft $draft): bool
    {
        return $draft->pages !== [];
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
     * @return array<string, string> path => content
     */
    private function generateForStack(string $stack, string $crudVariant, string $pageKey, string $slug, string $view): array
    {
        $usePowerGrid = $crudVariant === GeneratorVariantResolver::CRUD_POWER_GRID && $view === 'index';
        $useInertiaTables = $crudVariant === GeneratorVariantResolver::CRUD_INERTIA_TABLES && $view === 'index';

        return match ($stack) {
            'inertia-react' => [
                $this->inertiaReactPath($slug, $view) => $useInertiaTables
                    ? $this->renderInertiaReactIndexWithTables($pageKey, $slug)
                    : $this->renderInertiaReact($pageKey, $slug, $view),
            ],
            'inertia-vue' => [
                $this->inertiaVuePath($slug, $view) => $useInertiaTables
                    ? $this->renderInertiaVueIndexWithTables($pageKey, $slug)
                    : $this->renderInertiaVue($pageKey, $slug, $view),
            ],
            'livewire' => $this->generateLivewire($pageKey, $slug, $view, $usePowerGrid),
            'volt' => [
                $this->voltPath($slug, $view) => $this->renderVolt($pageKey, $slug, $view),
            ],
            default => [$this->bladePath($slug, $view) => $this->renderBlade($pageKey, $slug, $view)],
        };
    }

    private function inertiaReactPath(string $slug, string $view): string
    {
        return resource_path("js/pages/{$slug}/{$view}.tsx");
    }

    private function inertiaVuePath(string $slug, string $view): string
    {
        return resource_path("js/pages/{$slug}/{$view}.vue");
    }

    private function voltPath(string $slug, string $view): string
    {
        $name = "{$slug}-{$view}";

        return resource_path("views/livewire/{$name}.blade.php");
    }

    private function bladePath(string $slug, string $view): string
    {
        return resource_path("views/pages/{$slug}/{$view}.blade.php");
    }

    private function renderInertiaReact(string $pageKey, string $slug, string $view): string
    {
        $title = Str::title($view).' '.Str::title(str_replace('-', ' ', $slug));
        $componentName = Str::studly(str_replace('-', ' ', $slug)).Str::studly($view);

        return <<<TSX
import { Head } from '@inertiajs/react';

export default function {$componentName}() {
    return (
        <>
            <Head title="{$title}" />
            <div className="p-6">
                <h1 className="text-xl font-semibold">{$title}</h1>
                <p className="mt-2 text-muted-foreground">Page: {$slug}/{$view}</p>
            </div>
        </>
    );
}

TSX;
    }

    private function renderInertiaReactIndexWithTables(string $pageKey, string $slug): string
    {
        $title = 'Index '.Str::title(str_replace('-', ' ', $slug));
        $componentName = Str::studly(str_replace('-', ' ', $slug)).'Index';

        return <<<TSX
import { Head } from '@inertiajs/react';

export default function {$componentName}() {
    return (
        <>
            <Head title="{$title}" />
            <div className="p-6">
                <h1 className="text-xl font-semibold">{$title}</h1>
                <p className="mt-2 text-muted-foreground">Data table (Inertia Tables). Wire your table component here.</p>
            </div>
        </>
    );
}

TSX;
    }

    private function renderInertiaVue(string $pageKey, string $slug, string $view): string
    {
        $title = Str::title($view).' '.Str::title(str_replace('-', ' ', $slug));
        $componentName = Str::studly(str_replace('-', ' ', $slug)).Str::studly($view);

        return <<<VUE
<template>
  <Head :title="'{$title}'" />
  <div class="p-6">
    <h1 class="text-xl font-semibold">{$title}</h1>
    <p class="mt-2 text-muted-foreground">Page: {$slug}/{$view}</p>
  </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

VUE;
    }

    private function renderInertiaVueIndexWithTables(string $pageKey, string $slug): string
    {
        $title = 'Index '.Str::title(str_replace('-', ' ', $slug));

        return <<<VUE
<template>
  <Head :title="'{$title}'" />
  <div class="p-6">
    <h1 class="text-xl font-semibold">{$title}</h1>
    <p class="mt-2 text-muted-foreground">Data table (Inertia Tables). Wire your table component here.</p>
  </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

VUE;
    }

    /**
     * @return array<string, string> path => content
     */
    private function generateLivewire(string $pageKey, string $slug, string $view, bool $usePowerGrid = false): array
    {
        $componentName = Str::studly("{$slug}-{$view}");
        $viewName = "{$slug}-{$view}";
        $classPath = app_path("Livewire/{$componentName}.php");
        $viewPath = resource_path("views/livewire/{$viewName}.blade.php");
        $classContent = $this->renderLivewireClass($componentName, $viewName, $usePowerGrid);
        $viewContent = $this->renderLivewireView($pageKey, $slug, $view, $usePowerGrid);

        return [
            $classPath => $classContent,
            $viewPath => $viewContent,
        ];
    }

    private function renderLivewireClass(string $componentName, string $viewName, bool $usePowerGrid = false): string
    {
        if ($usePowerGrid) {
            $stubHint = $this->powerGridStubPublishHint();
            $comment = $stubHint !== null
                ? "/** Power Grid: extend PowerComponents\\LivewirePowerGrid\\PowerGridComponent and implement datasource/columns.\n * {$stubHint}\n */"
                : '/** Power Grid: extend PowerComponents\LivewirePowerGrid\PowerGridComponent and implement datasource/columns. */';

            return <<<PHP
<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;

{$comment}
final class {$componentName} extends Component
{
    public function render(): string
    {
        return 'livewire.{$viewName}';
    }
}

PHP;
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;

final class {$componentName} extends Component
{
    public function render(): string
    {
        return 'livewire.{$viewName}';
    }
}

PHP;
    }

    private function renderLivewireView(string $pageKey, string $slug, string $view, bool $usePowerGrid = false): string
    {
        $title = Str::title($view).' '.Str::title(str_replace('-', ' ', $slug));

        if ($usePowerGrid) {
            $stubHint = $this->powerGridStubPublishHint();
            $comment = $stubHint !== null
                ? "{{-- Power Grid: use <livewire:powergrid.table /> or your Power Grid table component. {$stubHint} --}}"
                : '{{-- Power Grid: use <livewire:powergrid.table /> or your Power Grid table component --}}';

            return <<<BLADE
<div>
    <h1 class="text-xl font-semibold">{$title}</h1>
    {$comment}
    <div class="mt-4">Wire your Power Grid table here.</div>
</div>
BLADE;
        }

        return <<<BLADE
<div>
    <h1 class="text-xl font-semibold">{$title}</h1>
    <p class="mt-2 text-muted-foreground">Page: {$slug}/{$view}</p>
</div>
BLADE;
    }

    private function renderVolt(string $pageKey, string $slug, string $view): string
    {
        $title = Str::title($view).' '.Str::title(str_replace('-', ' ', $slug));

        return <<<BLADE
<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div>
    <h1 class="text-xl font-semibold">{$title}</h1>
    <p class="mt-2 text-muted-foreground">Page: {$slug}/{$view}</p>
</div>
BLADE;
    }

    /**
     * If Power Grid is installed and provides stubs, return a hint to publish them; otherwise null.
     */
    private function powerGridStubPublishHint(): ?string
    {
        $installed = $this->packageDiscovery->installed();
        $hasPowerGrid = false;
        foreach (self::POWER_GRID_PACKAGES as $pkg) {
            if (isset($installed[$pkg])) {
                $hasPowerGrid = true;
                break;
            }
        }
        if (! $hasPowerGrid) {
            return null;
        }
        $vendorDir = base_path('vendor');
        $stubPaths = [
            $vendorDir.'/power-grid/laravel-powergrid/stubs',
            $vendorDir.'/power-grid/powergrid/stubs',
        ];
        foreach ($stubPaths as $path) {
            if (File::isDirectory($path)) {
                return 'To use Power Grid\'s official stubs, run: php artisan vendor:publish --tag=powergrid-stubs (see Power Grid docs for the exact tag).';
            }
        }

        return null;
    }

    private function renderBlade(string $pageKey, string $slug, string $view): string
    {
        $title = Str::title($view).' '.Str::title(str_replace('-', ' ', $slug));

        return <<<BLADE
<x-layouts.app :title="'{$title}'">
    <div class="p-6">
        <h1 class="text-xl font-semibold">{$title}</h1>
        <p class="mt-2 text-muted-foreground">Page: {$slug}/{$view}</p>
    </div>
</x-layouts.app>
BLADE;
    }
}
