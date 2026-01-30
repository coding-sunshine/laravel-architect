<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\Generators;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Services\StateManager;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\FileOwnership;
use CodingSunshine\Architect\Support\HashComputer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class MigrationGenerator implements GeneratorInterface
{
    public function __construct(
        private readonly StateManager $stateManager
    ) {}

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $backup = [];
        $basePath = database_path('migrations');

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $tableName = Str::snake(Str::plural($modelName));
            $content = $this->renderCreateMigration($modelName, $tableName, $modelDef);

            $existingPath = $this->stateManager->getGeneratedPathForTable($tableName);
            if ($existingPath !== null && File::exists($existingPath)) {
                $path = $existingPath;
            } else {
                $filename = date('Y_m_d_His') . '_create_' . $tableName . '_table.php';
                $path = "{$basePath}/{$filename}";
            }

            if (File::exists($path)) {
                $backup[$path] = File::get($path);
            }
            File::ensureDirectoryExists($basePath);
            File::put($path, $content);

            $generated[$path] = [
                'path' => $path,
                'hash' => HashComputer::compute($content),
                'ownership' => FileOwnership::Regenerate->value,
                'table' => $tableName,
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
    private function renderCreateMigration(string $modelName, string $tableName, array $modelDef): string
    {
        $columns = $this->buildMigrationColumns($modelDef);

        return <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table): void {
            \$table->id();
{$columns}
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};

PHP;
    }

    /**
     * @param  array<string, mixed>  $modelDef
     */
    private function buildMigrationColumns(array $modelDef): string
    {
        $lines = [];
        $usesSoftDeletes = ! empty($modelDef['softDeletes']);

        foreach ($modelDef as $columnName => $definition) {
            if (in_array($columnName, ['relationships', 'seeder', 'softDeletes', 'timestamps', 'traits'], true)) {
                continue;
            }
            if (! is_string($definition)) {
                continue;
            }

            $line = $this->columnDefinitionToMigration($columnName, $definition);
            if ($line !== '') {
                $lines[] = '            ' . $line;
            }
        }

        if ($usesSoftDeletes) {
            $lines[] = '            $table->softDeletes();';
        }

        return implode("\n", $lines);
    }

    private function columnDefinitionToMigration(string $columnName, string $definition): string
    {
        $parts = preg_split('/\s+/', trim($definition), 2);
        $typePart = $parts[0] ?? '';
        $modifiers = $parts[1] ?? '';

        $nullable = str_contains($modifiers, 'nullable');
        $unique = str_contains($modifiers, 'unique');
        $index = str_contains($modifiers, 'index');
        $foreign = str_contains($modifiers, 'foreign');

        if (preg_match('/^id:(.+)$/i', $typePart, $m)) {
            $refModel = trim($m[1]);
            $refTable = Str::snake(Str::plural($refModel));

            return "\$table->foreignId('{$columnName}')->constrained('{$refTable}')->cascadeOnDelete();";
        }

        if (str_contains($typePart, ':')) {
            [$type, $arg] = explode(':', $typePart, 2);
        } else {
            $type = $typePart;
            $arg = null;
        }

        $type = strtolower($type);
        $php = '';

        switch ($type) {
            case 'string':
                $length = $arg !== null && $arg !== '' ? (int) $arg : 255;
                $php = "\$table->string('{$columnName}', {$length})";
                break;
            case 'text':
                $php = "\$table->text('{$columnName}')";
                break;
            case 'longtext':
                $php = "\$table->longText('{$columnName}')";
                break;
            case 'integer':
                $php = "\$table->integer('{$columnName}')";
                break;
            case 'bigInteger':
                $php = "\$table->unsignedBigInteger('{$columnName}')";
                break;
            case 'decimal':
                $php = "\$table->decimal('{$columnName}', 10, 2)";
                if ($arg !== null && str_contains($arg, ',')) {
                    [$p, $s] = explode(',', $arg, 2);
                    $php = "\$table->decimal('{$columnName}', " . (int) trim($p) . ', ' . (int) trim($s) . ')';
                }
                break;
            case 'boolean':
                $php = "\$table->boolean('{$columnName}')";
                break;
            case 'date':
                $php = "\$table->date('{$columnName}')";
                break;
            case 'datetime':
            case 'timestamp':
                $php = "\$table->timestamp('{$columnName}')";
                break;
            case 'json':
                $php = "\$table->json('{$columnName}')";
                break;
            case 'uuid':
                $php = "\$table->uuid('{$columnName}')";
                break;
            default:
                $php = "\$table->string('{$columnName}')";
        }

        if ($nullable) {
            $php .= '->nullable()';
        }
        if ($unique) {
            $php .= '->unique()';
        }
        if ($index && ! $unique) {
            $php .= '->index()';
        }

        return $php . ';';
    }
}
