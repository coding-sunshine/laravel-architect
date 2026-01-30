<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use CodingSunshine\Architect\Support\BuildResult;
use Illuminate\Support\Facades\File;

final class BuildOrchestrator
{
    /**
     * @param  array<string>|null  $only
     */
    public function build(string $draftPath, ?array $only = null, bool $force = false): BuildResult
    {
        $draftPath = $this->resolveDraftPath($draftPath);

        if (! File::exists($draftPath)) {
            return BuildResult::failure(["Draft file not found: {$draftPath}"]);
        }

        $parser = app(DraftParser::class);
        $draft = $parser->parse($draftPath);
        $draftHash = ChangeDetector::computeDraftHash($draftPath);
        $state = app(StateManager::class);
        $changeDetector = app(ChangeDetector::class);

        if (! $force && ! $changeDetector->hasDraftChanged($draftPath, $draftHash)) {
            return BuildResult::noChanges();
        }

        $generators = $this->getGeneratorsToRun($only);
        $generated = [];
        $skipped = [];
        $warnings = [];
        $errors = [];

        $backup = [];
        foreach ($generators as $name => $generator) {
            if (! $generator->supports($draft)) {
                $skipped[] = $name;

                continue;
            }

            try {
                $result = $generator->generate($draft, $draftPath);
                foreach ($result->generated as $file => $meta) {
                    $generated[$file] = $meta;
                }
                foreach ($result->backup as $path => $content) {
                    $backup[$path] = $content;
                }
                $warnings = array_merge($warnings, $result->warnings);
                $errors = array_merge($errors, $result->errors);
            } catch (\Throwable $e) {
                $errors[] = "{$name}: ".$e->getMessage();
            }
        }

        if ($errors === []) {
            $state->update($draftPath, $draftHash, $generated);
            if ($backup !== []) {
                $state->saveLastBuildBackup($backup);
            }
        }

        return new BuildResult(
            generated: $generated,
            skipped: $skipped,
            warnings: $warnings,
            errors: $errors,
            success: $errors === [],
        );
    }

    /**
     * Revert last build by restoring backed-up file contents.
     *
     * @return array{success: bool, restored: array<string>, errors: array<string>}
     */
    public function revert(): array
    {
        $state = app(StateManager::class);
        $backup = $state->getLastBuildBackup();
        $restored = [];
        $errors = [];

        $base = realpath(base_path()) ?: base_path();
        foreach ($backup as $path => $content) {
            $fullPath = str_starts_with($path, '/') ? $path : base_path($path);
            $resolved = realpath(dirname($fullPath)) ?: dirname($fullPath);
            if ($resolved === false || ! str_starts_with($resolved.DIRECTORY_SEPARATOR, $base.DIRECTORY_SEPARATOR)) {
                $errors[] = "Invalid path: {$path}";

                continue;
            }
            try {
                File::ensureDirectoryExists(dirname($fullPath));
                File::put($fullPath, $content);
                $restored[] = $path;
            } catch (\Throwable $e) {
                $errors[] = "{$path}: ".$e->getMessage();
            }
        }

        $state->clearLastBuildBackup();

        return [
            'success' => $errors === [],
            'restored' => $restored,
            'errors' => $errors,
        ];
    }

    private function resolveDraftPath(string $path): string
    {
        if ($path === '' || $path === 'draft.yaml') {
            return (string) config('architect.draft_path', base_path('draft.yaml'));
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * @param  array<string>|null  $only
     * @return array<string, \CodingSunshine\Architect\Contracts\GeneratorInterface>
     */
    private function getGeneratorsToRun(?array $only): array
    {
        $all = app('architect.generators');

        if ($only === null || $only === []) {
            return $all;
        }

        $onlySet = array_fill_keys($only, true);

        $result = [];
        foreach ($all as $name => $generator) {
            if (isset($onlySet[$name])) {
                $result[$name] = $generator;
            }
        }

        return $result;
    }
}
