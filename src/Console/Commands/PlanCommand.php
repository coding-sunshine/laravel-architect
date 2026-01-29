<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use CodingSunshine\Architect\Console\Concerns\DisallowsProduction;
use CodingSunshine\Architect\Services\DraftParser;
use Illuminate\Console\Command;

final class PlanCommand extends Command
{
    use DisallowsProduction;

    protected $signature = 'architect:plan
                            {draft? : Path to draft file}';

    protected $description = 'Show what would be generated (dry run)';

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
            $draft = $parser->parse($draftPath);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Component', 'Count'],
            [
                ['Models', count($draft->models)],
                ['Actions', count($draft->actions)],
                ['Pages', count($draft->pages)],
            ]
        );

        $this->info('Run architect:build to generate.');

        return self::SUCCESS;
    }
}
