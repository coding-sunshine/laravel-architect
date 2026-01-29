<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use CodingSunshine\Architect\Console\Concerns\DisallowsProduction;
use CodingSunshine\Architect\Services\DraftParser;
use Illuminate\Console\Command;

final class FixCommand extends Command
{
    use DisallowsProduction;

    protected $signature = 'architect:fix
                            {draft? : Path to draft file}
                            {--dry-run : Show suggested fix without applying}
                            {--ai : Use AI (Prism) to suggest fix when validation fails}';

    protected $description = 'Suggest or apply fix when draft validation or build fails';

    public function handle(DraftParser $parser): int
    {
        $exit = $this->disallowProduction();
        if ($exit !== null) {
            return $exit;
        }

        $draftPath = $this->argument('draft') ?: config('architect.draft_path', base_path('draft.yaml'));

        if (! file_exists($draftPath)) {
            $this->error("Draft file not found: {$draftPath}");

            return self::FAILURE;
        }

        try {
            $parser->parse($draftPath);
            $this->info('Draft is valid. No fix needed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Validation failed: ' . $e->getMessage());

            if ($this->option('ai')) {
                $this->warn('AI fix (Prism) is not yet implemented. Fix the draft manually using the error message above.');
            } else {
                $this->line('Run architect:fix --ai when AI fix is available, or fix the draft manually. See [Troubleshooting](docs/troubleshooting.md).');
            }

            return self::FAILURE;
        }
    }
}
