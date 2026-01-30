<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\AI;

use CodingSunshine\Architect\Services\PackageDiscovery;

/**
 * AI-powered boilerplate generation service.
 *
 * Generates complete, ready-to-use boilerplate code for:
 * - Package integrations
 * - Model setup with all features
 * - Migration with package columns
 * - Factory with realistic data
 * - Seeder with relationships
 * - Tests covering all features
 */
final class AIBoilerplateGenerator extends AIServiceBase
{
    public function __construct(
        private readonly PackageDiscovery $packageDiscovery,
        private readonly AIPackageAnalyzer $packageAnalyzer,
    ) {}

    /**
     * Generate complete boilerplate for adding a feature to a model.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     * @return array{
     *     model_additions: string,
     *     migration_additions: string,
     *     factory_additions: string,
     *     seeder_additions: string,
     *     test_additions: string,
     *     config_changes: array<string, mixed>,
     *     setup_steps: array<string>,
     * }|null
     */
    public function generateFeatureBoilerplate(string $modelName, array $modelDef, string $feature, ?array $fingerprint = null): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();
        $packageName = $this->getPackageForFeature($feature);

        if ($packageName !== null && ! isset($installed[$packageName])) {
            return [
                'model_additions' => '',
                'migration_additions' => '',
                'factory_additions' => '',
                'seeder_additions' => '',
                'test_additions' => '',
                'config_changes' => [],
                'setup_steps' => [
                    "Install the required package: composer require {$packageName}",
                    'Run: php artisan vendor:publish --provider="..."',
                    'Run: php artisan migrate',
                ],
            ];
        }

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate complete boilerplate code to add a feature to a model.
Include all necessary code changes for model, migration, factory, seeder, and tests.
Use proper Laravel conventions and ensure code is production-ready.
PROMPT);

        $userPrompt = "Generate boilerplate to add '{$feature}' feature to model '{$modelName}'.\n\n";
        $userPrompt .= 'Current model definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";

        if ($packageName !== null) {
            $analysis = $this->packageAnalyzer->analyzePackage($packageName);
            if ($analysis !== null) {
                $userPrompt .= "Package analysis:\n".json_encode($analysis, JSON_PRETTY_PRINT)."\n\n";
            }
        }

        $userPrompt .= <<<'PROMPT'
Return a JSON object with:
- model_additions: PHP code to add to the model (traits, interfaces, methods)
- migration_additions: Migration column definitions to add
- factory_additions: Factory state/definition additions
- seeder_additions: Seeder code additions
- test_additions: Test cases to add
- config_changes: Any config file changes needed (as key-value pairs)
- setup_steps: Array of setup instructions
PROMPT;

        $text = $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));

        return $text !== null ? $this->extractJson($text) : null;
    }

    /**
     * Generate a complete model file with all features.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateCompleteModel(string $modelName, array $modelDef, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();
        $features = $this->detectFeatures($modelDef);

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate a complete, production-ready Eloquent model class.
Include all traits, interfaces, relationships, casts, fillable, and methods based on the definition.
Follow Laravel 12 conventions with proper type hints and declare(strict_types=1).
PROMPT);

        $userPrompt = "Generate complete model class for '{$modelName}'.\n\n";
        $userPrompt .= 'Definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Detected features: '.implode(', ', $features)."\n";
        $userPrompt .= 'Installed packages: '.implode(', ', array_keys($installed))."\n\n";
        $userPrompt .= 'Generate a complete PHP class file, not just snippets.';

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate a complete migration file.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateCompleteMigration(string $modelName, array $modelDef, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();
        $tableName = $this->toTableName($modelName);

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate a complete migration file.
Include all columns, indexes, foreign keys, and package-specific columns.
Use Laravel 12 anonymous migration syntax.
PROMPT);

        $userPrompt = "Generate migration for model '{$modelName}' (table: '{$tableName}').\n\n";
        $userPrompt .= 'Definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Installed packages: '.implode(', ', array_keys($installed))."\n\n";
        $userPrompt .= 'Generate a complete migration file with up() and down() methods.';

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate a complete factory file.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateCompleteFactory(string $modelName, array $modelDef, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate a complete factory class with:
1. Realistic, contextual fake data (not just random strings)
2. Proper relationship handling
3. Useful states based on the model type
4. Package-specific states if applicable

Make the data feel real for the model type (e.g., Product should have product names, prices).
PROMPT);

        $userPrompt = "Generate factory for model '{$modelName}'.\n\n";
        $userPrompt .= 'Definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Installed packages: '.implode(', ', array_keys($installed))."\n\n";
        $userPrompt .= 'Generate a complete factory class file.';

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate a complete seeder file.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateCompleteSeeder(string $modelName, array $modelDef, int $count = 10, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate a complete seeder class that:
1. Creates records in the right order (respecting foreign keys)
2. Uses factories with appropriate states
3. Creates realistic data distributions
4. Handles relationships properly
5. Uses transactions for safety
PROMPT);

        $userPrompt = "Generate seeder for model '{$modelName}' creating ~{$count} records.\n\n";
        $userPrompt .= 'Definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Generate a complete seeder class file.';

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate complete test file.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateCompleteTests(string $modelName, array $modelDef, string $framework = 'pest', ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();

        $systemPrompt = $this->prependDefaultInstructions(<<<PROMPT
You are a Laravel testing expert. Generate comprehensive tests using {$framework}.
Cover:
1. Model creation and attributes
2. Relationships
3. Validation rules
4. Factory states
5. Package feature integration
6. Edge cases

Use proper assertions and test isolation.
PROMPT);

        $userPrompt = "Generate {$framework} tests for model '{$modelName}'.\n\n";
        $userPrompt .= 'Definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Installed packages: '.implode(', ', array_keys($installed))."\n\n";
        $userPrompt .= 'Generate a complete test file.';

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate API Resource class.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateResource(string $modelName, array $modelDef, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate an API Resource class that:
1. Includes all relevant fields
2. Formats dates properly
3. Includes relationships when loaded
4. Handles null values gracefully
5. Uses proper type hints
PROMPT);

        $userPrompt = "Generate API Resource for model '{$modelName}'.\n\n";
        $userPrompt .= 'Definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Generate a complete Resource class file.';

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Get the package name for a feature.
     */
    private function getPackageForFeature(string $feature): ?string
    {
        return match ($feature) {
            'media' => 'spatie/laravel-medialibrary',
            'searchable' => 'laravel/scout',
            'billable' => 'laravel/cashier',
            'sluggable' => 'spatie/laravel-sluggable',
            'tags' => 'spatie/laravel-tags',
            'activity_log' => 'spatie/laravel-activitylog',
            'roles', 'permissions' => 'spatie/laravel-permission',
            'api_tokens' => 'laravel/sanctum',
            'filament' => 'filament/filament',
            default => null,
        };
    }

    /**
     * Detect features from model definition.
     *
     * @param  array<string, mixed>  $modelDef
     * @return array<string>
     */
    private function detectFeatures(array $modelDef): array
    {
        $features = [];

        $featureKeys = ['media', 'searchable', 'billable', 'sluggable', 'tags', 'activity_log', 'roles', 'permissions', 'api_tokens', 'softDeletes'];

        foreach ($featureKeys as $key) {
            if (! empty($modelDef[$key])) {
                $features[] = $key;
            }
        }

        return $features;
    }

    /**
     * Convert model name to table name.
     */
    private function toTableName(string $modelName): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));

        return $snake.'s'; // Simple pluralization
    }
}
