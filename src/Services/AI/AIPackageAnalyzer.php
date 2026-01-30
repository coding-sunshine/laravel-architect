<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * AI-powered package analyzer that dynamically discovers package capabilities.
 *
 * Instead of maintaining a static list of known packages, this service uses AI
 * to analyze package documentation, source code, and metadata to understand
 * what traits, interfaces, migrations, and configurations a package provides.
 */
final class AIPackageAnalyzer extends AIServiceBase
{
    private const CACHE_PREFIX = 'architect:package:analysis:';

    private const CACHE_TTL_HOURS = 24;

    /**
     * Analyze a package and return its capabilities.
     *
     * @return array{
     *     traits: array<string>,
     *     interfaces: array<string>,
     *     migrations: array<array{table: string, columns: array<string>}>,
     *     config_keys: array<string>,
     *     artisan_commands: array<string>,
     *     draft_extensions: array<string>,
     *     model_methods: array<string>,
     *     relationships: array<string>,
     *     setup_steps: array<string>,
     * }|null
     */
    public function analyzePackage(string $packageName, bool $useCache = true): ?array
    {
        $cacheKey = self::CACHE_PREFIX.md5($packageName);

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Gather information from multiple sources
        $composerJson = $this->readPackageComposer($packageName);
        $readme = $this->readPackageReadme($packageName);
        $serviceProviders = $this->findServiceProviders($packageName);

        if ($composerJson === null) {
            return null;
        }

        $analysis = $this->analyzeWithAI($packageName, $composerJson, $readme, $serviceProviders);

        if ($analysis !== null && $useCache) {
            Cache::put($cacheKey, $analysis, now()->addHours(self::CACHE_TTL_HOURS));
        }

        return $analysis;
    }

    /**
     * Analyze multiple packages in batch.
     *
     * @param  array<string>  $packageNames
     * @return array<string, array<string, mixed>|null>
     */
    public function analyzePackages(array $packageNames): array
    {
        $results = [];

        foreach ($packageNames as $packageName) {
            $results[$packageName] = $this->analyzePackage($packageName);
        }

        return $results;
    }

    /**
     * Check if a package is a Laravel package (has service providers).
     */
    public function isLaravelPackage(string $packageName): bool
    {
        $composerJson = $this->readPackageComposer($packageName);

        if ($composerJson === null) {
            return false;
        }

        // Check for Laravel-specific indicators
        $hasLaravelAutoDiscovery = isset($composerJson['extra']['laravel']['providers']);
        $requiresLaravel = isset($composerJson['require']['laravel/framework'])
            || isset($composerJson['require']['illuminate/support']);

        return $hasLaravelAutoDiscovery || $requiresLaravel;
    }

    /**
     * Get quick hints without full AI analysis (for faster responses).
     *
     * @return array{description: string, type: string, category: string}|null
     */
    public function getQuickHints(string $packageName): ?array
    {
        $composerJson = $this->readPackageComposer($packageName);

        if ($composerJson === null) {
            return null;
        }

        $description = $composerJson['description'] ?? '';
        $keywords = $composerJson['keywords'] ?? [];

        $type = $this->inferPackageType($packageName, $description, $keywords);
        $category = $this->inferPackageCategory($packageName, $description, $keywords);

        return [
            'description' => $description,
            'type' => $type,
            'category' => $category,
        ];
    }

    /**
     * Fetch package info from Packagist.
     *
     * @return array<string, mixed>|null
     */
    public function fetchPackagistInfo(string $packageName): ?array
    {
        $cacheKey = self::CACHE_PREFIX.'packagist:'.md5($packageName);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::timeout(10)->get("https://packagist.org/packages/{$packageName}.json");

            if ($response->successful()) {
                $data = $response->json();
                Cache::put($cacheKey, $data, now()->addHours(self::CACHE_TTL_HOURS));

                return $data;
            }
        } catch (\Throwable) {
            // Ignore HTTP errors
        }

        return null;
    }

    /**
     * Read the package's composer.json file.
     *
     * @return array<string, mixed>|null
     */
    private function readPackageComposer(string $packageName): ?array
    {
        $path = base_path("vendor/{$packageName}/composer.json");

        if (! File::exists($path)) {
            return null;
        }

        try {
            $content = File::get($path);

            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Read the package's README file.
     */
    private function readPackageReadme(string $packageName): ?string
    {
        $basePath = base_path("vendor/{$packageName}");
        $possibleNames = ['README.md', 'readme.md', 'Readme.md', 'README.MD'];

        foreach ($possibleNames as $name) {
            $path = "{$basePath}/{$name}";
            if (File::exists($path)) {
                $content = File::get($path);

                // Truncate to avoid token limits
                return mb_substr($content, 0, 8000);
            }
        }

        return null;
    }

    /**
     * Find service providers in the package.
     *
     * @return array<string>
     */
    private function findServiceProviders(string $packageName): array
    {
        $composerJson = $this->readPackageComposer($packageName);

        if ($composerJson === null) {
            return [];
        }

        // Check Laravel auto-discovery
        $providers = $composerJson['extra']['laravel']['providers'] ?? [];

        if ($providers !== []) {
            return $providers;
        }

        // Try to find service providers in source
        $srcPath = base_path("vendor/{$packageName}/src");
        if (! File::isDirectory($srcPath)) {
            return [];
        }

        $foundProviders = [];
        $files = File::glob("{$srcPath}/*ServiceProvider.php");

        foreach ($files as $file) {
            $content = File::get($file);
            if (preg_match('/namespace\s+([\w\\\\]+);/', $content, $nsMatch)) {
                if (preg_match('/class\s+(\w+)/', $content, $classMatch)) {
                    $foundProviders[] = $nsMatch[1].'\\'.$classMatch[1];
                }
            }
        }

        return $foundProviders;
    }

    /**
     * Use AI to analyze the package and extract capabilities.
     *
     * @param  array<string, mixed>  $composerJson
     * @param  array<string>  $serviceProviders
     * @return array<string, mixed>|null
     */
    private function analyzeWithAI(
        string $packageName,
        array $composerJson,
        ?string $readme,
        array $serviceProviders,
    ): ?array {
        $systemPrompt = <<<'PROMPT'
You are an expert Laravel developer analyzing a Composer package. Your task is to identify:
1. Traits the package provides for models
2. Interfaces models should implement
3. Database tables/migrations the package creates
4. Configuration keys to check
5. Artisan commands the package provides
6. Schema extensions for Architect (e.g., "media: true" enables HasMedia)
7. Methods that should be added to models
8. Relationships the package enables
9. Setup steps required after installation

Return a JSON object with these exact keys. Use empty arrays for categories that don't apply.
PROMPT;

        $userPrompt = "Analyze this Laravel package: {$packageName}\n\n";
        $userPrompt .= "Composer.json:\n".json_encode($composerJson, JSON_PRETTY_PRINT)."\n\n";

        if ($readme !== null) {
            $userPrompt .= "README (excerpt):\n{$readme}\n\n";
        }

        if ($serviceProviders !== []) {
            $userPrompt .= 'Service Providers: '.implode(', ', $serviceProviders)."\n\n";
        }

        $userPrompt .= <<<'PROMPT'
Return a JSON object with these keys:
- traits: array of fully-qualified trait names (e.g., "Spatie\MediaLibrary\InteractsWithMedia")
- interfaces: array of fully-qualified interface names
- migrations: array of {table: string, columns: string[]}
- config_keys: array of config keys to check (e.g., "media-library.disk_name")
- artisan_commands: array of command signatures (e.g., "media-library:regenerate")
- draft_extensions: array of schema keys (e.g., "media: true enables file uploads")
- model_methods: array of method names that should be added to models
- relationships: array of relationship types the package enables
- setup_steps: array of setup instructions
PROMPT;

        $schema = $this->createAnalysisSchema();
        $result = $this->generateStructured($systemPrompt, $userPrompt, $schema);

        return $result;
    }

    /**
     * Create the schema for structured analysis output.
     */
    private function createAnalysisSchema(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'package_analysis',
            description: 'Analysis of a Laravel package capabilities',
            properties: [
                new ArraySchema('traits', 'Fully-qualified trait class names', new StringSchema('trait', 'Trait class name')),
                new ArraySchema('interfaces', 'Fully-qualified interface class names', new StringSchema('interface', 'Interface class name')),
                new ArraySchema('migrations', 'Database migrations the package provides', new ObjectSchema(
                    'migration',
                    'Migration info',
                    [
                        new StringSchema('table', 'Table name'),
                        new ArraySchema('columns', 'Column names', new StringSchema('column', 'Column name')),
                    ],
                    ['table']
                )),
                new ArraySchema('config_keys', 'Configuration keys to check', new StringSchema('key', 'Config key')),
                new ArraySchema('artisan_commands', 'Artisan command signatures', new StringSchema('command', 'Command signature')),
                new ArraySchema('draft_extensions', 'Schema extensions for Architect drafts', new StringSchema('extension', 'Extension description')),
                new ArraySchema('model_methods', 'Methods to add to models', new StringSchema('method', 'Method name')),
                new ArraySchema('relationships', 'Relationship types enabled', new StringSchema('relationship', 'Relationship type')),
                new ArraySchema('setup_steps', 'Setup instructions', new StringSchema('step', 'Setup step')),
            ],
            requiredFields: ['traits', 'interfaces', 'draft_extensions'],
        );
    }

    /**
     * Infer the package type from metadata.
     */
    private function inferPackageType(string $packageName, string $description, array $keywords): string
    {
        $descLower = strtolower($description);
        $keywordsLower = array_map('strtolower', $keywords);

        if (str_contains($packageName, 'filament') || in_array('filament', $keywordsLower, true)) {
            return 'admin-panel';
        }
        if (str_contains($descLower, 'authentication') || str_contains($descLower, 'auth')) {
            return 'authentication';
        }
        if (str_contains($descLower, 'permission') || str_contains($descLower, 'role')) {
            return 'authorization';
        }
        if (str_contains($descLower, 'media') || str_contains($descLower, 'upload')) {
            return 'media';
        }
        if (str_contains($descLower, 'search') || str_contains($descLower, 'scout')) {
            return 'search';
        }
        if (str_contains($descLower, 'payment') || str_contains($descLower, 'billing')) {
            return 'payment';
        }
        if (str_contains($descLower, 'queue') || str_contains($descLower, 'job')) {
            return 'queue';
        }
        if (str_contains($descLower, 'test') || str_contains($packageName, 'pest')) {
            return 'testing';
        }

        return 'utility';
    }

    /**
     * Infer the package category from metadata.
     */
    private function inferPackageCategory(string $packageName, string $description, array $keywords): string
    {
        if (str_starts_with($packageName, 'laravel/')) {
            return 'laravel-official';
        }
        if (str_starts_with($packageName, 'spatie/')) {
            return 'spatie';
        }
        if (str_starts_with($packageName, 'filament/')) {
            return 'filament';
        }
        if (str_starts_with($packageName, 'livewire/')) {
            return 'livewire';
        }

        return 'community';
    }
}
