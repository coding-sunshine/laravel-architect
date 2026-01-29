<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \CodingSunshine\Architect\Support\Draft parse(string $path)
 * @method static string draft(string $description, ?string $existingDraftPath = null)
 * @method static \CodingSunshine\Architect\Support\BuildResult build(string $draftPath, ?array $only = null, bool $force = false)
 * @method static array state()
 * @method static void registerGenerator(string $name, string $class)
 * @method static void stub(string $name, string $path)
 * @method static void validationRule(string $columnType, array $rules)
 *
 * @see \CodingSunshine\Architect\Architect
 */
final class Architect extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CodingSunshine\Architect\Architect::class;
    }
}
