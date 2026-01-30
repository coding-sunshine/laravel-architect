<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Http\Controllers;

use CodingSunshine\Architect\Schema\SchemaValidator;
use CodingSunshine\Architect\Services\AppModelService;
use CodingSunshine\Architect\Services\BuildOrchestrator;
use CodingSunshine\Architect\Services\BuildPlanner;
use CodingSunshine\Architect\Services\DraftGenerator;
use CodingSunshine\Architect\Services\DraftParser;
use CodingSunshine\Architect\Services\ImportService;
use CodingSunshine\Architect\Services\PackageSuggestionService;
use CodingSunshine\Architect\Services\PackageValidationService;
use CodingSunshine\Architect\Services\SchemaDiscovery;
use CodingSunshine\Architect\Services\StateManager;
use CodingSunshine\Architect\Services\StudioContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

    public function draftFromAi(Request $request, DraftGenerator $generator, AppModelService $appModel): JsonResponse
    {
        $description = $request->input('description');

        if (! is_string($description) || trim($description) === '') {
            return response()->json(['error' => 'Description is required.'], 422);
        }

        $extend = config('architect.draft_path', base_path('draft.yaml'));
        $existingPath = File::exists($extend) ? $extend : null;

        try {
            $yaml = $generator->generate(trim($description), $existingPath, $appModel->fingerprint());
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

    /**
     * Simple flow: generate draft from description, return summary + yaml (no file diffs).
     * Use fingerprint and current draft. Response: summary (model/action/page counts) and draft delta (full yaml).
     */
    public function simpleGenerate(Request $request, DraftGenerator $generator, AppModelService $appModel, DraftParser $parser): JsonResponse
    {
        $description = $request->input('description');

        if (! is_string($description) || trim($description) === '') {
            return response()->json(['error' => 'Description is required.'], 422);
        }

        $draftPath = config('architect.draft_path', base_path('draft.yaml'));
        $existingPath = File::exists($draftPath) ? $draftPath : null;

        try {
            $yaml = $generator->generate(trim($description), $existingPath, $appModel->fingerprint());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $summary = ['models' => 0, 'actions' => 0, 'pages' => 0];
        try {
            $data = Yaml::parse($yaml);
            if (is_array($data)) {
                $summary['models'] = count($data['models'] ?? []);
                $summary['actions'] = count($data['actions'] ?? []);
                $summary['pages'] = count($data['pages'] ?? []);
            }
        } catch (\Throwable) {
            // keep zero counts
        }

        return response()->json([
            'summary' => $summary,
            'yaml' => $yaml,
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
        $mergeSchemaColumns = $request->boolean('merge_schema_columns');

        $draft = $import->import($modelFilter, $mergeSchemaColumns);

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

    /**
     * Wizard: Add model. POST body: name, table (optional), columns (optional) or infer_from_db (use SchemaDiscovery).
     */
    public function wizardAddModel(Request $request, DraftParser $parser, SchemaDiscovery $schemaDiscovery, SchemaValidator $validator): JsonResponse
    {
        $name = $request->input('name');
        if (! is_string($name) || trim($name) === '') {
            return response()->json(['error' => 'Model name is required.'], 422);
        }
        $name = Str::studly(trim($name));
        $table = $request->input('table');
        $table = is_string($table) && $table !== '' ? $table : Str::snake(Str::plural($name));
        $columns = $request->input('columns');
        $inferFromDb = $request->boolean('infer_from_db');
        if ($inferFromDb) {
            $dbSchema = $schemaDiscovery->discover();
            $cols = $dbSchema[$table]['columns'] ?? [];
            $modelDef = [];
            foreach ($cols as $col) {
                $modelDef[$col] = 'string';
            }
        } else {
            $modelDef = is_array($columns) && $columns !== [] ? $columns : ['name' => 'string'];
        }
        $current = $this->getCurrentDraftArray($request, $parser);
        $current['models'] = $current['models'] ?? [];
        $current['models'][$name] = $modelDef;
        $current['actions'] = $current['actions'] ?? [];
        $current['actions']["Create{$name}"] = ['model' => $name, 'return' => $name];
        $current['actions']["Update{$name}"] = ['model' => $name, 'params' => [$name, 'attributes'], 'return' => 'void'];
        $current['actions']["Delete{$name}"] = ['model' => $name, 'params' => [$name], 'return' => 'void'];
        $current['pages'] = $current['pages'] ?? [];
        $current['pages'][$name] = $current['pages'][$name] ?? [];
        $current['routes'] = $current['routes'] ?? [];
        $current['routes'][$name] = ['resource' => true];
        $errors = $validator->validate($current);
        if ($errors !== []) {
            return response()->json(['error' => 'Validation failed.', 'errors' => $errors], 422);
        }
        $yaml = Yaml::dump($current, 4, 2);

        return response()->json([
            'summary' => ['models' => count($current['models']), 'actions' => count($current['actions']), 'pages' => count($current['pages'])],
            'yaml' => $yaml,
        ]);
    }

    /**
     * Wizard: Add CRUD resource for a model. POST body: model_name, options (api, admin).
     */
    public function wizardAddCrudResource(Request $request, DraftParser $parser, SchemaValidator $validator): JsonResponse
    {
        $modelName = $request->input('model_name');
        if (! is_string($modelName) || trim($modelName) === '') {
            return response()->json(['error' => 'Model name is required.'], 422);
        }
        $modelName = Str::studly(trim($modelName));
        $current = $this->getCurrentDraftArray($request, $parser);
        $current['models'] = $current['models'] ?? [];
        if (! isset($current['models'][$modelName])) {
            $current['models'][$modelName] = ['name' => 'string'];
        }
        $current['actions'] = $current['actions'] ?? [];
        $current['actions']["Create{$modelName}"] = ['model' => $modelName, 'return' => $modelName];
        $current['actions']["Update{$modelName}"] = ['model' => $modelName, 'params' => [$modelName, 'attributes'], 'return' => 'void'];
        $current['actions']["Delete{$modelName}"] = ['model' => $modelName, 'params' => [$modelName], 'return' => 'void'];
        $current['pages'] = $current['pages'] ?? [];
        $current['pages'][$modelName] = $current['pages'][$modelName] ?? [];
        $current['routes'] = $current['routes'] ?? [];
        $current['routes'][$modelName] = ['resource' => true];
        $errors = $validator->validate($current);
        if ($errors !== []) {
            return response()->json(['error' => 'Validation failed.', 'errors' => $errors], 422);
        }
        $yaml = Yaml::dump($current, 4, 2);

        return response()->json([
            'summary' => ['models' => count($current['models']), 'actions' => count($current['actions']), 'pages' => count($current['pages'])],
            'yaml' => $yaml,
        ]);
    }

    /**
     * Wizard: Add relationship. POST body: from_model, type, to_model.
     */
    public function wizardAddRelationship(Request $request, DraftParser $parser, SchemaValidator $validator): JsonResponse
    {
        $from = $request->input('from_model');
        $type = $request->input('type');
        $to = $request->input('to_model');
        if (! is_string($from) || ! is_string($type) || ! is_string($to)) {
            return response()->json(['error' => 'from_model, type, and to_model are required.'], 422);
        }
        $from = Str::studly(trim($from));
        $to = Str::studly(trim($to));
        $allowed = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany'];
        if (! in_array($type, $allowed, true)) {
            return response()->json(['error' => 'type must be one of: '.implode(', ', $allowed)], 422);
        }
        $current = $this->getCurrentDraftArray($request, $parser);
        $current['models'] = $current['models'] ?? [];
        if (! isset($current['models'][$from])) {
            $current['models'][$from] = [];
        }
        if (! is_array($current['models'][$from])) {
            $current['models'][$from] = [];
        }
        $current['models'][$from]['relationships'] = $current['models'][$from]['relationships'] ?? [];
        $rel = $current['models'][$from]['relationships'];
        $existing = $rel[$type] ?? '';
        $current['models'][$from]['relationships'][$type] = $existing === '' ? $to : $existing.', '.$to;
        $errors = $validator->validate($current);
        if ($errors !== []) {
            return response()->json(['error' => 'Validation failed.', 'errors' => $errors], 422);
        }
        $yaml = Yaml::dump($current, 4, 2);

        return response()->json([
            'summary' => ['relationship' => "{$from} {$type} {$to}"],
            'yaml' => $yaml,
        ]);
    }

    /**
     * Wizard: Add page. POST body: name, type (index|show|create|edit), model (optional).
     */
    public function wizardAddPage(Request $request, DraftParser $parser, SchemaValidator $validator): JsonResponse
    {
        $name = $request->input('name');
        $type = $request->input('type');
        $model = $request->input('model');
        if (! is_string($name) || trim($name) === '') {
            return response()->json(['error' => 'Page name is required.'], 422);
        }
        $name = Str::studly(trim($name));
        $type = is_string($type) && $type !== '' ? $type : 'index';
        $current = $this->getCurrentDraftArray($request, $parser);
        $current['pages'] = $current['pages'] ?? [];
        $pageKey = is_string($model) && $model !== '' ? Str::studly(trim($model)) : $name;
        $current['pages'][$pageKey] = is_array($current['pages'][$pageKey] ?? null) ? $current['pages'][$pageKey] : [];
        $current['routes'] = $current['routes'] ?? [];
        if (! isset($current['routes'][$pageKey])) {
            $current['routes'][$pageKey] = [];
        }
        $errors = $validator->validate($current);
        if ($errors !== []) {
            return response()->json(['error' => 'Validation failed.', 'errors' => $errors], 422);
        }
        $yaml = Yaml::dump($current, 4, 2);

        return response()->json([
            'summary' => ['pages' => count($current['pages'])],
            'yaml' => $yaml,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getCurrentDraftArray(Request $request, DraftParser $parser): array
    {
        $yaml = $request->input('yaml');
        if (is_string($yaml) && trim($yaml) !== '') {
            try {
                $data = Yaml::parse($yaml);

                return is_array($data) ? $data : ['models' => [], 'actions' => [], 'pages' => [], 'routes' => [], 'schema_version' => '1.0'];
            } catch (\Throwable) {
                return ['models' => [], 'actions' => [], 'pages' => [], 'routes' => [], 'schema_version' => '1.0'];
            }
        }
        $path = config('architect.draft_path', base_path('draft.yaml'));
        if (! File::exists($path)) {
            return ['models' => [], 'actions' => [], 'pages' => [], 'routes' => [], 'schema_version' => '1.0'];
        }
        try {
            $draft = $parser->parse($path);
        } catch (\Throwable) {
            return ['models' => [], 'actions' => [], 'pages' => [], 'routes' => [], 'schema_version' => '1.0'];
        }

        return [
            'models' => $draft->models,
            'actions' => $draft->actions,
            'pages' => $draft->pages,
            'routes' => $draft->routes,
            'schema_version' => $draft->schemaVersion,
        ];
    }
}
