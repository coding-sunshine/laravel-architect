<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Facades\Schema;

final class SchemaDiscovery
{
    /**
     * Get tables and their columns from the database schema.
     * Returns empty array when DB is unavailable (e.g. no .env, connection failed).
     *
     * @return array<string, array{columns: array<string>}>
     */
    public function discover(): array
    {
        try {
            $tables = Schema::getTableListing();

            if (! is_array($tables) || $tables === []) {
                return [];
            }

            $result = [];

            foreach ($tables as $table) {
                $tableName = is_string($table) ? $table : (is_array($table) ? ($table['name'] ?? $table['table'] ?? null) : null);

                if ($tableName === null || $tableName === '') {
                    continue;
                }

                $columns = Schema::getColumnListing($tableName);

                $result[$tableName] = [
                    'columns' => is_array($columns) ? $columns : [],
                ];
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get column listing for a single table. Returns empty array when table missing or DB unavailable.
     *
     * @return array<string>
     */
    public function getColumnListing(string $table): array
    {
        try {
            if (! Schema::hasTable($table)) {
                return [];
            }

            $columns = Schema::getColumnListing($table);

            return is_array($columns) ? $columns : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
