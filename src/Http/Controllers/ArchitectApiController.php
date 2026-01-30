<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Http\Controllers;

use CodingSunshine\Architect\Schema\SchemaValidator;
use CodingSunshine\Architect\Services\BuildOrchestrator;
use CodingSunshine\Architect\Services\BuildPlanner;
use CodingSunshine\Architect\Services\DraftGenerator;
use CodingSunshine\Architect\Services\DraftParser;
use CodingSunshine\Architect\Services\ImportService;
use CodingSunshine\Architect\Services\PackageSuggestionService;
use CodingSunshine\Architect\Services\PackageValidationService;
use CodingSunshine\Architect\Services\StateManager;
use CodingSunshine\Architect\Services\StudioContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

final class ArchitectApiController
{
    public function context(StudioContextService $context): JsonResponse
    {
        return response()->json($context->build());
    }

    public function getDraft(): JsonResponse
    {
        $path = config('architect.draft_path', base_path('draft.yaml'));

        if (! File::exists($path)) {
            return response()->json(['yaml' => '', 'exists' => false], 200);
        }

        $yaml = File::get($path);

        return response()->json(['yaml' => $yaml, 'exists' => true]);
    }

    public function putDraft(Request $request): JsonResponse
    {
        $path = config('architect.draft_path', base_path('draft.yaml'));
        $content = $request->input('yaml') ?? $request->getContent();

        if (! is_string($content) || $content === '') {
            return response()->json(['valid' => false, 'errors' => ['Draft content is required.']], 422);
        }

        $validator = app(SchemaValidator::class);
        try {
            $data = Yaml::parse($content);
        } catch (\Throwable $e) {
            return response()->json(['valid' => false, 'errors' => ['Invalid YAML: '.$e->getMessage()]], 422);
        }

        if (! is_array($data)) {
            return response()->json(['valid' => false, 'errors' => ['Draft must be a YAML object.']], 422);
        }

        $errors = $validator->validate($data);
        if ($errors !== [] && ! $request->boolean('force')) {
            return response()->json(['valid' => false, 'errors' => $errors], 422);
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);

        return response()->json(['valid' => true, 'saved' => true]);
    }

    public function validateDraft(Request $request, DraftParser $parser, SchemaValidator $validator): JsonResponse
    {
        $yaml = $request->input('yaml');

        if (is_string($yaml) && $yaml !== '') {
            try {
                $data = Yaml::parse($yaml);
            } catch (\Throwable $e) {
                return response()->json(['valid' => false, 'errors' => ['Invalid YAML: '.$e->getMessage()]]);
            }

            if (! is_array($data)) {
                return response()->json(['valid' => false, 'errors' => ['Draft must be a YAML object.']]);
            }

            $errors = $validator->validate($data);

            return response()->json(['valid' => $errors === [], 'errors' => $errors]);
        }

        $path = config('architect.draft_path', base_path('draft.yaml'));
        if (! File::exists($path)) {
            return response()->json(['valid' => false, 'errors' => ['Draft file not found.']]);
        }

        try {
            $parser->parse($path);

            return response()->json(['valid' => true, 'errors' => []]);
        } catch (\InvalidArgumentException $e) {
            $errors = array_filter(explode("\n", $e->getMessage()));

            return response()->json(['valid' => false, 'errors' => $errors]);
        }
    }

    public function plan(DraftParser $parser, BuildPlanner $planner): JsonResponse
    {
        $path = config('architect.draft_path', base_path('draft.yaml'));

        if (! File::exists($path)) {
            return response()->json(['error' => 'Draft file not found.'], 404);
        }

        try {
            $draft = $parser->parse($path);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $steps = $planner->plan($draft);

        return response()->json([
            'steps' => $steps,
            'summary' => [
                'models' => count($draft->models),
                'actions' => count($draft->actions),
                'pages' => count($draft->pages),
            ],
        ]);
    }

    public function build(Request $request, BuildOrchestrator $orchestrator): JsonResponse
    {
        $path = config('architect.draft_path', base_path('draft.yaml'));
        $only = $request->input('only');
        $only = is_array($only) ? $only : null;
        $force = (bool) $request->input('force', false);

        $result = $orchestrator->build($path, $only, $force);

        return response()->json([
            'success' => $result->success,
            'generated' => array_keys($result->generated),
            'skipped' => $result->skipped,
            'warnings' => $result->warnings,
            'errors' => $result->errors,
        ]);
    }

    public function draftFromAi(Request $request, DraftGenerator $generator): JsonResponse
    {
        $description = $request->input('description');

        if (! is_string($description) || trim($description) === '') {
            return response()->json(['error' => 'Description is required.'], 422);
        }

        $extend = config('architect.draft_path', base_path('draft.yaml'));
        $existingPath = File::exists($extend) ? $extend : null;

        try {
            $yaml = $generator->generate(trim($description), $existingPath);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $apply = $request->boolean('apply');
        if ($apply) {
            File::ensureDirectoryExists(dirname($extend));
            File::put($extend, $yaml);
        }

        return response()->json([
            'yaml' => $yaml,
            'applied' => $apply,
        ]);
    }

    public function starters(): JsonResponse
    {
        $context = app(StudioContextService::class);

        return response()->json(['starters' => $context->build()['starters']]);
    }

    public function getStarter(string $name): JsonResponse
    {
        $packageRoot = dirname(__DIR__, 2);
        $path = $packageRoot.'/resources/starters/'.$name.'.yaml';

        if (! File::exists($path)) {
            return response()->json(['error' => "Starter '{$name}' not found."], 404);
        }

        $yaml = File::get($path);

        return response()->json(['name' => $name, 'yaml' => $yaml]);
    }

    public function import(Request $request, ImportService $import): JsonResponse
    {
        $modelFilter = $request->input('models');
        $modelFilter = is_array($modelFilter) ? $modelFilter : null;

        $draft = $import->import($modelFilter);

        return response()->json($draft);
    }

    public function status(StateManager $state): JsonResponse
    {
        $data = $state->load();

        return response()->json($data);
    }

    public function explain(DraftParser $parser): JsonResponse
    {
        $path = config('architect.draft_path', base_path('draft.yaml'));

        if (! File::exists($path)) {
            return response()->json(['error' => 'Draft file not found.'], 404);
        }

        try {
            $draft = $parser->parse($path);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'draft_path' => $path,
            'models' => $draft->modelNames(),
            'actions' => array_keys($draft->actions),
            'pages' => array_keys($draft->pages),
            'model_count' => count($draft->models),
            'action_count' => count($draft->actions),
            'page_count' => count($draft->pages),
        ]);
    }

    public function preview(Request $request, DraftParser $parser): JsonResponse
    {
        $path = config('architect.draft_path', base_path('draft.yaml'));
        if (! File::exists($path)) {
            return response()->json(['error' => 'Draft file not found.'], 404);
        }

        try {
            $draft = $parser->parse($path);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $type = $request->input('type'); // model, action, page
        $name = $request->input('name');

        if ($type === 'model') {
            $def = $draft->getModel($name);
            if ($def) {
                $gen = app(\CodingSunshine\Architect\Services\Generators\ModelGenerator::class);

                return response()->json(['code' => $gen->renderModel($name, $def)]);
            }
        }

        if ($type === 'action') {
            $def = $draft->actions[$name] ?? null;
            if ($def) {
                $gen = app(\CodingSunshine\Architect\Services\Generators\ActionGenerator::class);

                return response()->json(['code' => $gen->renderAction($name, $def)]);
            }
        }

        return response()->json(['code' => '// No preview available for this item.']);
    }

    /**
     * Analyze a draft and return suggestions and validation results.
     *
     * Returns package-aware suggestions for:
     * - Schema additions based on field names
     * - Missing traits based on schema keys
     * - Relationship suggestions
     * - Validation warnings for missing packages
     */
    public function analyze(
        Request $request,
        DraftParser $parser,
        PackageSuggestionService $suggestionService,
        PackageValidationService $validationService,
    ): JsonResponse {
        $yaml = $request->input('yaml');

        // Parse from request or file
        if (is_string($yaml) && $yaml !== '') {
            try {
                $data = Yaml::parse($yaml);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'Invalid YAML: '.$e->getMessage()], 422);
            }

            if (! is_array($data)) {
                return response()->json(['error' => 'Draft must be a YAML object.'], 422);
            }

            // Create a temporary draft object
            $draft = new \CodingSunshine\Architect\Support\Draft(
                models: $data['models'] ?? [],
                actions: $data['actions'] ?? [],
                pages: $data['pages'] ?? [],
            );
        } else {
            $path = config('architect.draft_path', base_path('draft.yaml'));

            if (! File::exists($path)) {
                return response()->json(['error' => 'Draft file not found.'], 404);
            }

            try {
                $draft = $parser->parse($path);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        // Get suggestions
        $suggestions = $suggestionService->analyze($draft);

        // Get validation results
        $validation = $validationService->validate($draft);

        return response()->json([
            'suggestions' => $suggestions,
            'validation' => $validation,
        ]);
    }
}
