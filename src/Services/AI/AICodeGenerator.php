<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\AI;

use CodingSunshine\Architect\Services\PackageDiscovery;

/**
 * AI-powered code generation service.
 *
 * Generates contextually-aware code for:
 * - Package integrations (traits, interfaces, methods)
 * - Migrations with package-specific columns
 * - Factories with realistic data
 * - Seeders with meaningful content
 * - Tests with proper coverage
 */
final class AICodeGenerator extends AIServiceBase
{
    public function __construct(
        private readonly PackageDiscovery $packageDiscovery,
    ) {}

    /**
     * Generate package integration code for a model.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generatePackageIntegration(string $modelName, array $modelDef, string $packageName, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate the PHP code needed to integrate a package into a model.
Return only the code (traits, interfaces, methods) that needs to be added, with proper namespaces.
Do not include the full class, just the additions. Use proper Laravel conventions.
PROMPT);

        $userPrompt = "Generate code to integrate '{$packageName}' into model '{$modelName}'.\n\n";
        $userPrompt .= 'Model definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= <<<'PROMPT'
Return PHP code including:
1. Any use statements for traits
2. Any interface implementations
3. Required methods (e.g., registerMediaConversions, getSlugOptions)
4. Any boot method additions

Format as valid PHP that can be inserted into a class.
PROMPT;

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate migration columns for a model with package awareness.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateMigrationColumns(string $modelName, array $modelDef, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate migration column definitions for a model.
Consider the installed packages and add any columns they require.
Use proper Laravel migration syntax with methods like $table->string(), $table->foreignId(), etc.
Include indexes where appropriate.
PROMPT);

        $userPrompt = "Generate migration columns for model '{$modelName}'.\n\n";
        $userPrompt .= 'Model definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Installed packages: '.implode(', ', array_keys($installed))."\n\n";
        $userPrompt .= <<<'PROMPT'
Return PHP code for Schema::create callback including:
1. All columns from the definition
2. Package-specific columns (e.g., stripe_id for Cashier)
3. Proper foreign key constraints
4. Indexes for commonly queried fields
5. Soft delete columns if needed

Format as the body of Schema::create callback (no function wrapper).
PROMPT;

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate factory definition with realistic data.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateFactory(string $modelName, array $modelDef, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate a factory definition that produces realistic, contextual data.
Use Faker methods appropriate to each field type. Consider the model's semantic meaning.
For example, a Product model should have realistic product names, not random strings.
PROMPT);

        $userPrompt = "Generate factory definition for model '{$modelName}'.\n\n";
        $userPrompt .= 'Model fields: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= <<<'PROMPT'
Return a PHP array that would go in the definition() method.
Use appropriate Faker methods like:
- fake()->sentence() for titles
- fake()->paragraphs(3, true) for content
- fake()->randomFloat(2, 10, 1000) for prices
- fake()->boolean() for boolean fields
- fake()->dateTimeBetween('-1 year', 'now') for timestamps

Make the data realistic for the model type.
PROMPT;

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate factory states based on installed packages.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateFactoryStates(string $modelName, array $modelDef, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate factory states that are useful for testing.
Consider the installed packages and create states that leverage their features.
For example, if spatie/laravel-permission is installed, create admin/moderator states.
PROMPT);

        $userPrompt = "Generate factory states for model '{$modelName}'.\n\n";
        $userPrompt .= 'Model fields: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Installed packages: '.implode(', ', array_keys($installed))."\n\n";
        $userPrompt .= <<<'PROMPT'
Return PHP methods for factory states like:
public function admin(): static { return $this->state(...); }
public function published(): static { return $this->state(...); }
public function withMedia(): static { return $this->afterCreating(...); }

Only include states that make sense for this model type.
PROMPT;

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate seeder with meaningful data.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateSeeder(string $modelName, array $modelDef, int $count = 10, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate a seeder that creates meaningful, realistic data.
Consider relationships and create data in the right order.
Use factories with appropriate states. Include comments explaining the data strategy.
PROMPT);

        $userPrompt = "Generate seeder for model '{$modelName}' with approximately {$count} records.\n\n";
        $userPrompt .= 'Model definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= <<<'PROMPT'
Return the run() method body including:
1. Creating related models first if needed
2. Using factories with appropriate states
3. Meaningful distribution (e.g., 80% active, 20% inactive)
4. Realistic data relationships

Use Laravel conventions and proper model namespaces.
PROMPT;

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate comprehensive test cases for a model.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateTests(string $modelName, array $modelDef, string $framework = 'pest', ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel testing expert. Generate comprehensive test cases that cover:
1. Model creation and validation
2. Relationships
3. Scopes and queries
4. Package feature integration
5. Edge cases and error handling

Use the specified testing framework (Pest or PHPUnit).
PROMPT);

        $userPrompt = "Generate tests for model '{$modelName}' using {$framework}.\n\n";
        $userPrompt .= 'Model definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Installed packages: '.implode(', ', array_keys($installed))."\n\n";
        $userPrompt .= <<<'PROMPT'
Return test code including:
1. Model creation tests
2. Validation tests
3. Relationship tests
4. Factory state tests
5. Package integration tests (if applicable)

For Pest: Use it() syntax with closures
For PHPUnit: Use test_ prefix methods in a class
PROMPT;

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate controller methods with proper responses.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateControllerMethods(string $modelName, array $modelDef, string $stack = 'inertia-react', ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel expert. Generate controller methods that follow best practices:
1. Use actions for business logic (not inline in controllers)
2. Return appropriate responses for the stack (Inertia, API, etc.)
3. Handle authorization
4. Include proper type hints and return types
PROMPT);

        $userPrompt = "Generate controller methods for model '{$modelName}' using {$stack} stack.\n\n";
        $userPrompt .= 'Model definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= <<<'PROMPT'
Return PHP methods for:
1. index() - list resources
2. create() - show create form
3. store() - create resource
4. show() - view single resource
5. edit() - show edit form
6. update() - update resource
7. destroy() - delete resource

Use thin controllers that delegate to Actions.
PROMPT;

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate Form Request validation rules.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generateFormRequest(string $modelName, array $modelDef, string $action = 'store', ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel validation expert. Generate Form Request rules that are:
1. Comprehensive but not excessive
2. Using the correct rule syntax for Laravel 12
3. Including custom error messages
4. Considering the action type (store vs update)
PROMPT);

        $userPrompt = "Generate {$action} Form Request for model '{$modelName}'.\n\n";
        $userPrompt .= 'Model fields: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= <<<'PROMPT'
Return a complete Form Request class including:
1. rules() method with validation rules
2. messages() method with custom error messages
3. authorize() method (return true for now)
4. Proper type hints and namespaces

For update requests, make unique rules ignore the current record.
PROMPT;

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }

    /**
     * Generate Policy methods.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, mixed>|null  $fingerprint  Optional app fingerprint from AppModelService
     */
    public function generatePolicy(string $modelName, array $modelDef, ?array $fingerprint = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();
        $hasPermissions = isset($installed['spatie/laravel-permission']);

        $systemPrompt = $this->prependDefaultInstructions(<<<'PROMPT'
You are a Laravel authorization expert. Generate Policy methods that:
1. Cover all standard CRUD operations
2. Consider ownership (user can edit their own resources)
3. Use role/permission checks if Spatie Permission is available
4. Include sensible defaults
PROMPT);

        $userPrompt = "Generate Policy for model '{$modelName}'.\n\n";
        $userPrompt .= 'Model definition: '.json_encode($modelDef, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Has Spatie Permission package: '.($hasPermissions ? 'Yes' : 'No')."\n\n";
        $userPrompt .= <<<'PROMPT'
Return a complete Policy class including:
1. viewAny() - can view list
2. view() - can view single record
3. create() - can create
4. update() - can update (consider ownership)
5. delete() - can delete
6. restore() - can restore soft-deleted
7. forceDelete() - can permanently delete

Use proper type hints and return types.
PROMPT;

        return $this->generateText($systemPrompt, $this->appendFingerprintContext($userPrompt, $fingerprint));
    }
}
