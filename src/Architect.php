<?php

declare(strict_types=1);

namespace CodingSunshine\Architect;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Services\BuildOrchestrator;
use CodingSunshine\Architect\Services\DraftGenerator;
use CodingSunshine\Architect\Services\DraftParser;
use CodingSunshine\Architect\Services\StateManager;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;

final class Architect
{
    /**
     * @var array<string, class-string<GeneratorInterface>>
     */
    private static array $customGenerators = [];

    /**
     * @var array<string, string>
     */
    private static array $customStubs = [];

    /**
     * @var array<string, array<mixed>>
     */
    private static array $validationRules = [];

    /**
     * Register a custom generator.
     *
     * @param  class-string<GeneratorInterface>  $class
     */
    public static function registerGenerator(string $name, string $class): void
    {
        self::$customGenerators[$name] = $class;
    }

    /**
     * Get custom generators.
     *
     * @return array<string, class-string<GeneratorInterface>>
     */
    public static function getCustomGenerators(): array
    {
        return self::$customGenerators;
    }

    /**
     * Register a custom stub path.
     */
    public static function stub(string $name, string $path): void
    {
        self::$customStubs[$name] = $path;
    }

    /**
     * Get a stub path.
     */
    public static function getStub(string $name): ?string
    {
        return self::$customStubs[$name] ?? null;
    }

    /**
     * Register a validation rule mapping.
     *
     * @param  array<mixed>  $rules
     */
    public static function validationRule(string $columnType, array $rules): void
    {
        self::$validationRules[$columnType] = $rules;
    }

    /**
     * Get validation rules for a column type.
     *
     * @return array<mixed>|null
     */
    public static function getValidationRules(string $columnType): ?array
    {
        return self::$validationRules[$columnType] ?? null;
    }

    /**
     * Parse a draft file.
     */
    public static function parse(string $path): Draft
    {
        return app(DraftParser::class)->parse($path);
    }

    /**
     * Generate a draft from natural language.
     */
    public static function draft(string $description, ?string $existingDraftPath = null): string
    {
        return app(DraftGenerator::class)->generate($description, $existingDraftPath);
    }

    /**
     * Build from a draft.
     *
     * @param  array<string>|null  $only
     */
    public static function build(string $draftPath, ?array $only = null, bool $force = false): BuildResult
    {
        return app(BuildOrchestrator::class)->build($draftPath, $only, $force);
    }

    /**
     * Get the current state.
     *
     * @return array<string, mixed>
     */
    public static function state(): array
    {
        return app(StateManager::class)->load();
    }
}
