<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class ImportService
{
    /**
     * Scan the codebase and return a draft structure (suitable for YAML export).
     *
     * @return array{models: array<string, mixed>, actions: array<string, mixed>, pages: array<string, mixed>}
     */
    public function import(?array $modelFilter = null): array
    {
        $models = $this->scanModels($modelFilter);
        $actions = $this->scanActions();
        $pages = $this->scanPages();

        return [
            'schema_version' => '1.0',
            'models' => $models,
            'actions' => $actions,
            'pages' => $pages,
        ];
    }

    /**
     * @param  array<string>|null  $modelFilter
     * @return array<string, mixed>
     */
    private function scanModels(?array $modelFilter): array
    {
        $modelsPath = app_path('Models');
        if (! File::isDirectory($modelsPath)) {
            return [];
        }

        $models = [];
        $files = File::glob($modelsPath . '/*.php');

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if ($name === 'User' && ! in_array('User', $modelFilter ?? [], true)) {
                continue;
            }
            if ($modelFilter !== null && ! in_array($name, $modelFilter, true)) {
                continue;
            }
            $models[$name] = $this->inferModelFromFile($file);
        }

        return $models;
    }

    /**
     * @return array<string, mixed>
     */
    private function inferModelFromFile(string $path): array
    {
        $content = File::get($path);
        $columns = [];

        if (preg_match_all('/\$fillable\s*=\s*\[(.*?)\]/s', $content, $m)) {
            $fillable = preg_replace('/[\s\'"]/', '', $m[1][0]);
            foreach (array_filter(explode(',', $fillable)) as $col) {
                $columns[$col] = 'string:255';
            }
        }

        if ($columns === []) {
            $columns['name'] = 'string:255';
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function scanActions(): array
    {
        $actionsPath = app_path('Actions');
        if (! File::isDirectory($actionsPath)) {
            return [];
        }

        $actions = [];
        $files = File::glob($actionsPath . '/*.php');

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $actions[$name] = ['model' => $this->inferModelFromActionName($name)];
        }

        return $actions;
    }

    private function inferModelFromActionName(string $actionName): string
    {
        if (preg_match('/^(Create|Update|Delete)(.+)$/', $actionName, $m)) {
            return $m[2];
        }

        return 'Model';
    }

    /**
     * @return array<string, mixed>
     */
    private function scanPages(): array
    {
        $pagesPath = resource_path('js/pages');
        if (! File::isDirectory($pagesPath)) {
            $pagesPath = resource_path('views/pages');
        }
        if (! File::isDirectory($pagesPath)) {
            return [];
        }

        $pages = [];
        $dirs = File::directories($pagesPath);

        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $name = Str::studly(str_replace('-', ' ', $slug));
            $pages[$name] = [];
        }

        return $pages;
    }
}
