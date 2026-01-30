<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\AI;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * Base class for AI-powered services in Architect.
 *
 * Provides common functionality for interacting with LLMs through Prism.
 */
abstract class AIServiceBase
{
    /**
     * Check if AI services are available.
     */
    public function isAvailable(): bool
    {
        return config('architect.ai.enabled', true)
            && class_exists(Prism::class);
    }

    /**
     * Get the configured AI provider.
     */
    protected function getProvider(): string
    {
        return config('architect.ai.provider', 'anthropic');
    }

    /**
     * Get the configured AI model.
     */
    protected function getModel(): string
    {
        $provider = $this->getProvider();

        return config('architect.ai.model') ?? match ($provider) {
            'anthropic' => 'claude-sonnet-4-20250514',
            'openai' => 'gpt-4o',
            'openrouter' => 'anthropic/claude-sonnet-4',
            default => 'claude-sonnet-4-20250514',
        };
    }

    /**
     * Execute a text generation request with retry logic.
     *
     * @param  array<string>  $previousErrors
     */
    protected function generateText(
        string $systemPrompt,
        string $userPrompt,
        int $maxRetries = 2,
        array $previousErrors = [],
    ): ?string {
        if (! $this->isAvailable()) {
            return null;
        }

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $prompt = $userPrompt;
                if ($attempt > 0 && $previousErrors !== []) {
                    $prompt .= "\n\nPrevious attempt failed. Please fix these issues:\n";
                    foreach ($previousErrors as $error) {
                        $prompt .= "- {$error}\n";
                    }
                }

                $response = Prism::text()
                    ->using($this->getProvider(), $this->getModel())
                    ->withSystemPrompt($systemPrompt)
                    ->withPrompt($prompt)
                    ->asText();

                return $response->text ?? null;
            } catch (\Throwable $e) {
                $previousErrors[] = $e->getMessage();
            }
        }

        return null;
    }

    /**
     * Execute a structured output request.
     *
     * @return array<string, mixed>|null
     */
    protected function generateStructured(
        string $systemPrompt,
        string $userPrompt,
        ObjectSchema $schema,
    ): ?array {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $response = Prism::structured()
                ->using($this->getProvider(), $this->getModel())
                ->withSystemPrompt($systemPrompt)
                ->withSchema($schema)
                ->withPrompt($userPrompt)
                ->asStructured();

            return $response->structured ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Create an ObjectSchema for structured output.
     *
     * @param  array<\Prism\Prism\Schema\Schema>  $properties
     * @param  array<string>  $requiredFields
     */
    protected function createSchema(
        string $name,
        string $description,
        array $properties,
        array $requiredFields = [],
    ): ObjectSchema {
        return new ObjectSchema(
            name: $name,
            description: $description,
            properties: $properties,
            requiredFields: $requiredFields,
        );
    }

    /**
     * Create a StringSchema property.
     */
    protected function stringProperty(string $name, string $description): StringSchema
    {
        return new StringSchema($name, $description);
    }

    /**
     * Create an ArraySchema property.
     */
    protected function arrayProperty(string $name, string $description, mixed $items = null): ArraySchema
    {
        return new ArraySchema($name, $description, $items);
    }

    /**
     * Create a BooleanSchema property.
     */
    protected function booleanProperty(string $name, string $description): BooleanSchema
    {
        return new BooleanSchema($name, $description);
    }

    /**
     * Extract JSON from text that may contain markdown fences.
     *
     * @return array<string, mixed>|null
     */
    protected function extractJson(string $text): ?array
    {
        // Try to extract from markdown code block
        if (preg_match('/```(?:json)?\s*\n(.*?)```/s', $text, $matches)) {
            $text = $matches[1];
        }

        try {
            $decoded = json_decode(trim($text), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }
}
