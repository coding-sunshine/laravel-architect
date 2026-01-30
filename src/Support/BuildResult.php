<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Support;

final class BuildResult
{
    /**
     * @param  array<string, array{path: string, hash: string, ownership: string}>  $generated
     * @param  array<string>  $skipped
     * @param  array<string>  $warnings
     * @param  array<string>  $errors
     * @param  array<string, string>  $backup  path => content (for revert)
     */
    public function __construct(
        public readonly array $generated = [],
        public readonly array $skipped = [],
        public readonly array $warnings = [],
        public readonly array $errors = [],
        public readonly bool $success = true,
        public readonly array $backup = [],
    ) {}

    public static function noChanges(): self
    {
        return new self(success: true);
    }

    public static function failure(array $errors): self
    {
        return new self(errors: $errors, success: false);
    }
}
