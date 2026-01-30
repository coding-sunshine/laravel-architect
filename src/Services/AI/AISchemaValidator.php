<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\AI;

use CodingSunshine\Architect\Services\PackageDiscovery;
use CodingSunshine\Architect\Support\Draft;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * AI-powered schema validation service.
 *
 * Validates schemas against best practices including:
 * - N+1 query risks
 * - Missing indexes
 * - Security issues
 * - Performance concerns
 * - Data integrity
 * - Laravel conventions
 */
final class AISchemaValidator extends AIServiceBase
{
    public function __construct(
        private readonly PackageDiscovery $packageDiscovery,
    ) {}

    /**
     * Validate a draft schema and return comprehensive results.
     *
     * @return array{
     *     valid: bool,
     *     score: int,
     *     issues: array<array{severity: string, category: string, model?: string, field?: string, message: string, suggestion: string}>,
     *     best_practices: array<array{category: string, status: string, message: string}>,
     *     performance_warnings: array<array{model: string, issue: string, impact: string, fix: string}>,
     *     security_concerns: array<array{model: string, field?: string, issue: string, risk: string, mitigation: string}>,
     * }|null
     */
    public function validate(Draft $draft): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();

        $systemPrompt = $this->getValidationSystemPrompt();
        $userPrompt = $this->buildValidationPrompt($draft, $installed);
        $schema = $this->createValidationSchema();

        $result = $this->generateStructured($systemPrompt, $userPrompt, $schema);

        if ($result !== null) {
            // Calculate overall validity and score
            $result['valid'] = $this->calculateValidity($result);
            $result['score'] = $this->calculateScore($result);
        }

        return $result;
    }

    /**
     * Quick validation for real-time feedback.
     *
     * @param  array<string, mixed>  $modelDef
     * @return array<array{type: string, message: string}>
     */
    public function quickValidate(string $modelName, array $modelDef): array
    {
        $issues = [];

        // Check for common issues without AI
        foreach ($modelDef as $field => $type) {
            if (! is_string($type)) {
                continue;
            }

            // Missing nullable on optional fields
            if (str_ends_with($field, '_at') && ! str_contains($type, 'nullable') && $field !== 'created_at' && $field !== 'updated_at') {
                $issues[] = [
                    'type' => 'warning',
                    'message' => "Field '{$field}' might need nullable modifier",
                ];
            }

            // Foreign key without index
            if (str_ends_with($field, '_id') && ! str_contains($type, 'index') && ! str_contains($type, 'foreign')) {
                $issues[] = [
                    'type' => 'performance',
                    'message' => "Field '{$field}' should have an index for better query performance",
                ];
            }

            // Password without hash reminder
            if ($field === 'password' && ! str_contains($type, 'hashed')) {
                $issues[] = [
                    'type' => 'security',
                    'message' => 'Password fields should always be hashed - ensure model uses casts or mutators',
                ];
            }

            // Email without unique
            if ($field === 'email' && ! str_contains($type, 'unique')) {
                $issues[] = [
                    'type' => 'warning',
                    'message' => 'Email fields are typically unique - consider adding unique constraint',
                ];
            }
        }

        // Check relationships
        $relationships = $modelDef['relationships'] ?? [];
        if ($relationships === [] && $modelName !== 'User') {
            // Check for _id fields without corresponding belongsTo
            foreach ($modelDef as $field => $type) {
                if (is_string($type) && str_ends_with($field, '_id')) {
                    $issues[] = [
                        'type' => 'warning',
                        'message' => "Found '{$field}' but no belongsTo relationship defined",
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Validate a specific aspect of the schema.
     *
     * @return array<array{issue: string, suggestion: string}>|null
     */
    public function validateAspect(Draft $draft, string $aspect): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = match ($aspect) {
            'performance' => 'You are a Laravel performance expert. Analyze the schema for N+1 risks, missing indexes, and query optimization opportunities.',
            'security' => 'You are a Laravel security expert. Analyze the schema for mass assignment vulnerabilities, sensitive data exposure, and authorization gaps.',
            'relationships' => 'You are a Laravel relationships expert. Analyze the schema for missing or incorrect relationships, circular dependencies, and orphaned records risks.',
            'naming' => 'You are a Laravel conventions expert. Analyze the schema for naming convention violations and suggest improvements.',
            default => 'You are a Laravel expert. Analyze the schema for issues.',
        };

        $userPrompt = "Analyze this schema for {$aspect} issues:\n\n";
        $userPrompt .= json_encode($draft->models, JSON_PRETTY_PRINT);
        $userPrompt .= "\n\nReturn an array of {issue: string, suggestion: string} objects.";

        $text = $this->generateText($systemPrompt, $userPrompt);

        return $text !== null ? $this->extractJson($text) : null;
    }

    /**
     * Get recommendations for improving the schema.
     *
     * @return array<array{category: string, recommendation: string, priority: string, implementation: string}>|null
     */
    public function getRecommendations(Draft $draft): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $installed = $this->packageDiscovery->installed();

        $systemPrompt = <<<'PROMPT'
You are a Laravel architecture expert. Based on the schema and installed packages,
provide actionable recommendations for improvements. Consider scalability, maintainability, and best practices.
PROMPT;

        $userPrompt = "Provide recommendations for this schema:\n\n";
        $userPrompt .= json_encode($draft->models, JSON_PRETTY_PRINT)."\n\n";
        $userPrompt .= 'Installed packages: '.implode(', ', array_keys($installed))."\n\n";
        $userPrompt .= <<<'PROMPT'
Return an array of recommendations with:
- category: what area (performance, security, scalability, maintainability)
- recommendation: what to do
- priority: high, medium, low
- implementation: brief code or steps to implement
PROMPT;

        $schema = new ArraySchema(
            'recommendations',
            'Schema recommendations',
            new ObjectSchema(
                'recommendation',
                'A recommendation',
                [
                    new StringSchema('category', 'Category'),
                    new StringSchema('recommendation', 'What to do'),
                    new StringSchema('priority', 'Priority level'),
                    new StringSchema('implementation', 'How to implement'),
                ],
                ['category', 'recommendation', 'priority']
            )
        );

        $result = $this->generateStructured($systemPrompt, $userPrompt, new ObjectSchema(
            'result',
            'Recommendations result',
            [$schema],
            ['recommendations']
        ));

        return $result['recommendations'] ?? null;
    }

    private function getValidationSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a Laravel schema validation expert. Analyze database schemas for:

1. **Issues** (severity: error, warning, info)
   - Missing required fields
   - Invalid type definitions
   - Broken relationships
   - Convention violations

2. **Best Practices**
   - Soft deletes where appropriate
   - Timestamps presence
   - UUID usage for public IDs
   - Proper indexing

3. **Performance Warnings**
   - N+1 query risks
   - Missing indexes on foreign keys
   - Large text fields without optimization
   - Inefficient relationship patterns

4. **Security Concerns**
   - Mass assignment vulnerabilities
   - Sensitive data exposure
   - Missing validation hints
   - Authorization gaps

Rate issues by severity and provide actionable suggestions.
PROMPT;
    }

    /**
     * @param  array<string, string>  $installed
     */
    private function buildValidationPrompt(Draft $draft, array $installed): string
    {
        $prompt = "Validate this Laravel schema:\n\n";
        $prompt .= "Models:\n".json_encode($draft->models, JSON_PRETTY_PRINT)."\n\n";

        if ($draft->actions !== []) {
            $prompt .= "Actions:\n".json_encode(array_keys($draft->actions), JSON_PRETTY_PRINT)."\n\n";
        }

        $prompt .= 'Installed packages: '.implode(', ', array_keys($installed))."\n\n";
        $prompt .= 'Provide comprehensive validation results.';

        return $prompt;
    }

    private function createValidationSchema(): ObjectSchema
    {
        $issue = new ObjectSchema(
            'issue',
            'A validation issue',
            [
                new StringSchema('severity', 'error, warning, or info'),
                new StringSchema('category', 'Category of issue'),
                new StringSchema('model', 'Affected model'),
                new StringSchema('field', 'Affected field'),
                new StringSchema('message', 'Issue description'),
                new StringSchema('suggestion', 'How to fix'),
            ],
            ['severity', 'category', 'message', 'suggestion']
        );

        $bestPractice = new ObjectSchema(
            'best_practice',
            'Best practice check',
            [
                new StringSchema('category', 'Category'),
                new StringSchema('status', 'pass, fail, or warning'),
                new StringSchema('message', 'Status message'),
            ],
            ['category', 'status', 'message']
        );

        $performanceWarning = new ObjectSchema(
            'performance_warning',
            'Performance issue',
            [
                new StringSchema('model', 'Affected model'),
                new StringSchema('issue', 'Issue description'),
                new StringSchema('impact', 'Performance impact'),
                new StringSchema('fix', 'How to fix'),
            ],
            ['model', 'issue', 'impact', 'fix']
        );

        $securityConcern = new ObjectSchema(
            'security_concern',
            'Security issue',
            [
                new StringSchema('model', 'Affected model'),
                new StringSchema('field', 'Affected field'),
                new StringSchema('issue', 'Issue description'),
                new StringSchema('risk', 'Risk level'),
                new StringSchema('mitigation', 'How to mitigate'),
            ],
            ['model', 'issue', 'risk', 'mitigation']
        );

        return new ObjectSchema(
            'validation_result',
            'Schema validation results',
            [
                new ArraySchema('issues', 'Validation issues', $issue),
                new ArraySchema('best_practices', 'Best practice checks', $bestPractice),
                new ArraySchema('performance_warnings', 'Performance warnings', $performanceWarning),
                new ArraySchema('security_concerns', 'Security concerns', $securityConcern),
            ],
            ['issues', 'best_practices']
        );
    }

    /**
     * Calculate if schema is valid based on issues.
     *
     * @param  array<string, mixed>  $result
     */
    private function calculateValidity(array $result): bool
    {
        $issues = $result['issues'] ?? [];

        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'error') {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate schema quality score (0-100).
     *
     * @param  array<string, mixed>  $result
     */
    private function calculateScore(array $result): int
    {
        $score = 100;

        // Deduct for issues
        foreach ($result['issues'] ?? [] as $issue) {
            $severity = $issue['severity'] ?? 'info';
            $score -= match ($severity) {
                'error' => 20,
                'warning' => 10,
                'info' => 2,
                default => 0,
            };
        }

        // Deduct for performance warnings
        $score -= count($result['performance_warnings'] ?? []) * 5;

        // Deduct for security concerns
        $score -= count($result['security_concerns'] ?? []) * 10;

        // Bonus for passing best practices
        foreach ($result['best_practices'] ?? [] as $practice) {
            if (($practice['status'] ?? '') === 'pass') {
                $score += 2;
            }
        }

        return max(0, min(100, $score));
    }
}
