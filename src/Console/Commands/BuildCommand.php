<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use CodingSunshine\Architect\Services\BuildOrchestrator;
use Illuminate\Console\Command;

final class BuildCommand extends Command
{
    protected $signature = 'architect:build
                            {draft? : Path to draft file}
                            {--only=* : Only run these generators (e.g. models,actions)}
                            {--force : Overwrite scaffold_only files}';

    protected $description = 'Generate code from draft.yaml (idempotent)';

    public function handle(BuildOrchestrator $orchestrator): int
    {
        $draftPath = $this->argument('draft') ?: '';
        $only = $this->option('only');
        $only = is_array($only) && $only !== [] ? $only : null;
        $force = (bool) $this->option('force');

        $this->info('Building from draft...');

        try {
            $result = $orchestrator->build($draftPath, $only, $force);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $result->success) {
            foreach ($result->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($result->generated === [] && $result->errors === []) {
            $this->info('No changes detected. Nothing to generate.');

            return self::SUCCESS;
        }

        foreach ($result->generated as $path => $meta) {
            $this->line("  <info>Created:</info> {$path}");
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        $this->info('Build complete.');

        return self::SUCCESS;
    }
}
