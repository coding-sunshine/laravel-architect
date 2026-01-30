<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\AI;

use CodingSunshine\Architect\Services\PackageDiscovery;
use CodingSunshine\Architect\Support\Draft;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * AI-powered schema suggestion service.
 *
 * Analyzes the user's draft in real-time and suggests improvements based on:
 * - Model semantics (understanding what a "Product" or "User" model should have)
 * - Installed packages (suggesting features from available packages)
 * - Best practices (validation rules, relationships, indexes)
 * - Missing components (models that should exist based on relationships)
 */
final class AISchemaSuggestionService extends AIServiceBase
{
    public function __construct(
        private readonly PackageDiscovery $packageDiscovery,
    ) {}

    /**
     * Analyze a draft and return comprehensive suggestions.
     *
     * @return array{
     *     field_suggestions: array<string, array<array{field: string, type: string, reason: string}>>,
     *     relationship_suggestions: array<string, array<array{type: string, target: string, reason: string}>>,
     *     feature_suggestions: array<string, array<array{feature: string, reason: string}>>,
     *     missing_models: array<array{name: string, reason: string, suggested_fields: array<string>}>,
     *     validation_suggestions: array<string, array<string, array<string>>>,
     *     index_suggestions: array<string, array<string>>,
     *     naming_improvements: array<string, array{current: string, suggested: string, reason: string}>,
     * }|null
     */
    public function analyzeDraft(Draft $draft): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();

        $systemPrompt = $this->getAnalysisSystemPrompt();
        $userPrompt = $this->buildAnalysisPrompt($draft, $installed);
        $schema = $this->createSuggestionSchema();

        return $this->generateStructured($systemPrompt, $userPrompt, $schema);
    }

    /**
     * Suggest fields for a specific model based on its name and context.
     *
     * @return array<array{field: string, type: string, reason: string}>|null
     */
    public function suggestFieldsForModel(string $modelName, array $existingFields = []): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = <<<'PROMPT'
You are a Laravel expert. Suggest database fields for models based on their name and common patterns.
Return only fields that are commonly needed. Be practical and follow Laravel conventions.
PROMPT;

        $userPrompt = "Suggest database fields for a model named '{$modelName}'.\n\n";

        if ($existingFields !== []) {
            $userPrompt .= 'Already has these fields: '.implode(', ', $existingFields)."\n\n";
            $userPrompt .= "Suggest additional fields that would complement these.\n";
        }

        $userPrompt .= <<<'PROMPT'
Return a JSON array of objects with:
- field: the field name (snake_case)
- type: the column type (string, text, integer, boolean, timestamp, etc.) with modifiers
- reason: brief explanation of why this field is useful

Focus on practical, commonly-used fields. Don't suggest id, created_at, updated_at as they're automatic.
PROMPT;

        $schema = new ArraySchema(
            'field_suggestions',
            'Suggested fields for the model',
            new ObjectSchema(
                'field',
                'Field suggestion',
                [
                    new StringSchema('field', 'Field name'),
                    new StringSchema('type', 'Column type with modifiers'),
                    new StringSchema('reason', 'Reason for suggestion'),
                ],
                ['field', 'type', 'reason']
            )
        );

        $result = $this->generateStructured($systemPrompt, $userPrompt, new ObjectSchema(
            'suggestions',
            'Field suggestions',
            [$schema],
            ['field_suggestions']
        ));

        return $result['field_suggestions'] ?? null;
    }

    /**
     * Suggest relationships for a model based on context.
     *
     * @param  array<string>  $otherModels
     * @return array<array{type: string, target: string, reason: string}>|null
     */
    public function suggestRelationships(string $modelName, array $existingRelationships, array $otherModels): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = <<<'PROMPT'
You are a Laravel expert. Suggest Eloquent relationships for models based on naming conventions and common patterns.
Consider the other models in the application and suggest relationships that make sense.
PROMPT;

        $userPrompt = "Suggest relationships for model '{$modelName}'.\n\n";
        $userPrompt .= 'Other models in the app: '.implode(', ', $otherModels)."\n\n";

        if ($existingRelationships !== []) {
            $userPrompt .= 'Already has these relationships: '.implode(', ', $existingRelationships)."\n\n";
        }

        $userPrompt .= <<<'PROMPT'
Return a JSON array of objects with:
- type: relationship type (belongsTo, hasMany, hasOne, belongsToMany)
- target: target model name
- reason: brief explanation

Focus on relationships that make semantic sense. Don't suggest relationships that already exist.
PROMPT;

        $schema = new ArraySchema(
            'relationship_suggestions',
            'Suggested relationships',
            new ObjectSchema(
                'relationship',
                'Relationship suggestion',
                [
                    new StringSchema('type', 'Relationship type'),
                    new StringSchema('target', 'Target model'),
                    new StringSchema('reason', 'Reason for suggestion'),
                ],
                ['type', 'target', 'reason']
            )
        );

        $result = $this->generateStructured($systemPrompt, $userPrompt, new ObjectSchema(
            'suggestions',
            'Relationship suggestions',
            [$schema],
            ['relationship_suggestions']
        ));

        return $result['relationship_suggestions'] ?? null;
    }

    /**
     * Suggest package features that would benefit a model.
     *
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, string>  $installedPackages
     * @return array<array{feature: string, package: string, reason: string}>|null
     */
    public function suggestPackageFeatures(string $modelName, array $modelDef, array $installedPackages): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = <<<'PROMPT'
You are a Laravel expert. Based on a model's structure and the packages installed in the application,
suggest package features that would benefit the model. Only suggest features from packages that are actually installed.
PROMPT;

        $fields = array_keys(array_filter($modelDef, fn ($v) => is_string($v)));
        $packages = array_keys($installedPackages);

        $userPrompt = "Model '{$modelName}' has these fields: ".implode(', ', $fields)."\n\n";
        $userPrompt .= 'Installed packages: '.implode(', ', $packages)."\n\n";
        $userPrompt .= <<<'PROMPT'
Suggest package features using this JSON format:
- feature: the schema key to add (e.g., "media", "searchable", "sluggable")
- package: the package that provides this feature
- reason: why this model would benefit from this feature

Only suggest features that make semantic sense for this model type.
PROMPT;

        $schema = new ArraySchema(
            'feature_suggestions',
            'Suggested package features',
            new ObjectSchema(
                'feature',
                'Feature suggestion',
                [
                    new StringSchema('feature', 'Schema feature key'),
                    new StringSchema('package', 'Package name'),
                    new StringSchema('reason', 'Reason for suggestion'),
                ],
                ['feature', 'package', 'reason']
            )
        );

        $result = $this->generateStructured($systemPrompt, $userPrompt, new ObjectSchema(
            'suggestions',
            'Feature suggestions',
            [$schema],
            ['feature_suggestions']
        ));

        return $result['feature_suggestions'] ?? null;
    }

    /**
     * Detect missing models that should exist based on relationships.
     *
     * @return array<array{name: string, reason: string, suggested_fields: array<string>}>|null
     */
    public function detectMissingModels(Draft $draft): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $existingModels = $draft->modelNames();
        $allRelationshipTargets = [];

        foreach ($existingModels as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $relationships = $modelDef['relationships'] ?? [];
            foreach ($relationships as $targets) {
                if (is_string($targets)) {
                    foreach (explode(',', $targets) as $target) {
                        $targetModel = trim(explode(':', $target)[0]);
                        if ($targetModel !== '' && ! in_array($targetModel, $existingModels, true)) {
                            $allRelationshipTargets[] = $targetModel;
                        }
                    }
                }
            }

            // Check for _id fields that suggest missing models
            foreach ($modelDef as $field => $type) {
                if (is_string($type) && str_ends_with($field, '_id')) {
                    $suggestedModel = ucfirst(str_replace('_id', '', $field));
                    if (! in_array($suggestedModel, $existingModels, true)) {
                        $allRelationshipTargets[] = $suggestedModel;
                    }
                }
            }
        }

        $missingModels = array_unique($allRelationshipTargets);

        if ($missingModels === []) {
            return [];
        }

        $systemPrompt = <<<'PROMPT'
You are a Laravel expert. For each missing model, suggest basic fields that would typically be needed.
Consider the context of how the model is referenced in relationships.
PROMPT;

        $userPrompt = "These models are referenced but don't exist: ".implode(', ', $missingModels)."\n\n";
        $userPrompt .= 'Existing models: '.implode(', ', $existingModels)."\n\n";
        $userPrompt .= <<<'PROMPT'
For each missing model, return:
- name: the model name
- reason: why it should exist
- suggested_fields: array of field names that would typically be needed
PROMPT;

        $schema = new ArraySchema(
            'missing_models',
            'Missing models that should be created',
            new ObjectSchema(
                'model',
                'Missing model info',
                [
                    new StringSchema('name', 'Model name'),
                    new StringSchema('reason', 'Why it should exist'),
                    new ArraySchema('suggested_fields', 'Suggested fields', new StringSchema('field', 'Field name')),
                ],
                ['name', 'reason', 'suggested_fields']
            )
        );

        $result = $this->generateStructured($systemPrompt, $userPrompt, new ObjectSchema(
            'result',
            'Missing models',
            [$schema],
            ['missing_models']
        ));

        return $result['missing_models'] ?? null;
    }

    private function getAnalysisSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert Laravel architect analyzing a database schema. Your task is to:
1. Identify missing fields that models commonly need
2. Suggest relationships based on model semantics and naming
3. Recommend package features that would benefit specific models
4. Detect models that should exist based on relationship references
5. Suggest validation rules for fields
6. Identify fields that should be indexed
7. Suggest naming improvements for better clarity

Be practical and only suggest improvements that provide real value.
Follow Laravel naming conventions (snake_case for fields, StudlyCase for models).
PROMPT;
    }

    /**
     * @param  array<string, string>  $installed
     */
    private function buildAnalysisPrompt(Draft $draft, array $installed): string
    {
        $prompt = "Analyze this Laravel schema and suggest improvements:\n\n";
        $prompt .= "Models:\n".json_encode($draft->models, JSON_PRETTY_PRINT)."\n\n";
        $prompt .= 'Installed packages: '.implode(', ', array_keys($installed))."\n\n";
        $prompt .= <<<'PROMPT'
Return a JSON object with these keys:
- field_suggestions: {model_name: [{field, type, reason}]}
- relationship_suggestions: {model_name: [{type, target, reason}]}
- feature_suggestions: {model_name: [{feature, reason}]}
- missing_models: [{name, reason, suggested_fields}]
- validation_suggestions: {model_name: {field_name: [rules]}}
- index_suggestions: {model_name: [fields_to_index]}
- naming_improvements: {model_name: [{current, suggested, reason}]}
PROMPT;

        return $prompt;
    }

    private function createSuggestionSchema(): ObjectSchema
    {
        $fieldSuggestion = new ObjectSchema(
            'field_suggestion',
            'A suggested field',
            [
                new StringSchema('field', 'Field name'),
                new StringSchema('type', 'Column type'),
                new StringSchema('reason', 'Reason'),
            ],
            ['field', 'type', 'reason']
        );

        $relationshipSuggestion = new ObjectSchema(
            'relationship_suggestion',
            'A suggested relationship',
            [
                new StringSchema('type', 'Relationship type'),
                new StringSchema('target', 'Target model'),
                new StringSchema('reason', 'Reason'),
            ],
            ['type', 'target', 'reason']
        );

        $featureSuggestion = new ObjectSchema(
            'feature_suggestion',
            'A suggested feature',
            [
                new StringSchema('feature', 'Feature name'),
                new StringSchema('reason', 'Reason'),
            ],
            ['feature', 'reason']
        );

        $missingModel = new ObjectSchema(
            'missing_model',
            'A missing model',
            [
                new StringSchema('name', 'Model name'),
                new StringSchema('reason', 'Reason'),
                new ArraySchema('suggested_fields', 'Suggested fields', new StringSchema('field', 'Field')),
            ],
            ['name', 'reason']
        );

        $namingImprovement = new ObjectSchema(
            'naming_improvement',
            'A naming improvement',
            [
                new StringSchema('current', 'Current name'),
                new StringSchema('suggested', 'Suggested name'),
                new StringSchema('reason', 'Reason'),
            ],
            ['current', 'suggested', 'reason']
        );

        // Note: Using simpler structure due to schema limitations
        return new ObjectSchema(
            'schema_suggestions',
            'Comprehensive schema suggestions',
            [
                new ArraySchema('field_suggestions_list', 'All field suggestions', $fieldSuggestion),
                new ArraySchema('relationship_suggestions_list', 'All relationship suggestions', $relationshipSuggestion),
                new ArraySchema('feature_suggestions_list', 'All feature suggestions', $featureSuggestion),
                new ArraySchema('missing_models', 'Missing models', $missingModel),
                new ArraySchema('naming_improvements_list', 'Naming improvements', $namingImprovement),
            ],
            ['missing_models']
        );
    }
}
