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

final class PageGenerator implements GeneratorInterface
{
    private const RESOURCE_VIEWS = ['index', 'create', 'show', 'edit'];

    public function __construct(
        private readonly StackDetector $stackDetector
    ) {}

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $stack = $this->resolveStack();
        $generated = [];

        foreach (array_keys($draft->pages) as $pageKey) {
            $slug = Str::kebab($pageKey);
            foreach (self::RESOURCE_VIEWS as $view) {
                $artifacts = $this->generateForStack($stack, $pageKey, $slug, $view);
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
    private function generateForStack(string $stack, string $pageKey, string $slug, string $view): array
    {
        return match ($stack) {
            'inertia-react' => [$this->inertiaReactPath($slug, $view) => $this->renderInertiaReact($pageKey, $slug, $view)],
            'inertia-vue' => [$this->inertiaVuePath($slug, $view) => $this->renderInertiaVue($pageKey, $slug, $view)],
            'livewire' => $this->generateLivewire($pageKey, $slug, $view),
            'volt' => [$this->voltPath($slug, $view) => $this->renderVolt($pageKey, $slug, $view)],
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

    /**
     * @return array<string, string> path => content
     */
    private function generateLivewire(string $pageKey, string $slug, string $view): array
    {
        $componentName = Str::studly("{$slug}-{$view}");
        $viewName = "{$slug}-{$view}";
        $classPath = app_path("Livewire/{$componentName}.php");
        $viewPath = resource_path("views/livewire/{$viewName}.blade.php");
        $classContent = $this->renderLivewireClass($componentName, $viewName);
        $viewContent = $this->renderLivewireView($pageKey, $slug, $view);

        return [
            $classPath => $classContent,
            $viewPath => $viewContent,
        ];
    }

    private function renderLivewireClass(string $componentName, string $viewName): string
    {
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

    private function renderLivewireView(string $pageKey, string $slug, string $view): string
    {
        $title = Str::title($view).' '.Str::title(str_replace('-', ' ', $slug));

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
