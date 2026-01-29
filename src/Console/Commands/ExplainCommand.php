<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use CodingSunshine\Architect\Services\DraftParser;
use Illuminate\Console\Command;

final class ExplainCommand extends Command
{
    protected $signature = 'architect:explain
                            {draft? : Path to draft file}
                            {--json : Output as JSON}';

    protected $description = 'Output a short summary of the draft (models, actions, pages, what would be generated)';

    public function handle(DraftParser $parser): int
    {
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

        $summary = [
            'draft_path' => $draftPath,
            'models' => $draft->modelNames(),
            'actions' => array_keys($draft->actions),
            'pages' => array_keys($draft->pages),
            'model_count' => count($draft->models),
            'action_count' => count($draft->actions),
            'page_count' => count($draft->pages),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Draft summary: '.$draftPath);
        $this->newLine();
        $this->line('Models: '.($draft->modelNames() !== [] ? implode(', ', $draft->modelNames()) : '(none)'));
        $this->line('Actions: '.(array_keys($draft->actions) !== [] ? implode(', ', array_keys($draft->actions)) : '(none)'));
        $this->line('Pages: '.(array_keys($draft->pages) !== [] ? implode(', ', array_keys($draft->pages)) : '(none)'));
        $this->newLine();
        $this->line('Run architect:plan for a dry run or architect:build to generate.');

        return self::SUCCESS;
    }
}
