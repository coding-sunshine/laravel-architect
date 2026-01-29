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

final class ModelGenerator implements GeneratorInterface
{
    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $basePath = app()->path('Models');

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $content = $this->renderModel($modelName, $modelDef);
            $path = "{$basePath}/{$modelName}.php";

            File::ensureDirectoryExists(dirname($path));
            File::put($path, $content);

            $generated[$path] = [
                'path' => $path,
                'hash' => HashComputer::compute($content),
                'ownership' => FileOwnership::ScaffoldOnly->value,
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
     */
    private function renderModel(string $modelName, array $modelDef): string
    {
        $fillable = $this->extractFillable($modelDef);
        $casts = $this->extractCasts($modelDef);
        $relationships = $this->extractRelationships($modelDef);
        $traits = $modelDef['traits'] ?? [];
        $usesSoftDeletes = ! empty($modelDef['softDeletes']);

        $stubPath = $this->stubPath('model.stub');
        $stub = $stubPath && File::exists($stubPath)
            ? File::get($stubPath)
            : $this->defaultModelStub();

        $traitsBlock = $this->formatTraits($traits, $usesSoftDeletes);
        if ($traitsBlock !== '') {
            $traitsBlock = $traitsBlock."\n\n    ";
        }

        return str_replace(
            [
                '{{namespace}}',
                '{{class}}',
                '{{fillable}}',
                '{{casts}}',
                '{{traits}}',
                '{{relationships}}',
            ],
            [
                'App\\Models',
                $modelName,
                $this->formatFillable($fillable),
                $this->formatCasts($casts),
                $traitsBlock,
                $this->formatRelationships($relationships),
            ],
            $stub
        );
    }

    /**
     * @param  array<string, mixed>  $modelDef
     * @return array<string>
     */
    private function extractFillable(array $modelDef): array
    {
        $fillable = [];
        foreach ($modelDef as $key => $value) {
            if (in_array($key, ['relationships', 'seeder', 'softDeletes', 'timestamps', 'traits'], true)) {
                continue;
            }
            if (is_string($value) && str_contains($value, 'id') && preg_match('/^[\w_]+_id$/', $key)) {
                $fillable[] = $key;
            } elseif (is_string($value)) {
                $fillable[] = $key;
            }
        }

        return $fillable;
    }

    /**
     * @param  array<string, mixed>  $modelDef
     * @return array<string, string>
     */
    private function extractCasts(array $modelDef): array
    {
        $casts = [];
        foreach ($modelDef as $key => $value) {
            if (! is_string($value)) {
                continue;
            }
            if (str_contains($value, 'timestamp') || str_contains($value, 'date')) {
                $casts[$key] = 'datetime';
            }
            if (str_contains($value, 'boolean')) {
                $casts[$key] = 'boolean';
            }
            if (str_contains($value, 'json')) {
                $casts[$key] = 'array';
            }
        }

        return $casts;
    }

    /**
     * @param  array<string, mixed>  $modelDef
     * @return array<string, array<string, string>>
     */
    private function extractRelationships(array $modelDef): array
    {
        $rels = $modelDef['relationships'] ?? [];
        if (! is_array($rels)) {
            return [];
        }

        $out = [];
        foreach ($rels as $type => $target) {
            if (is_string($target)) {
                foreach (array_map('trim', explode(',', $target)) as $item) {
                    $parts = explode(':', $item);
                    $model = trim($parts[0]);
                    $method = $parts[1] ?? Str::camel($model);
                    $out[] = ['type' => $type, 'model' => $model, 'method' => $method];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string>  $fillable
     */
    private function formatFillable(array $fillable): string
    {
        if ($fillable === []) {
            return '[]';
        }

        return "[\n            '".implode("',\n            '", $fillable)."',\n        ]";
    }

    /**
     * @param  array<string, string>  $casts
     */
    private function formatCasts(array $casts): string
    {
        if ($casts === []) {
            return 'return [];';
        }

        $lines = [];
        foreach ($casts as $key => $value) {
            $lines[] = "            '{$key}' => '{$value}',";
        }

        return "return [\n".implode("\n", $lines)."\n        ];";
    }

    /**
     * @param  array<string>  $traits
     */
    private function formatTraits(array $traits, bool $softDeletes): string
    {
        $all = $traits;
        if ($softDeletes) {
            $all[] = 'Illuminate\\Database\\Eloquent\\SoftDeletes';
        }

        if ($all === []) {
            return '';
        }

        return 'use '.implode(";\n    use ", $all).';';
    }

    /**
     * @param  array<int, array<string, string>>  $relationships
     */
    private function formatRelationships(array $relationships): string
    {
        if ($relationships === []) {
            return '';
        }

        $lines = [];
        foreach ($relationships as $rel) {
            $type = $rel['type'];
            $model = $rel['model'];
            $method = $rel['method'];
            $returnType = match ($type) {
                'belongsTo' => 'BelongsTo',
                'hasMany' => 'HasMany',
                'hasOne' => 'HasOne',
                'belongsToMany' => 'BelongsToMany',
                default => 'Relation',
            };

            $lines[] = <<<PHP
    public function {$method}(): \\Illuminate\\Database\\Eloquent\\Relations\\{$returnType}
    {
        return \$this->{$type}(\\App\\Models\\{$model}::class);
    }
PHP;
        }

        return implode("\n\n", $lines);
    }

    private function stubPath(string $name): ?string
    {
        $custom = \CodingSunshine\Architect\Architect::getStub($name);
        if ($custom !== null) {
            return $custom;
        }

        $pkg = dirname(__DIR__, 2).'/stubs/'.$name;

        return file_exists($pkg) ? $pkg : null;
    }

    private function defaultModelStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace {{namespace}};

use Illuminate\Database\Eloquent\Model;

final class {{class}} extends Model
{
    {{traits}}

    protected $fillable = {{fillable}};

    protected function casts(): array
    {
        {{casts}}
    }
{{relationships}}
}
STUB;
    }
}
