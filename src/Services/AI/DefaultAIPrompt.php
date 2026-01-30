<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\AI;

/**
 * Central default instructions for all Architect AI calls.
 * Ensures "check existing packages first" behavior.
 */
final class DefaultAIPrompt
{
    /**
     * Return the default instruction string to prepend to system prompts.
     * Used so every AI call checks installed packages before generating code.
     */
    public static function get(): string
    {
        return (string) config(
            'architect.ai.default_instructions',
            'Before generating any code, check the application\'s installed packages and conventions (provided in context). '.
            'For the requested use case (e.g. CRUD, data table, form, auth), prefer using an existing package '.
            '(e.g. Filament, Power Grid, Inertia Tables, Breeze) if it fits. Only fall back to plain framework code '.
            'when no suitable package is installed or applicable.'
        );
    }
}
