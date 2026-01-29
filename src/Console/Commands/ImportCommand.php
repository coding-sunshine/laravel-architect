<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use CodingSunshine\Architect\Services\ImportService;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

final class ImportCommand extends Command
{
    protected $signature = 'architect:import
                            {--models= : Comma-separated model names to import}
                            {--output= : Path to write draft YAML (default: stdout)}
                            {--from-database : Import schema from database (not yet implemented)}';

    protected $description = 'Reverse-engineer existing codebase into draft.yaml';

    public function handle(ImportService $import): int
    {
        $modelFilter = $this->option('models')
            ? array_map('trim', explode(',', (string) $this->option('models')))
            : null;

        $draft = $import->import($modelFilter);
        $yaml = Yaml::dump($draft, 4, 2);

        $outputPath = $this->option('output');

        if ($outputPath !== null && $outputPath !== '') {
            $resolved = str_starts_with($outputPath, '/') ? $outputPath : base_path($outputPath);
            if (! file_put_contents($resolved, $yaml)) {
                $this->error("Could not write to {$resolved}");

                return self::FAILURE;
            }
            $this->info("Draft written to {$resolved}");

            return self::SUCCESS;
        }

        $this->line($yaml);

        return self::SUCCESS;
    }
}
