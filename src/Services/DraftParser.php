<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use CodingSunshine\Architect\Schema\SchemaValidator;
use CodingSunshine\Architect\Support\Draft;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class DraftParser
{
    public function __construct(
        private readonly SchemaValidator $validator
    ) {}

    public function parse(string $path): Draft
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("Draft file not found: {$path}");
        }

        $content = (string) file_get_contents($path);

        return $this->parseContent($content);
    }

    /**
     * Parse draft from YAML content string (for API validation from body).
     *
     * @throws \InvalidArgumentException
     */
    public function parseContent(string $content): Draft
    {
        try {
            $data = Yaml::parse($content);
        } catch (ParseException $e) {
            throw new \InvalidArgumentException('Invalid YAML: ' . $e->getMessage());
        }

        if (! is_array($data)) {
            throw new \InvalidArgumentException('Draft must contain a YAML object.');
        }

        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            throw new \InvalidArgumentException("Draft validation failed:\n" . implode("\n", $errors));
        }

        return $this->hydrate($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrate(array $data): Draft
    {
        $models = $data['models'] ?? [];
        $actions = $data['actions'] ?? [];
        $pages = $data['pages'] ?? [];
        $routes = $data['routes'] ?? [];
        $schemaVersion = $data['schema_version'] ?? '1.0';

        if (! is_array($models)) {
            $models = [];
        }
        if (! is_array($actions)) {
            $actions = [];
        }
        if (! is_array($pages)) {
            $pages = [];
        }
        if (! is_array($routes)) {
            $routes = [];
        }

        return new Draft(
            models: $models,
            actions: $actions,
            pages: $pages,
            routes: $routes,
            schemaVersion: (string) $schemaVersion,
        );
    }
}
