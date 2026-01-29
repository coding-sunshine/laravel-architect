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

final class SeederGenerator implements GeneratorInterface
{
    private const CATEGORIES = ['development' => 'Development', 'essential' => 'Essential', 'production' => 'Production'];

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $basePath = database_path('seeders');

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $seederConfig = $modelDef['seeder'] ?? null;
            if (! is_array($seederConfig)) {
                continue;
            }

            $category = $this->resolveCategory($seederConfig['category'] ?? 'development');
            $count = (int) ($seederConfig['count'] ?? 5);
            $useJson = ! empty($seederConfig['json']);

            $tableName = Str::snake(Str::plural($modelName));
            $className = Str::plural($modelName).'Seeder';
            $namespace = 'Database\\Seeders\\'.$category;
            $content = $this->renderSeeder($modelName, $namespace, $className, $tableName, $count, $useJson);

            $dir = "{$basePath}/{$category}";
            $path = "{$dir}/{$className}.php";

            File::ensureDirectoryExists($dir);
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
        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef !== null && is_array($modelDef['seeder'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function resolveCategory(mixed $category): string
    {
        $key = is_string($category) ? strtolower($category) : 'development';

        return self::CATEGORIES[$key] ?? 'Development';
    }

    private function renderSeeder(
        string $modelName,
        string $namespace,
        string $className,
        string $tableName,
        int $count,
        bool $useJson,
    ): string {
        $modelFqcn = 'App\\Models\\'.$modelName;

        $runBody = $useJson
            ? "\$this->seedFromJson();\n        \$this->seedFromFactory();"
            : '$this->seedFromFactory();';

        $methods = '';
        if ($useJson) {
            $dataPath = "database_path('seeders/data/{$tableName}.json')";
            $methods = <<<PHP

    private function seedFromJson(): void
    {
        \$path = {$dataPath};
        if (! is_file(\$path)) {
            return;
        }
        \$data = json_decode((string) file_get_contents(\$path), true);
        if (! is_array(\$data) || ! isset(\$data['{$tableName}'])) {
            return;
        }
        foreach (\$data['{$tableName}'] as \$row) {
            \$key = \$row['id'] ?? \$row['email'] ?? null;
            if (\$key !== null) {
                {$modelName}::query()->updateOrCreate(
                    [is_numeric(\$key) ? 'id' : 'email' => \$key],
                    \$row
                );
            } else {
                {$modelName}::query()->create(\$row);
            }
        }
    }
PHP;
        }

        $methods .= "\n\n    private function seedFromFactory(): void\n    {\n        {$modelName}::factory()->count({$count})->create();\n    }";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$modelFqcn};
use Illuminate\Database\Seeder;

final class {$className} extends Seeder
{
    public function run(): void
    {
        {$runBody}
    }
{$methods}
}

PHP;
    }
}
