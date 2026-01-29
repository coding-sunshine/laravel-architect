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

final class RequestGenerator implements GeneratorInterface
{
    private const SKIP_KEYS = ['relationships', 'seeder', 'softDeletes', 'timestamps', 'traits'];

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $basePath = app_path('Http/Requests');

        $actions = $draft->actions;
        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            if (isset($actions['Create'.$modelName])) {
                $path = $this->writeRequest($basePath, 'Store'.$modelName.'Request', $modelName, $modelDef, 'store');
                if ($path !== null) {
                    $content = (string) file_get_contents($path);
                    $generated[$path] = [
                        'path' => $path,
                        'hash' => HashComputer::compute($content),
                        'ownership' => FileOwnership::Regenerate->value,
                    ];
                }
            }
            if (isset($actions['Update'.$modelName])) {
                $path = $this->writeRequest($basePath, 'Update'.$modelName.'Request', $modelName, $modelDef, 'update');
                if ($path !== null) {
                    $content = (string) file_get_contents($path);
                    $generated[$path] = [
                        'path' => $path,
                        'hash' => HashComputer::compute($content),
                        'ownership' => FileOwnership::Regenerate->value,
                    ];
                }
            }
            if (isset($actions['Delete'.$modelName])) {
                $path = $this->writeRequest($basePath, 'Delete'.$modelName.'Request', $modelName, $modelDef, 'delete');
                if ($path !== null) {
                    $content = (string) file_get_contents($path);
                    $generated[$path] = [
                        'path' => $path,
                        'hash' => HashComputer::compute($content),
                        'ownership' => FileOwnership::Regenerate->value,
                    ];
                }
            }
        }

        return new BuildResult(generated: $generated);
    }

    public function supports(Draft $draft): bool
    {
        return $draft->actions !== [];
    }

    /**
     * @param  array<string, mixed>  $modelDef
     */
    private function writeRequest(string $basePath, string $className, string $modelName, array $modelDef, string $type): ?string
    {
        $rules = $this->buildRules($modelName, $modelDef, $type);
        $content = $this->renderRequest($className, $modelName, $rules, $type);
        $path = "{$basePath}/{$className}.php";
        File::ensureDirectoryExists($basePath);
        File::put($path, $content);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $modelDef
     * @return array<string, array<int, string>>
     */
    private function buildRules(string $modelName, array $modelDef, string $type): array
    {
        $rules = [];
        $modelFqcn = 'App\\Models\\'.$modelName;

        foreach ($modelDef as $columnName => $definition) {
            if (in_array($columnName, self::SKIP_KEYS, true)) {
                continue;
            }
            if (! is_string($definition)) {
                continue;
            }
            if (in_array($columnName, ['id', 'created_at', 'updated_at', 'remember_token'], true)) {
                continue;
            }
            if ($type === 'update' && in_array($columnName, ['password'], true)) {
                $rules['password'] = ['nullable', 'string', 'confirmed', 'min:8'];

                continue;
            }
            if ($type === 'delete') {
                continue;
            }

            $rule = $this->columnToRule($columnName, $definition, $modelFqcn, $type === 'update');
            if ($rule !== []) {
                $rules[$columnName] = $rule;
            }
        }

        if ($type === 'delete') {
            return [];
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    private function columnToRule(string $columnName, string $definition, string $modelFqcn, bool $update): array
    {
        $parts = preg_split('/\s+/', trim($definition), 2);
        $typePart = $parts[0] ?? '';
        $modifiers = $parts[1] ?? '';
        $nullable = str_contains($modifiers, 'nullable');
        $unique = str_contains($modifiers, 'unique');

        if (preg_match('/^id:(.+)$/i', $typePart, $m)) {
            return ['required', 'integer', 'exists:'.Str::snake(Str::plural(trim($m[1]))).',id'];
        }

        [$type] = str_contains($typePart, ':') ? explode(':', $typePart, 2) : [$typePart];
        $type = strtolower($type);

        $base = match ($type) {
            'string' => ['string', 'max:255'],
            'text', 'longtext' => ['string'],
            'integer', 'bigInteger' => ['integer'],
            'decimal' => ['numeric'],
            'boolean' => ['boolean'],
            'date' => ['date'],
            'datetime', 'timestamp' => ['date'],
            'json' => ['array'],
            'uuid' => ['uuid'],
            default => ['string'],
        };

        if (str_contains($columnName, 'email')) {
            $base = ['required', 'string', 'lowercase', 'email', 'max:255'];
            if ($unique) {
                $base[] = 'unique:'.$modelFqcn.','.$columnName.($update ? ','.'request()->route(\'id\')' : '');
            }
        }
        if (str_contains($columnName, 'password')) {
            return ['required', 'string', 'confirmed', 'min:8'];
        }
        if (! $nullable && $type !== 'boolean') {
            array_unshift($base, 'required');
        } elseif ($nullable) {
            $base[] = 'nullable';
        }

        return $base;
    }

    /**
     * @param  array<string, array<int, string>>  $rules
     */
    private function renderRequest(string $className, string $modelName, array $rules, string $type): string
    {
        if ($type === 'delete') {
            return <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class {$className} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

PHP;
        }

        $rulesPhp = $this->formatRules($rules);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class {$className} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
{$rulesPhp}
        ];
    }
}

PHP;
    }

    /**
     * @param  array<string, array<int, string>>  $rules
     */
    private function formatRules(array $rules): string
    {
        $lines = [];
        foreach ($rules as $field => $ruleList) {
            $ruleStr = implode(', ', array_map(fn (string $r) => "'{$r}'", $ruleList));
            $lines[] = "            '{$field}' => [{$ruleStr}],";
        }

        return implode("\n", $lines);
    }
}
