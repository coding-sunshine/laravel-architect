<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Commands;

use CodingSunshine\Architect\Console\Concerns\DisallowsProduction;
use CodingSunshine\Architect\Services\PackageDiscovery;
use CodingSunshine\Architect\Services\PackageRegistry;
use Illuminate\Console\Command;

final class PackagesCommand extends Command
{
    use DisallowsProduction;

    protected $signature = 'architect:packages
                            {--json : Output as JSON}';

    protected $description = 'List detected Composer packages and Architect-known packages with suggestions';

    public function handle(PackageDiscovery $discovery, PackageRegistry $registry): int
    {
        $exit = $this->disallowProduction();
        if ($exit !== null) {
            return $exit;
        }

        $installed = $discovery->installed();

        if ($installed === []) {
            $this->warn('No Composer packages detected. Run composer install?');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->outputJson($installed, $registry);

            return self::SUCCESS;
        }

        $this->outputTable($installed, $registry);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $installed
     */
    private function outputTable(array $installed, PackageRegistry $registry): void
    {
        $rows = [];

        foreach ($installed as $name => $version) {
            $hints = $registry->get($name);
            $known = $hints !== null ? 'Yes' : 'No';
            $suggestions = $hints !== null && $hints['suggested_commands'] !== []
                ? implode(', ', $hints['suggested_commands'])
                : '-';
            $rows[] = [$name, $version, $known, $suggestions];
        }

        $this->table(['Package', 'Version', 'Known to Architect', 'Suggested commands'], $rows);

        $knownPackages = array_filter(array_keys($installed), fn (string $name): bool => $registry->isKnown($name));

        if ($knownPackages !== []) {
            $this->newLine();
            $this->info('Known packages: ' . implode(', ', $knownPackages));

            foreach ($knownPackages as $name) {
                $hints = $registry->get($name);
                if ($hints === null) {
                    continue;
                }

                if ($hints['draft_extensions'] !== []) {
                    $this->line('  ' . $name . ' draft extensions: ' . implode('; ', $hints['draft_extensions']));
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $installed
     */
    private function outputJson(array $installed, PackageRegistry $registry): void
    {
        $packages = [];

        foreach ($installed as $name => $version) {
            $hints = $registry->get($name);
            $packages[] = [
                'name' => $name,
                'version' => $version,
                'known' => $hints !== null,
                'hints' => $hints,
            ];
        }

        $this->line(json_encode(['packages' => $packages], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
