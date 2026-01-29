<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\Generators;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\FileOwnership;
use CodingSunshine\Architect\Support\HashComputer;
use Illuminate\Support\Facades\File;

final class ActionGenerator implements GeneratorInterface
{
    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        $generated = [];
        $basePath = app_path('Actions');

        foreach (array_keys($draft->actions) as $actionName) {
            $actionDef = $draft->actions[$actionName];
            if (! is_array($actionDef)) {
                continue;
            }

            $content = $this->renderAction($actionName, $actionDef);
            $path = "{$basePath}/{$actionName}.php";

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
        return $draft->actions !== [];
    }

    /**
     * @param  array<string, mixed>  $actionDef
     */
    private function renderAction(string $actionName, array $actionDef): string
    {
        $model = isset($actionDef['model']) && is_string($actionDef['model']) ? $actionDef['model'] : null;
        $returnType = isset($actionDef['return']) && is_string($actionDef['return']) ? $actionDef['return'] : 'void';
        $params = isset($actionDef['params']) && is_array($actionDef['params']) ? $actionDef['params'] : [];

        $lines = ['namespace App\\Actions;'];
        if ($model !== null) {
            $lines[] = "use App\\Models\\{$model};";
        }
        $useBlock = implode("\n", $lines)."\n\n";

        $paramList = $this->buildParamList($params, $model);
        $returnType = $this->normalizeReturnType($returnType, $model);
        $body = '        // TODO: implement action logic';
        if ($model !== null) {
            $body = "        // TODO: implement {$actionName} for {$model}";
        }

        return <<<PHP
<?php

declare(strict_types=1);

{$useBlock}final readonly class {$actionName}
{
    public function handle({$paramList}): {$returnType}
    {
{$body}
    }
}

PHP;
    }

    /**
     * @param  array<int, mixed>  $params
     */
    private function buildParamList(array $params, ?string $model): string
    {
        if ($params === []) {
            return '';
        }

        $parts = [];
        foreach ($params as $i => $p) {
            $name = is_string($p) ? $p : "param{$i}";
            $type = 'mixed';
            if (is_array($p) && isset($p['name'], $p['type'])) {
                $name = $p['name'];
                $type = $p['type'];
            } elseif ($model !== null && in_array(strtolower($name), ['model', strtolower($model), strtolower($model).'id'], true)) {
                $type = $model;
            }
            $parts[] = "{$type} \${$name}";
        }

        return implode(', ', $parts);
    }

    private function normalizeReturnType(string $return, ?string $model): string
    {
        if ($return === 'void' || $return === '') {
            return 'void';
        }
        if (strtolower($return) === 'model' && $model !== null) {
            return $model;
        }

        return $return;
    }
}
