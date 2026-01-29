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

final class PageGenerator implements GeneratorInterface
{
    private const RESOURCE_VIEWS = ['index', 'create', 'show', 'edit'];

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $basePath = resource_path('js/pages');

        foreach (array_keys($draft->pages) as $pageKey) {
            $slug = Str::kebab($pageKey);
            $dir = "{$basePath}/{$slug}";
            File::ensureDirectoryExists($dir);

            foreach (self::RESOURCE_VIEWS as $view) {
                $content = $this->renderPage($pageKey, $slug, $view);
                $path = "{$dir}/{$view}.tsx";
                File::put($path, $content);
                $generated[$path] = [
                    'path' => $path,
                    'hash' => HashComputer::compute($content),
                    'ownership' => FileOwnership::Regenerate->value,
                ];
            }
        }

        return new BuildResult(generated: $generated);
    }

    public function supports(Draft $draft): bool
    {
        return $draft->pages !== [];
    }

    private function renderPage(string $pageKey, string $slug, string $view): string
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
}
