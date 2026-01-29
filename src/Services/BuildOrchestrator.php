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
                $warnings = array_merge($warnings, $result->warnings);
                $errors = array_merge($errors, $result->errors);
            } catch (\Throwable $e) {
                $errors[] = "{$name}: ".$e->getMessage();
            }
        }

        if ($errors === []) {
            $state->update($draftPath, $draftHash, $generated);
        }

        return new BuildResult(
            generated: $generated,
            skipped: $skipped,
            warnings: $warnings,
            errors: $errors,
            success: $errors === [],
        );
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
