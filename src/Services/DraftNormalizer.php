<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Str;

/**
 * Normalizes draft models: expand column shorthands (Blueprint-style) and add FK columns for belongsTo (Vemto-style).
 */
final class DraftNormalizer
{
    private const RESERVED_KEYS = ['relationships', 'seeder', 'softDeletes', 'timestamps', 'traits', 'media', 'searchable', 'sluggable', 'tags', 'activity_log', 'roles', 'permissions', 'api_tokens', 'oauth', 'notifiable', 'billable', 'filament', 'exportable'];

    public function __construct(
        private readonly ColumnTypeInferrer $columnTypeInferrer,
    ) {}

    /**
     * Normalize models: expand column shorthands and add belongsTo FK columns.
     *
     * @param  array<string, mixed>  $models
     * @return array<string, mixed>
     */
    public function normalizeModels(array $models): array
    {
        $out = [];
        foreach ($models as $name => $def) {
            if (! is_array($def)) {
                $out[$name] = $def;

                continue;
            }
            $normalized = $this->expandColumnShorthands($def);
            $normalized = $this->addBelongsToFkColumns($name, $normalized);
            $out[$name] = $normalized;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $modelDef
     * @return array<string, mixed>
     */
    private function expandColumnShorthands(array $modelDef): array
    {
        if (isset($modelDef['columns']) && is_array($modelDef['columns'])) {
            $expanded = [];
            foreach ($modelDef['columns'] as $col) {
                if (! is_string($col)) {
                    continue;
                }
                if (Str::lower($col) === 'id') {
                    $expanded['id'] = 'bigIncrements';

                    continue;
                }
                if (Str::lower($col) === 'timestamps') {
                    $expanded['created_at'] = 'timestamp nullable';
                    $expanded['updated_at'] = 'timestamp nullable';

                    continue;
                }
                if (Str::lower($col) === 'softdeletes') {
                    $expanded['deleted_at'] = 'timestamp nullable';

                    continue;
                }
                $expanded[$col] = $this->columnTypeInferrer->inferFromColumnName($col);
            }
            unset($modelDef['columns']);
            $modelDef = array_merge($expanded, $modelDef);
        }

        $hasNumericKeys = false;
        foreach (array_keys($modelDef) as $k) {
            if (is_int($k)) {
                $hasNumericKeys = true;
                break;
            }
        }
        if ($hasNumericKeys) {
            $expanded = [];
            foreach ($modelDef as $k => $v) {
                if (is_int($k) && is_string($v)) {
                    $col = $v;
                    if (Str::lower($col) === 'id') {
                        $expanded['id'] = 'bigIncrements';
                    } elseif (Str::lower($col) === 'timestamps') {
                        $expanded['created_at'] = 'timestamp nullable';
                        $expanded['updated_at'] = 'timestamp nullable';
                    } elseif (Str::lower($col) === 'softdeletes') {
                        $expanded['deleted_at'] = 'timestamp nullable';
                    } else {
                        $expanded[$col] = $this->columnTypeInferrer->inferFromColumnName($col);
                    }
                } else {
                    $expanded[$k] = $v;
                }
            }
            $modelDef = $expanded;
        }

        return $modelDef;
    }

    /**
     * Add FK columns for belongsTo relationships if missing.
     *
     * @param  array<string, mixed>  $modelDef
     * @return array<string, mixed>
     */
    private function addBelongsToFkColumns(string $modelName, array $modelDef): array
    {
        $rels = $modelDef['relationships'] ?? null;
        if (! is_array($rels) || ! isset($rels['belongsTo']) || ! is_string($rels['belongsTo'])) {
            return $modelDef;
        }

        $targets = array_map('trim', explode(',', $rels['belongsTo']));
        foreach ($targets as $item) {
            $parts = explode(':', $item);
            $relatedModel = trim($parts[0]);
            $method = isset($parts[1]) ? trim($parts[1]) : Str::camel($relatedModel);
            $fkColumn = Str::snake($method).'_id';
            if ($relatedModel === '' || $fkColumn === '_id') {
                continue;
            }
            if (! isset($modelDef[$fkColumn]) && ! in_array($fkColumn, self::RESERVED_KEYS, true)) {
                $modelDef[$fkColumn] = 'foreignId';
            }
        }

        return $modelDef;
    }
}
