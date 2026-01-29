<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class StudioContextService
{
    public function __construct(
        private readonly StackDetector $stackDetector,
        private readonly PackageDiscovery $packageDiscovery,
        private readonly PackageRegistry $packageRegistry,
        private readonly ImportService $importService,
    ) {}

    /**
     * Build context payload for the Studio UI (stack, packages, models, paths, ai, starters).
     *
     * @return array{stack: string, packages: array<int, array{name: string, version: string, hints: array|null}, existing_models: array<int, array{name: string, table: string}>, draft_path: string, state_path: string, schema_version: string, ai_enabled: bool, starters: array<int, string>}
     */
    public function build(): array
    {
        $stack = config('architect.stack', 'auto');
        if ($stack === 'auto') {
            $stack = $this->stackDetector->detect();
        }

        $installed = $this->packageDiscovery->installed();
        $packages = [];
        foreach ($installed as $name => $version) {
            $hints = $this->packageRegistry->get($name);
            $packages[] = [
                'name' => $name,
                'version' => $version,
                'hints' => $hints,
            ];
        }

        $existingModels = $this->existingModels();

        $draftPath = config('architect.draft_path', base_path('draft.yaml'));
        $statePath = config('architect.state_path', base_path('.architect-state.json'));
        $schemaVersion = '1.0';

        $aiEnabled = config('architect.ai.enabled', true)
            && class_exists(\Prism\Prism\Facades\Prism::class);

        $starters = $this->starterNames();

        return [
            'stack' => $stack,
            'packages' => $packages,
            'existing_models' => $existingModels,
            'draft_path' => $draftPath,
            'state_path' => $statePath,
            'schema_version' => $schemaVersion,
            'ai_enabled' => $aiEnabled,
            'starters' => $starters,
        ];
    }

    /**
     * @return array<int, array{name: string, table: string}>
     */
    private function existingModels(): array
    {
        $draft = $this->importService->import(null);
        $models = $draft['models'] ?? [];
        $out = [];
        foreach (array_keys($models) as $name) {
            $out[] = [
                'name' => $name,
                'table' => Str::snake(Str::plural($name)),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function starterNames(): array
    {
        $packageRoot = dirname(__DIR__, 2);
        $startersPath = $packageRoot . '/resources/starters';
        if (! File::isDirectory($startersPath)) {
            return [];
        }

        $files = File::glob($startersPath . '/*.yaml');
        if (! is_array($files)) {
            return [];
        }

        $names = [];
        foreach ($files as $path) {
            $names[] = basename($path, '.yaml');
        }

        sort($names);

        return $names;
    }
}
