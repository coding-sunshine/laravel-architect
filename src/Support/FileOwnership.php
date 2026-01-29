<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Support;

enum FileOwnership: string
{
    case Regenerate = 'regenerate';

    case ScaffoldOnly = 'scaffold_only';
}
