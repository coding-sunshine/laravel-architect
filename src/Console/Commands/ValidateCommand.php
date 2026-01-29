<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use CodingSunshine\Architect\Services\DraftParser;
use Illuminate\Console\Command;

final class ValidateCommand extends Command
{
    protected $signature = 'architect:validate
                            {draft? : Path to draft file}';

    protected $description = 'Validate draft.yaml syntax and schema';

    public function handle(DraftParser $parser): int
    {
        $draftPath = $this->argument('draft') ?: config('architect.draft_path', base_path('draft.yaml'));

        if (! file_exists($draftPath)) {
            $this->error("Draft file not found: {$draftPath}");

            return self::FAILURE;
        }

        try {
            $parser->parse($draftPath);
            $this->info('Draft is valid.');

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
