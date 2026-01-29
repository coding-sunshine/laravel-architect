<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Schema;

final class SchemaValidator
{
    /**
     * Validate data against the draft schema.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string> List of error messages
     */
    public function validate(array $data): array
    {
        if (empty($data['models']) && empty($data['actions']) && empty($data['pages'])) {
            return ['Draft must contain at least one of: models, actions, pages.'];
        }

        return [];
    }
}
