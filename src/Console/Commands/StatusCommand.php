<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use CodingSunshine\Architect\Services\StateManager;
use Illuminate\Console\Command;

final class StatusCommand extends Command
{
    protected $signature = 'architect:status';

    protected $description = 'Show current architect state and generated files';

    public function handle(StateManager $state): int
    {
        $data = $state->load();

        $this->info('Architect state');
        $this->line('Version: '.($data['version'] ?? 'unknown'));
        $this->line('Last run: '.($data['lastRun'] ?? 'never'));
        $this->newLine();

        $generated = $data['generated'] ?? [];
        if ($generated === []) {
            $this->comment('No generated files tracked yet. Run architect:build first.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($generated as $path => $meta) {
            $rows[] = [
                $path,
                is_array($meta) ? ($meta['hash'] ?? '') : '',
                is_array($meta) ? ($meta['ownership'] ?? '') : '',
            ];
        }

        $this->table(['Path', 'Hash', 'Ownership'], $rows);

        return self::SUCCESS;
    }
}
