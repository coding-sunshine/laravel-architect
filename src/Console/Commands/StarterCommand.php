<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class StarterCommand extends Command
{
    protected $signature = 'architect:starter
                            {name : Starter name (blog, saas, api)}
                            {--output= : Path to write draft (default: config draft_path)}
                            {--stdout : Output YAML to stdout instead of writing file}';

    protected $description = 'Copy a starter draft to draft path or output to stdout';

    public function handle(): int
    {
        $name = $this->argument('name');
        $packageRoot = dirname(__DIR__, 3);
        $startersPath = $packageRoot.'/resources/starters';
        $path = $startersPath.'/'.$name.'.yaml';

        if (! File::exists($path)) {
            $available = collect(File::glob($startersPath.'/*.yaml'))
                ->map(fn ($p) => basename($p, '.yaml'))
                ->implode(', ');
            $this->error("Starter '{$name}' not found. Available: ".($available ?: 'none'));

            return self::FAILURE;
        }

        $content = File::get($path);

        if ($this->option('stdout')) {
            $this->line($content);

            return self::SUCCESS;
        }

        $outputPath = $this->option('output') ?: config('architect.draft_path', base_path('draft.yaml'));
        $resolved = str_starts_with($outputPath, '/') ? $outputPath : base_path($outputPath);

        if (! File::put($resolved, $content)) {
            $this->error("Could not write to {$resolved}");

            return self::FAILURE;
        }

        $this->info("Starter '{$name}' written to {$resolved}");

        return self::SUCCESS;
    }
}
