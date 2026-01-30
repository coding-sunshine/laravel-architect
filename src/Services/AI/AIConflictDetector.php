<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\AI;

use CodingSunshine\Architect\Services\PackageDiscovery;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * AI-powered package conflict detection service.
 *
 * Analyzes installed packages for potential conflicts including:
 * - Namespace collisions
 * - Service provider conflicts
 * - Database table conflicts
 * - Configuration key conflicts
 * - Middleware conflicts
 * - Version incompatibilities
 */
final class AIConflictDetector extends AIServiceBase
{
    public function __construct(
        private readonly PackageDiscovery $packageDiscovery,
        private readonly AIPackageAnalyzer $packageAnalyzer,
    ) {}

    /**
     * Detect all potential conflicts in the current installation.
     *
     * @return array{
     *     conflicts: array<array{type: string, packages: array<string>, description: string, severity: string, resolution: string}>,
     *     warnings: array<array{type: string, package: string, message: string}>,
     *     recommendations: array<string>,
     * }|null
     */
    public function detectConflicts(): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();
        $laravelPackages = [];

        foreach ($installed as $name => $version) {
            if ($this->packageAnalyzer->isLaravelPackage($name)) {
                $laravelPackages[$name] = [
                    'version' => $version,
                    'hints' => $this->packageAnalyzer->getQuickHints($name),
                ];
            }
        }

        if ($laravelPackages === []) {
            return [
                'conflicts' => [],
                'warnings' => [],
                'recommendations' => [],
            ];
        }

        return $this->analyzeWithAI($laravelPackages);
    }

    /**
     * Check if a specific package would conflict with current installation.
     *
     * @return array{
     *     compatible: bool,
     *     conflicts: array<array{package: string, reason: string}>,
     *     warnings: array<string>,
     * }|null
     */
    public function checkPackageCompatibility(string $packageName): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();

        // Already installed check
        if (isset($installed[$packageName])) {
            return [
                'compatible' => true,
                'conflicts' => [],
                'warnings' => ["Package '{$packageName}' is already installed"],
            ];
        }

        $systemPrompt = <<<'PROMPT'
You are a Laravel package compatibility expert. Analyze if a new package would conflict with existing packages.
Consider common conflict patterns like authentication packages, multiple admin panels, etc.
PROMPT;

        $userPrompt = "Check if '{$packageName}' would conflict with these installed packages:\n\n";
        $userPrompt .= implode("\n", array_map(
            fn ($name, $version) => "- {$name}: {$version}",
            array_keys($installed),
            $installed
        ));

        $schema = new ObjectSchema(
            'compatibility_check',
            'Package compatibility analysis',
            [
                new \Prism\Prism\Schema\BooleanSchema('compatible', 'Whether the package is compatible'),
                new ArraySchema('conflicts', 'Conflicting packages', new ObjectSchema(
                    'conflict',
                    'Conflict info',
                    [
                        new StringSchema('package', 'Conflicting package name'),
                        new StringSchema('reason', 'Reason for conflict'),
                    ],
                    ['package', 'reason']
                )),
                new ArraySchema('warnings', 'Warning messages', new StringSchema('warning', 'Warning message')),
            ],
            ['compatible']
        );

        return $this->generateStructured($systemPrompt, $userPrompt, $schema);
    }

    /**
     * Get dependency chain for a package.
     *
     * @return array<string, array{required: bool, reason: string}>|null
     */
    public function getDependencyChain(string $packageName): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = <<<'PROMPT'
You are a Laravel package expert. Identify what dependencies a package requires,
including both Composer dependencies and Laravel-specific requirements (like Livewire for Filament).
PROMPT;

        $userPrompt = "What dependencies does '{$packageName}' require for Laravel applications?\n";
        $userPrompt .= <<<'PROMPT'
Return a JSON object where keys are package names and values are:
- required: boolean indicating if it's required or optional
- reason: why the dependency is needed

Focus on significant dependencies, not every sub-package.
PROMPT;

        $text = $this->generateText($systemPrompt, $userPrompt);

        return $text !== null ? $this->extractJson($text) : null;
    }

    /**
     * Suggest resolutions for detected conflicts.
     *
     * @param  array<array{type: string, packages: array<string>, description: string}>  $conflicts
     * @return array<array{conflict: string, resolution: string, commands: array<string>}>|null
     */
    public function suggestResolutions(array $conflicts): ?array
    {
        if (! $this->isAvailable() || $conflicts === []) {
            return [];
        }

        $systemPrompt = <<<'PROMPT'
You are a Laravel expert helping resolve package conflicts.
For each conflict, provide a practical resolution and any commands needed.
PROMPT;

        $userPrompt = "Suggest resolutions for these conflicts:\n\n";
        foreach ($conflicts as $conflict) {
            $userPrompt .= "- {$conflict['description']} (packages: ".implode(', ', $conflict['packages']).")\n";
        }

        $schema = new ArraySchema(
            'resolutions',
            'Conflict resolutions',
            new ObjectSchema(
                'resolution',
                'Resolution for a conflict',
                [
                    new StringSchema('conflict', 'Description of the conflict'),
                    new StringSchema('resolution', 'How to resolve it'),
                    new ArraySchema('commands', 'Commands to run', new StringSchema('command', 'Command')),
                ],
                ['conflict', 'resolution']
            )
        );

        $result = $this->generateStructured($systemPrompt, $userPrompt, new ObjectSchema(
            'result',
            'Resolutions',
            [$schema],
            ['resolutions']
        ));

        return $result['resolutions'] ?? null;
    }

    /**
     * Analyze packages with AI for conflicts.
     *
     * @param  array<string, array{version: string, hints: array<string, mixed>|null}>  $packages
     * @return array<string, mixed>|null
     */
    private function analyzeWithAI(array $packages): ?array
    {
        $systemPrompt = <<<'PROMPT'
You are a Laravel package conflict expert. Analyze installed packages for:
1. Direct conflicts (e.g., two auth packages, multiple admin panels)
2. Namespace collisions
3. Database table conflicts
4. Configuration conflicts
5. Version incompatibilities

Rate severity as: critical, high, medium, low
PROMPT;

        $userPrompt = "Analyze these Laravel packages for conflicts:\n\n";
        foreach ($packages as $name => $info) {
            $type = $info['hints']['type'] ?? 'unknown';
            $userPrompt .= "- {$name} v{$info['version']} ({$type})\n";
        }

        $userPrompt .= <<<'PROMPT'

Return a JSON object with:
- conflicts: array of {type, packages: string[], description, severity, resolution}
- warnings: array of {type, package, message}
- recommendations: array of strings with best practice suggestions
PROMPT;

        $schema = new ObjectSchema(
            'conflict_analysis',
            'Package conflict analysis',
            [
                new ArraySchema('conflicts', 'Detected conflicts', new ObjectSchema(
                    'conflict',
                    'Conflict details',
                    [
                        new StringSchema('type', 'Type of conflict'),
                        new ArraySchema('packages', 'Involved packages', new StringSchema('pkg', 'Package name')),
                        new StringSchema('description', 'Conflict description'),
                        new StringSchema('severity', 'Severity level'),
                        new StringSchema('resolution', 'How to resolve'),
                    ],
                    ['type', 'packages', 'description', 'severity']
                )),
                new ArraySchema('warnings', 'Warnings', new ObjectSchema(
                    'warning',
                    'Warning details',
                    [
                        new StringSchema('type', 'Warning type'),
                        new StringSchema('package', 'Package name'),
                        new StringSchema('message', 'Warning message'),
                    ],
                    ['type', 'package', 'message']
                )),
                new ArraySchema('recommendations', 'Best practice recommendations', new StringSchema('rec', 'Recommendation')),
            ],
            ['conflicts', 'warnings', 'recommendations']
        );

        return $this->generateStructured($systemPrompt, $userPrompt, $schema);
    }
}
