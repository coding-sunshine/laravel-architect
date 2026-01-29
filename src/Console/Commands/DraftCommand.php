<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use CodingSunshine\Architect\Services\DraftGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class DraftCommand extends Command
{
    protected $signature = 'architect:draft
                            {description? : Natural language description of the application or feature}
                            {--extend= : Path to existing draft to extend}
                            {--output= : Output path for generated draft}
                            {--interactive : Ask clarifying questions}';

    protected $description = 'Generate draft.yaml from natural language description (AI-powered when Prism is available)';

    public function handle(DraftGenerator $generator): int
    {
        $description = $this->argument('description');

        if ($description === null || $description === '') {
            $description = $this->ask('Describe the application or feature to scaffold');
            if ($description === null || $description === '') {
                $this->error('Description is required.');

                return self::FAILURE;
            }
        }

        $extend = $this->option('extend');
        $output = $this->option('output') ?: config('architect.draft_path', base_path('draft.yaml'));

        $this->info('Generating draft...');

        try {
            $yaml = $generator->generate($description, $extend);
            File::ensureDirectoryExists(dirname($output));
            File::put($output, $yaml);
            $this->info("Draft written to: {$output}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
