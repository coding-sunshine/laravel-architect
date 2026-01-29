<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use Illuminate\Console\Command;

final class ImportCommand extends Command
{
    protected $signature = 'architect:import
                            {--models= : Comma-separated model names to import}
                            {--from-database : Import schema from database}';

    protected $description = 'Reverse-engineer existing codebase into draft.yaml';

    public function handle(): int
    {
        $this->warn('Import is not yet implemented. Create draft.yaml manually or use architect:draft with a description.');

        return self::SUCCESS;
    }
}
