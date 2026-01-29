<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\Generators;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\FileOwnership;
use CodingSunshine\Architect\Support\HashComputer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class FactoryGenerator implements GeneratorInterface
{
    private const SKIP_KEYS = ['relationships', 'seeder', 'softDeletes', 'timestamps', 'traits'];

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $basePath = database_path('factories');

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $definitionLines = $this->buildDefinitionLines($modelName, $modelDef);
            $content = $this->renderFactory($modelName, $definitionLines);
            $path = "{$basePath}/{$modelName}Factory.php";

            File::ensureDirectoryExists($basePath);
            File::put($path, $content);

            $generated[$path] = [
                'path' => $path,
                'hash' => HashComputer::compute($content),
                'ownership' => FileOwnership::Regenerate->value,
            ];
        }

        return new BuildResult(generated: $generated);
    }

    public function supports(Draft $draft): bool
    {
        return $draft->modelNames() !== [];
    }

    /**
     * @param  array<string, mixed>  $modelDef
     * @return array<string>
     */
    private function buildDefinitionLines(string $modelName, array $modelDef): array
    {
        $lines = [];

        foreach ($modelDef as $columnName => $definition) {
            if (in_array($columnName, self::SKIP_KEYS, true)) {
                continue;
            }
            if (! is_string($definition)) {
                continue;
            }

            $php = $this->columnDefinitionToFactory($modelName, $columnName, $definition);
            if ($php !== '') {
                $lines[$columnName] = $php;
            }
        }

        $this->ensureStandardColumns($modelName, $modelDef, $lines);

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $modelDef
     * @param  array<string, string>  $lines
     */
    private function ensureStandardColumns(string $modelName, array $modelDef, array &$lines): void
    {
        if (! array_key_exists('password', $lines) && $modelName === 'User') {
            $lines['password'] = "self::\$password ??= Hash::make('password'),";
        }
        if (! array_key_exists('remember_token', $lines)) {
            $lines['remember_token'] = "Str::random(10),";
        }
        $usesTimestamps = ($modelDef['timestamps'] ?? true) !== false;
        if ($usesTimestamps && ! array_key_exists('created_at', $lines)) {
            $lines['created_at'] = 'now(),';
            $lines['updated_at'] = 'now(),';
        }
    }

    private function columnDefinitionToFactory(string $modelName, string $columnName, string $definition): string
    {
        $parts = preg_split('/\s+/', trim($definition), 2);
        $typePart = $parts[0] ?? '';
        $modifiers = $parts[1] ?? '';

        $unique = str_contains($modifiers, 'unique');

        if (preg_match('/^id:(.+)$/i', $typePart, $m)) {
            return ''; // Foreign keys: factory will set relation or ID later
        }

        if (str_contains($typePart, ':')) {
            [$type] = explode(':', $typePart, 2);
        } else {
            $type = $typePart;
        }
        $type = strtolower($type);

        $base = match ($type) {
            'string' => $this->stringFactory($columnName, $unique),
            'text', 'longtext' => "'{$columnName}' => fake()->paragraph(),",
            'integer', 'biginteger' => "'{$columnName}' => fake()->randomNumber(),",
            'decimal' => "'{$columnName}' => fake()->randomFloat(2, 0, 9999),",
            'boolean' => "'{$columnName}' => fake()->boolean(),",
            'date' => "'{$columnName}' => fake()->date(),",
            'datetime', 'timestamp' => "'{$columnName}' => now(),",
            'json' => "'{$columnName}' => [],",
            'uuid' => "'{$columnName}' => (string) Str::uuid(),",
            default => "'{$columnName}' => fake()->word(),",
        };

        if ($base === '') {
            return '';
        }

        return $base;
    }

    private function stringFactory(string $columnName, bool $unique): string
    {
        if (str_contains($columnName, 'email')) {
            return $unique
                ? "'{$columnName}' => fake()->unique()->safeEmail(),"
                : "'{$columnName}' => fake()->safeEmail(),";
        }
        if (str_contains($columnName, 'name')) {
            return "'{$columnName}' => fake()->name(),";
        }
        if (str_contains($columnName, 'title')) {
            return "'{$columnName}' => fake()->sentence(3),";
        }
        if (str_contains($columnName, 'slug')) {
            return "'{$columnName}' => Str::slug(fake()->slug()),";
        }
        if (str_contains($columnName, 'password')) {
            return "self::\$password ??= Hash::make('password'),";
        }
        if (str_contains($columnName, 'token') || str_contains($columnName, 'secret') || str_contains($columnName, 'code')) {
            return "'{$columnName}' => Str::random(10),";
        }
        if (str_contains($columnName, 'verified_at') || str_contains($columnName, '_at')) {
            return "'{$columnName}' => now(),";
        }

        return $unique
            ? "'{$columnName}' => fake()->unique()->word(),"
            : "'{$columnName}' => fake()->word(),";
    }

    /**
     * @param  array<string, string>  $definitionLines
     */
    private function renderFactory(string $modelName, array $definitionLines): string
    {
        $indented = [];
        foreach ($definitionLines as $line) {
            $indented[] = '            ' . $line;
        }
        $body = implode("\n", $indented);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\\{$modelName};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<{$modelName}>
 */
final class {$modelName}Factory extends Factory
{
    protected \$model = {$modelName}::class;

    private static ?string \$password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
{$body}
        ];
    }
}

PHP;
    }
}
