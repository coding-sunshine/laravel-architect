<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use CodingSunshine\Architect\Services\DraftParser;
use Illuminate\Console\Command;

final class CheckCommand extends Command
{
    protected $signature = 'architect:check
                            {draft? : Path to draft file}';

    protected $description = 'Run validate + plan and show checklist (draft valid, etc.)';

    public function handle(DraftParser $parser): int
    {
        $draftPath = $this->argument('draft') ?: config('architect.draft_path', base_path('draft.yaml'));
        $checks = [];

        $checks['draft_exists'] = file_exists($draftPath);
        $checks['draft_valid'] = false;
        if ($checks['draft_exists']) {
            try {
                $parser->parse($draftPath);
                $checks['draft_valid'] = true;
            } catch (\Throwable) {
                // leave false
            }
        }

        $this->table(
            ['Check', 'Status'],
            [
                ['Draft file exists', $checks['draft_exists'] ? 'Yes' : 'No'],
                ['Draft valid (schema)', $checks['draft_valid'] ? 'Yes' : 'No'],
            ]
        );

        if (! $checks['draft_exists']) {
            $this->error('Draft file not found. Create draft.yaml or run architect:starter blog.');

            return self::FAILURE;
        }

        if (! $checks['draft_valid']) {
            $this->error('Draft failed validation. Run architect:validate for details.');

            return self::FAILURE;
        }

        $this->info('All checks passed. Run architect:plan for dry run or architect:build to generate.');

        return self::SUCCESS;
    }
}
