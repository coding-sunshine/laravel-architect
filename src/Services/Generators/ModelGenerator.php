<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\Generators;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Services\PackageSuggestionService;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\FileOwnership;
use CodingSunshine\Architect\Support\HashComputer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class ModelGenerator implements GeneratorInterface
{
    /**
     * Schema keys that map to traits.
     *
     * @var array<string, array{trait: string, interface?: string}>
     */
    private const SCHEMA_TRAIT_MAP = [
        'media' => [
            'trait' => 'Spatie\\MediaLibrary\\InteractsWithMedia',
            'interface' => 'Spatie\\MediaLibrary\\HasMedia',
        ],
        'searchable' => [
            'trait' => 'Laravel\\Scout\\Searchable',
        ],
        'billable' => [
            'trait' => 'Laravel\\Cashier\\Billable',
        ],
        'sluggable' => [
            'trait' => 'Spatie\\Sluggable\\HasSlug',
        ],
        'tags' => [
            'trait' => 'Spatie\\Tags\\HasTags',
        ],
        'activity_log' => [
            'trait' => 'Spatie\\Activitylog\\Traits\\LogsActivity',
        ],
        'roles' => [
            'trait' => 'Spatie\\Permission\\Traits\\HasRoles',
        ],
        'permissions' => [
            'trait' => 'Spatie\\Permission\\Traits\\HasRoles',
        ],
        'api_tokens' => [
            'trait' => 'Laravel\\Sanctum\\HasApiTokens',
        ],
        'oauth' => [
            'trait' => 'Laravel\\Passport\\HasApiTokens',
        ],
        'notifiable' => [
            'trait' => 'Illuminate\\Notifications\\Notifiable',
        ],
    ];

    public function __construct(
        private readonly ?PackageSuggestionService $suggestionService = null,
    ) {}

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $backup = [];
        $basePath = app()->path('Models');

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $content = $this->renderModel($modelName, $modelDef);
            $path = "{$basePath}/{$modelName}.php";

            if (File::exists($path)) {
                $backup[$path] = File::get($path);
            }
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $content);

            $generated[$path] = [
                'path' => $path,
                'hash' => HashComputer::compute($content),
                'ownership' => FileOwnership::ScaffoldOnly->value,
            ];
        }

        return new BuildResult(generated: $generated, backup: $backup);
    }

    public function supports(Draft $draft): bool
    {
        return $draft->modelNames() !== [];
    }

    /**
     * @param  array<string, mixed>  $modelDef
     */
    public function renderModel(string $modelName, array $modelDef): string
    {
        $fillable = $this->extractFillable($modelDef);
        $casts = $this->extractCasts($modelDef);
        $relationships = $this->extractRelationships($modelDef);
        $explicitTraits = $modelDef['traits'] ?? [];
        $schemaTraits = $this->extractSchemaTraits($modelDef);
        $traits = array_unique(array_merge($explicitTraits, $schemaTraits));
        $interfaces = $this->extractInterfaces($modelDef);
        $usesSoftDeletes = ! empty($modelDef['softDeletes']);

        $stubPath = $this->stubPath('model.stub');
        $stub = $stubPath && File::exists($stubPath)
            ? File::get($stubPath)
            : $this->defaultModelStub();

        $traitsBlock = $this->formatTraits($traits, $usesSoftDeletes);
        if ($traitsBlock !== '') {
            $traitsBlock = $traitsBlock . "\n\n    ";
        }

        $extendsBlock = $this->formatExtends($interfaces);
        $implementsBlock = $this->formatImplements($interfaces);
        $additionalMethods = $this->formatAdditionalMethods($modelDef, $modelName);

        return str_replace(
            [
                '{{namespace}}',
                '{{class}}',
                '{{extends}}',
                '{{implements}}',
                '{{fillable}}',
                '{{casts}}',
                '{{traits}}',
                '{{relationships}}',
                '{{additional_methods}}',
            ],
            [
                'App\\Models',
                $modelName,
                $extendsBlock,
                $implementsBlock,
                $this->formatFillable($fillable),
                $this->formatCasts($casts),
                $traitsBlock,
                $this->formatRelationships($relationships),
                $additionalMethods,
            ],
            $stub
        );
    }

    /**
     * Extract traits from schema keys.
     *
     * @param  array<string, mixed>  $modelDef
     * @return array<string>
     */
    private function extractSchemaTraits(array $modelDef): array
    {
        $traits = [];

        foreach (self::SCHEMA_TRAIT_MAP as $schemaKey => $config) {
            if (isset($modelDef[$schemaKey]) && $modelDef[$schemaKey]) {
                $traits[] = $config['trait'];
            }
        }

        return array_unique($traits);
    }

    /**
     * Extract interfaces from schema keys.
     *
     * @param  array<string, mixed>  $modelDef
     * @return array<string>
     */
    private function extractInterfaces(array $modelDef): array
    {
        $interfaces = [];

        foreach (self::SCHEMA_TRAIT_MAP as $schemaKey => $config) {
            if (isset($modelDef[$schemaKey]) && $modelDef[$schemaKey] && isset($config['interface'])) {
                $interfaces[] = $config['interface'];
            }
        }

        return array_unique($interfaces);
    }

    /**
     * Format extends clause.
     *
     * @param  array<string>  $interfaces
     */
    private function formatExtends(array $interfaces): string
    {
        return 'Model';
    }

    /**
     * Format implements clause.
     *
     * @param  array<string>  $interfaces
     */
    private function formatImplements(array $interfaces): string
    {
        if ($interfaces === []) {
            return '';
        }

        $interfaceNames = array_map(fn (string $fqcn) => '\\' . $fqcn, $interfaces);

        return ' implements ' . implode(', ', $interfaceNames);
    }

    /**
     * Format additional methods based on schema keys.
     *
     * @param  array<string, mixed>  $modelDef
     */
    private function formatAdditionalMethods(array $modelDef, string $modelName): string
    {
        $methods = [];

        // Add registerMediaConversions for media
        if (! empty($modelDef['media'])) {
            $methods[] = $this->renderMediaConversionsMethod();
        }

        // Add getSlugOptions for sluggable
        if (! empty($modelDef['sluggable'])) {
            $sourceField = $modelDef['sluggable_source'] ?? 'name';
            $methods[] = $this->renderSlugOptionsMethod($sourceField);
        }

        // Add getActivitylogOptions for activity_log
        if (! empty($modelDef['activity_log'])) {
            $methods[] = $this->renderActivityLogOptionsMethod();
        }

        // Add toSearchableArray for searchable
        if (! empty($modelDef['searchable'])) {
            $methods[] = $this->renderSearchableArrayMethod($modelDef);
        }

        if ($methods === []) {
            return '';
        }

        return "\n\n" . implode("\n\n", $methods);
    }

    private function renderMediaConversionsMethod(): string
    {
        return <<<'PHP'
    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10);

        $this->addMediaConversion('preview')
            ->width(400)
            ->height(400);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');
    }
PHP;
    }

    private function renderSlugOptionsMethod(string $sourceField): string
    {
        return <<<PHP
    public function getSlugOptions(): \\Spatie\\Sluggable\\SlugOptions
    {
        return \\Spatie\\Sluggable\\SlugOptions::create()
            ->generateSlugsFrom('{$sourceField}')
            ->saveSlugsTo('slug');
    }
PHP;
    }

    private function renderActivityLogOptionsMethod(): string
    {
        return <<<'PHP'
    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }
PHP;
    }

    /**
     * @param  array<string, mixed>  $modelDef
     */
    private function renderSearchableArrayMethod(array $modelDef): string
    {
        $searchableFields = [];
        foreach ($modelDef as $key => $value) {
            if (is_string($value) && ! in_array($key, ['relationships', 'seeder', 'softDeletes', 'timestamps', 'traits', 'searchable', 'media', 'sluggable'], true)) {
                $searchableFields[] = "'{$key}' => \$this->{$key}";
            }
        }

        $fieldsStr = implode(",\n            ", $searchableFields);

        return <<<PHP
    public function toSearchableArray(): array
    {
        return [
            'id' => \$this->id,
            {$fieldsStr},
        ];
    }
PHP;
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
     * @return list<array<string, string>>
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

        return "[\n            '" . implode("',\n            '", $fillable) . "',\n        ]";
    }

    /**
     * @param  array<string, string>  $casts
     */
    private function formatCasts(array $casts): string
    {
        if ($casts === []) {
            return "return [];";
        }

        $lines = [];
        foreach ($casts as $key => $value) {
            $lines[] = "            '{$key}' => '{$value}',";
        }

        return "return [\n" . implode("\n", $lines) . "\n        ];";
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

        return 'use ' . implode(";\n    use ", $all) . ';';
    }

    /**
     * @param  list<array<string, string>>  $relationships
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

        $pkg = dirname(__DIR__, 2) . '/stubs/' . $name;

        return file_exists($pkg) ? $pkg : null;
    }

    private function defaultModelStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace {{namespace}};

use Illuminate\Database\Eloquent\Model;

final class {{class}} extends {{extends}}{{implements}}
{
    {{traits}}

    protected $fillable = {{fillable}};

    protected function casts(): array
    {
        {{casts}}
    }
{{relationships}}{{additional_methods}}
}
STUB;
    }
}
