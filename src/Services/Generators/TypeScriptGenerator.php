<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\Generators;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\FileOwnership;
use CodingSunshine\Architect\Support\HashComputer;
use Illuminate\Support\Facades\File;

final class TypeScriptGenerator implements GeneratorInterface
{
    private const SKIP_KEYS = ['relationships', 'seeder', 'softDeletes', 'timestamps', 'traits'];

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $typesPath = resource_path('js/types/architect.d.ts');

        $interfaces = [];
        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }
            $interfaces[] = $this->renderInterface($modelName, $modelDef);
        }

        if ($interfaces === []) {
            return new BuildResult;
        }

        $content = "/** Architect-generated TypeScript interfaces from draft.\n * Do not edit by hand; regenerate with architect:build.\n */\n\n".implode("\n\n", $interfaces)."\n";
        File::ensureDirectoryExists(dirname($typesPath));
        File::put($typesPath, $content);

        $generated[$typesPath] = [
            'path' => $typesPath,
            'hash' => HashComputer::compute($content),
            'ownership' => FileOwnership::Regenerate->value,
        ];

        return new BuildResult(generated: $generated);
    }

    public function supports(Draft $draft): bool
    {
        return $draft->modelNames() !== [];
    }

    /**
     * @param  array<string, mixed>  $modelDef
     */
    private function renderInterface(string $modelName, array $modelDef): string
    {
        $lines = ['id: number;'];
        foreach ($modelDef as $columnName => $definition) {
            if (in_array($columnName, self::SKIP_KEYS, true) || $columnName === 'id') {
                continue;
            }
            if (! is_string($definition)) {
                continue;
            }
            $ts = $this->columnToTypeScript($columnName, $definition);
            if ($ts !== '') {
                $lines[] = "    {$ts}";
            }
        }
        $usesTimestamps = ($modelDef['timestamps'] ?? true) !== false;
        if ($usesTimestamps) {
            if (! in_array('created_at', array_keys($modelDef), true)) {
                $lines[] = '    created_at: string;';
            }
            if (! in_array('updated_at', array_keys($modelDef), true)) {
                $lines[] = '    updated_at: string;';
            }
        }
        $lines[] = '    [key: string]: unknown;';

        $body = implode("\n", $lines);

        return "export interface {$modelName} {\n{$body}\n}";
    }

    private function columnToTypeScript(string $columnName, string $definition): string
    {
        $parts = preg_split('/\s+/', trim($definition), 2);
        $typePart = $parts[0] ?? '';
        $modifiers = $parts[1] ?? '';
        $nullable = str_contains($modifiers, 'nullable');

        if (preg_match('/^id:(.+)$/i', $typePart, $m)) {
            return "{$columnName}: number;";
        }
        [$type] = str_contains($typePart, ':') ? explode(':', $typePart, 2) : [$typePart];
        $type = strtolower($type);

        $ts = match ($type) {
            'string', 'text', 'longtext', 'uuid' => 'string',
            'integer', 'bigInteger' => 'number',
            'decimal' => 'number',
            'boolean' => 'boolean',
            'date', 'datetime', 'timestamp' => 'string',
            'json' => 'Record<string, unknown>',
            default => 'string',
        };
        $optional = $nullable ? '?' : '';

        return "{$columnName}{$optional}: {$ts};";
    }
}
