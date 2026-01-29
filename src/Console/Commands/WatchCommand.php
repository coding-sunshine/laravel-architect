<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

final class WatchCommand extends Command
{
    protected $signature = 'architect:watch
                            {--poll : Use polling instead of file system events (for limited environments)}
                            {--interval=1 : Polling interval in seconds when using --poll}';

    protected $description = 'Watch draft file and run architect:build on change';

    public function handle(): int
    {
        $draftPath = config('architect.draft_path', base_path('draft.yaml'));

        if (! file_exists($draftPath)) {
            $this->error("Draft file not found: {$draftPath}. Create a draft first.");

            return self::FAILURE;
        }

        $this->info("Watching {$draftPath}. Press Ctrl+C to stop.");
        $lastMtime = filemtime($draftPath);
        $interval = (float) $this->option('interval');
        $usePoll = $this->option('poll');

        while (true) {
            clearstatcache(true, $draftPath);

            if (! file_exists($draftPath)) {
                $this->error('Draft file was removed.');

                return self::FAILURE;
            }

            $mtime = filemtime($draftPath);

            if ($mtime > $lastMtime) {
                $lastMtime = $mtime;
                $this->info('Draft changed. Running architect:build...');
                $result = Process::run('php artisan architect:build', base_path());
                if ($result->successful()) {
                    $this->info($result->output());
                } else {
                    $this->error($result->errorOutput());
                }
            }

            if ($usePoll) {
                usleep((int) ($interval * 1_000_000));
            } else {
                usleep(500_000);
            }
        }
    }
}
