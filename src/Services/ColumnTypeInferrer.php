<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Str;

/**
 * Infers Laravel migration / draft column type from column name (Vemto-style schema intelligence).
 * Used when importing from DB or expanding shorthands so the draft better reflects conventions.
 */
final class ColumnTypeInferrer
{
    /**
     * Infer type from column name. Returns a draft-style type (string, timestamp, boolean, etc.).
     */
    public function inferFromColumnName(string $columnName): string
    {
        $name = Str::lower($columnName);

        if ($name === 'id') {
            return 'bigIncrements';
        }
        if (in_array($name, ['created_at', 'updated_at', 'deleted_at', 'published_at'], true)) {
            return 'timestamp nullable';
        }
        if (Str::endsWith($name, '_at')) {
            return 'timestamp nullable';
        }
        if (Str::startsWith($name, 'is_') || Str::startsWith($name, 'has_')) {
            return 'boolean';
        }
        if (in_array($name, ['email', 'name', 'title', 'password'], true)) {
            return 'string:255';
        }
        if ($name === 'slug') {
            return 'string:255 unique';
        }
        if (in_array($name, ['body', 'content', 'description', 'bio'], true)) {
            return 'longtext';
        }
        if (in_array($name, ['price', 'amount', 'total', 'quantity'], true) || Str::contains($name, 'price') || Str::contains($name, 'amount')) {
            return 'decimal:10,2';
        }
        if (Str::endsWith($name, '_id')) {
            return 'foreignId';
        }
        if (Str::contains($name, 'json') || $name === 'metadata' || $name === 'options') {
            return 'json';
        }
        if (Str::contains($name, 'uuid')) {
            return 'uuid';
        }
        if (in_array($name, ['date', 'birthday'], true) || Str::endsWith($name, '_date')) {
            return 'date';
        }

        return 'string:255';
    }
}
