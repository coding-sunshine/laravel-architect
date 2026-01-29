<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Support;

final class Draft
{
    /**
     * @param  array<string, array<string, mixed>>  $models
     * @param  array<string, array<string, array<string, mixed>>>  $actions
     * @param  array<string, array<string, array<string, mixed>>>  $pages
     * @param  array<string, mixed>  $routes
     */
    public function __construct(
        public readonly array $models = [],
        public readonly array $actions = [],
        public readonly array $pages = [],
        public readonly array $routes = [],
        public readonly string $schemaVersion = '1.0',
    ) {}

    /**
     * @return array<string>
     */
    public function modelNames(): array
    {
        return array_keys($this->models);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getModel(string $name): ?array
    {
        return $this->models[$name] ?? null;
    }
}
