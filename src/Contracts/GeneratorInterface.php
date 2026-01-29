<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Contracts;

use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;

interface GeneratorInterface
{
    /**
     * Generate code from the draft.
     */
    public function generate(Draft $draft, string $draftPath): BuildResult;

    /**
     * Whether this generator supports the given draft.
     */
    public function supports(Draft $draft): bool;
}
