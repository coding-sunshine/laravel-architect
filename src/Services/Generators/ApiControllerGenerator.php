<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\Generators;

use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Services\GeneratorVariantResolver;
use CodingSunshine\Architect\Support\BuildResult;
use CodingSunshine\Architect\Support\Draft;
use CodingSunshine\Architect\Support\FileOwnership;
use CodingSunshine\Architect\Support\HashComputer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Generates API controllers with package-aware authentication middleware.
 *
 * Features:
 * - Sanctum/Passport middleware based on installed packages
 * - API Resource responses
 * - Versioned API endpoints
 */
final class ApiControllerGenerator implements GeneratorInterface
{
    public function __construct(
        private readonly GeneratorVariantResolver $variantResolver,
    ) {}

    public function generate(Draft $draft, string $draftPath): BuildResult
    {
        // Only generate if API generation is enabled
        if (! config('architect.conventions.generate_api', false)) {
            return new BuildResult(generated: []);
        }

        $generated = [];
        $basePath = app_path('Http/Controllers/Api/V1');
        $apiAuth = $this->variantResolver->resolveApiAuth();
        $apiDocs = $this->variantResolver->resolveApiDocs();

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            // Skip if model explicitly disabled for API
            if (isset($modelDef['api']) && $modelDef['api'] === false) {
                continue;
            }

            $controllerName = $modelName.'Controller';
            $content = $this->renderApiController($modelName, $draft->actions, $apiAuth, $apiDocs);
            $path = "{$basePath}/{$controllerName}.php";

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
        return config('architect.conventions.generate_api', false)
            && $draft->modelNames() !== [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $actions
     */
    private function renderApiController(string $modelName, array $actions, string $apiAuth, string $apiDocs): string
    {
        $slug = Str::kebab(Str::plural($modelName));
        $singular = Str::camel($modelName);
        $createAction = 'Create'.$modelName;
        $updateAction = 'Update'.$modelName;
        $deleteAction = 'Delete'.$modelName;
        $storeRequest = 'Store'.$modelName.'Request';
        $updateRequest = 'Update'.$modelName.'Request';
        $deleteRequest = 'Delete'.$modelName.'Request';
        $hasCreate = isset($actions[$createAction]);
        $hasUpdate = isset($actions[$updateAction]);
        $hasDelete = isset($actions[$deleteAction]);

        $middleware = $this->getMiddlewareAttribute($apiAuth);
        $docAnnotations = $this->getDocAnnotations($modelName, $apiDocs);

        $methods = [];
        $methods[] = $this->renderIndexMethod($modelName, $singular, $slug, $apiDocs);
        if ($hasCreate) {
            $methods[] = $this->renderStoreMethod($modelName, $singular, $slug, $storeRequest, $createAction, $apiDocs);
        }
        $methods[] = $this->renderShowMethod($modelName, $singular, $slug, $apiDocs);
        if ($hasUpdate) {
            $methods[] = $this->renderUpdateMethod($modelName, $singular, $slug, $updateRequest, $updateAction, $apiDocs);
        }
        if ($hasDelete) {
            $methods[] = $this->renderDestroyMethod($modelName, $singular, $slug, $deleteRequest, $deleteAction, $apiDocs);
        }

        $useLines = [
            'use App\\Models\\'.$modelName.';',
            'use Illuminate\\Http\\JsonResponse;',
            'use Illuminate\\Http\\Resources\\Json\\AnonymousResourceCollection;',
        ];

        if ($hasCreate) {
            $useLines[] = 'use App\\Actions\\'.$createAction.';';
            $useLines[] = 'use App\\Http\\Requests\\'.$storeRequest.';';
        }
        if ($hasUpdate) {
            $useLines[] = 'use App\\Actions\\'.$updateAction.';';
            $useLines[] = 'use App\\Http\\Requests\\'.$updateRequest.';';
        }
        if ($hasDelete) {
            $useLines[] = 'use App\\Actions\\'.$deleteAction.';';
            $useLines[] = 'use App\\Http\\Requests\\'.$deleteRequest.';';
        }

        sort($useLines);

        return $this->buildControllerClass($modelName, $useLines, $methods, $middleware, $docAnnotations);
    }

    private function getMiddlewareAttribute(string $apiAuth): string
    {
        return match ($apiAuth) {
            GeneratorVariantResolver::API_AUTH_SANCTUM => "#[Middleware(['auth:sanctum'])]",
            GeneratorVariantResolver::API_AUTH_PASSPORT => "#[Middleware(['auth:api'])]",
            default => '',
        };
    }

    private function getDocAnnotations(string $modelName, string $apiDocs): string
    {
        if ($apiDocs === GeneratorVariantResolver::API_DOCS_SCRIBE) {
            return <<<PHP

/**
 * @group {$modelName} Management
 *
 * APIs for managing {$modelName}s
 */
PHP;
        }

        return '';
    }

    private function renderIndexMethod(string $modelName, string $singular, string $slug, string $apiDocs): string
    {
        $annotation = $apiDocs === GeneratorVariantResolver::API_DOCS_SCRIBE
            ? "    /**\n     * List all {$slug}\n     *\n     * @apiResourceCollection App\\Http\\Resources\\{$modelName}Resource\n     * @apiResourceModel App\\Models\\{$modelName}\n     */\n"
            : '';

        return <<<PHP
{$annotation}    public function index(): AnonymousResourceCollection
    {
        \${$slug} = {$modelName}::query()->paginate();

        return {$modelName}Resource::collection(\${$slug});
    }
PHP;
    }

    private function renderStoreMethod(string $modelName, string $singular, string $slug, string $storeRequest, string $createAction, string $apiDocs): string
    {
        $annotation = $apiDocs === GeneratorVariantResolver::API_DOCS_SCRIBE
            ? "    /**\n     * Create a new {$singular}\n     *\n     * @apiResource App\\Http\\Resources\\{$modelName}Resource\n     * @apiResourceModel App\\Models\\{$modelName}\n     */\n"
            : '';

        return <<<PHP
{$annotation}    public function store({$storeRequest} \$request, {$createAction} \$action): JsonResponse
    {
        \${$singular} = \$action->handle(\$request->validated());

        return response()->json(
            new {$modelName}Resource(\${$singular}),
            201
        );
    }
PHP;
    }

    private function renderShowMethod(string $modelName, string $singular, string $slug, string $apiDocs): string
    {
        $annotation = $apiDocs === GeneratorVariantResolver::API_DOCS_SCRIBE
            ? "    /**\n     * Get a specific {$singular}\n     *\n     * @apiResource App\\Http\\Resources\\{$modelName}Resource\n     * @apiResourceModel App\\Models\\{$modelName}\n     */\n"
            : '';

        return <<<PHP
{$annotation}    public function show({$modelName} \${$singular}): JsonResponse
    {
        return response()->json(
            new {$modelName}Resource(\${$singular})
        );
    }
PHP;
    }

    private function renderUpdateMethod(string $modelName, string $singular, string $slug, string $updateRequest, string $updateAction, string $apiDocs): string
    {
        $annotation = $apiDocs === GeneratorVariantResolver::API_DOCS_SCRIBE
            ? "    /**\n     * Update an existing {$singular}\n     *\n     * @apiResource App\\Http\\Resources\\{$modelName}Resource\n     * @apiResourceModel App\\Models\\{$modelName}\n     */\n"
            : '';

        return <<<PHP
{$annotation}    public function update({$updateRequest} \$request, {$modelName} \${$singular}, {$updateAction} \$action): JsonResponse
    {
        \${$singular} = \$action->handle(\${$singular}, \$request->validated());

        return response()->json(
            new {$modelName}Resource(\${$singular})
        );
    }
PHP;
    }

    private function renderDestroyMethod(string $modelName, string $singular, string $slug, string $deleteRequest, string $deleteAction, string $apiDocs): string
    {
        $annotation = $apiDocs === GeneratorVariantResolver::API_DOCS_SCRIBE
            ? "    /**\n     * Delete a {$singular}\n     *\n     * @response 204\n     */\n"
            : '';

        return <<<PHP
{$annotation}    public function destroy({$deleteRequest} \$request, {$modelName} \${$singular}, {$deleteAction} \$action): JsonResponse
    {
        \$action->handle(\${$singular});

        return response()->json(null, 204);
    }
PHP;
    }

    /**
     * @param  array<string>  $useLines
     * @param  array<string>  $methods
     */
    private function buildControllerClass(string $modelName, array $useLines, array $methods, string $middleware, string $docAnnotation): string
    {
        $useBlock = implode("\n", $useLines);
        $methodsBlock = implode("\n\n", $methods);

        // Add middleware use statement if needed
        if ($middleware !== '') {
            $useBlock .= "\nuse Illuminate\\Routing\\Controllers\\Middleware;";
        }

        // Add Resource use statement
        $useBlock .= "\nuse App\\Http\\Resources\\{$modelName}Resource;";

        $middlewareBlock = $middleware !== '' ? "\n{$middleware}" : '';

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

{$useBlock}
{$docAnnotation}
{$middlewareBlock}
final readonly class {$modelName}Controller
{
{$methodsBlock}
}

PHP;
    }
}
