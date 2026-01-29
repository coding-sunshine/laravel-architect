<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\HashComputer;

final class ChangeDetector
{
    public function __construct(
        private readonly StateManager $state
    ) {}

    /**
     * Detect if draft has changed since last build.
     */
    public function hasDraftChanged(string $draftPath, string $draftHash): bool
    {
        $previousHash = $this->state->getDraftHash($draftPath);

        return $previousHash === null || $previousHash !== $draftHash;
    }

    /**
     * Compute hash of draft file contents.
     */
    public static function computeDraftHash(string $path): string
    {
        $content = file_exists($path) ? (string) file_get_contents($path) : '';

        return HashComputer::compute($content);
    }
}
