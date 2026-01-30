<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Http\Controllers;

use CodingSunshine\Architect\Services\AI\AIBoilerplateGenerator;
use CodingSunshine\Architect\Services\AI\AICodeGenerator;
use CodingSunshine\Architect\Services\AI\AIConflictDetector;
use CodingSunshine\Architect\Services\AI\AIPackageAnalyzer;
use CodingSunshine\Architect\Services\AI\AISchemaSuggestionService;
use CodingSunshine\Architect\Services\AI\AISchemaValidator;
use CodingSunshine\Architect\Services\AI\PackageAssistant;
use CodingSunshine\Architect\Services\DraftParser;
use CodingSunshine\Architect\Support\Draft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * API controller for AI-powered features in Architect Studio.
 *
 * All endpoints require Prism to be installed and configured.
 */
final class ArchitectAIController
{
    /**
     * Chat with the AI assistant.
     */
    public function chat(Request $request, PackageAssistant $assistant, DraftParser $parser): JsonResponse
    {
        $message = $request->input('message');

        if (! is_string($message) || trim($message) === '') {
            return response()->json(['error' => 'Message is required.'], 422);
        }

        $draft = $this->getDraft($request, $parser);
        $response = $assistant->chat(trim($message), $draft);

        return response()->json($response);
    }

    /**
     * Get quick suggestions for the chat.
     */
    public function chatSuggestions(Request $request, PackageAssistant $assistant, DraftParser $parser): JsonResponse
    {
        $draft = $this->getDraft($request, $parser);

        return response()->json([
            'suggestions' => $assistant->getQuickSuggestions($draft),
        ]);
    }

    /**
     * Analyze a package and return its capabilities.
     */
    public function analyzePackage(Request $request, AIPackageAnalyzer $analyzer): JsonResponse
    {
        $packageName = $request->input('package');

        if (! is_string($packageName) || trim($packageName) === '') {
            return response()->json(['error' => 'Package name is required.'], 422);
        }

        $useCache = $request->boolean('cache', true);
        $analysis = $analyzer->analyzePackage(trim($packageName), $useCache);

        if ($analysis === null) {
            return response()->json([
                'error' => "Unable to analyze package '{$packageName}'. Ensure it's installed and AI is available.",
            ], 404);
        }

        return response()->json($analysis);
    }

    /**
     * Get suggestions for the schema.
     */
    public function suggestions(Request $request, AISchemaSuggestionService $suggestionService, DraftParser $parser): JsonResponse
    {
        $draft = $this->getDraft($request, $parser);

        if ($draft === null) {
            return response()->json(['error' => 'No draft available.'], 404);
        }

        $suggestions = $suggestionService->analyzeDraft($draft);

        if ($suggestions === null) {
            return response()->json(['error' => 'AI suggestions are not available.'], 503);
        }

        return response()->json($suggestions);
    }

    /**
     * Suggest fields for a model.
     */
    public function suggestFields(Request $request, AISchemaSuggestionService $suggestionService): JsonResponse
    {
        $modelName = $request->input('model');
        $existingFields = $request->input('existing_fields', []);

        if (! is_string($modelName) || trim($modelName) === '') {
            return response()->json(['error' => 'Model name is required.'], 422);
        }

        $fields = $suggestionService->suggestFieldsForModel(
            trim($modelName),
            is_array($existingFields) ? $existingFields : []
        );

        if ($fields === null) {
            return response()->json(['error' => 'AI suggestions are not available.'], 503);
        }

        return response()->json(['fields' => $fields]);
    }

    /**
     * Validate the schema with AI.
     */
    public function validateSchema(Request $request, AISchemaValidator $validator, DraftParser $parser): JsonResponse
    {
        $draft = $this->getDraft($request, $parser);

        if ($draft === null) {
            return response()->json(['error' => 'No draft available.'], 404);
        }

        $validation = $validator->validate($draft);

        if ($validation === null) {
            return response()->json(['error' => 'AI validation is not available.'], 503);
        }

        return response()->json($validation);
    }

    /**
     * Get recommendations for the schema.
     */
    public function recommendations(Request $request, AISchemaValidator $validator, DraftParser $parser): JsonResponse
    {
        $draft = $this->getDraft($request, $parser);

        if ($draft === null) {
            return response()->json(['error' => 'No draft available.'], 404);
        }

        $recommendations = $validator->getRecommendations($draft);

        if ($recommendations === null) {
            return response()->json(['error' => 'AI recommendations are not available.'], 503);
        }

        return response()->json(['recommendations' => $recommendations]);
    }

    /**
     * Detect package conflicts.
     */
    public function conflicts(AIConflictDetector $detector): JsonResponse
    {
        $conflicts = $detector->detectConflicts();

        if ($conflicts === null) {
            return response()->json(['error' => 'AI conflict detection is not available.'], 503);
        }

        return response()->json($conflicts);
    }

    /**
     * Check package compatibility.
     */
    public function checkCompatibility(Request $request, AIConflictDetector $detector): JsonResponse
    {
        $packageName = $request->input('package');

        if (! is_string($packageName) || trim($packageName) === '') {
            return response()->json(['error' => 'Package name is required.'], 422);
        }

        $result = $detector->checkPackageCompatibility(trim($packageName));

        if ($result === null) {
            return response()->json(['error' => 'AI compatibility check is not available.'], 503);
        }

        return response()->json($result);
    }

    /**
     * Generate code for a model.
     */
    public function generateCode(Request $request, AICodeGenerator $generator, DraftParser $parser): JsonResponse
    {
        $modelName = $request->input('model');
        $type = $request->input('type', 'model'); // model, factory, seeder, tests, controller, policy

        if (! is_string($modelName) || trim($modelName) === '') {
            return response()->json(['error' => 'Model name is required.'], 422);
        }

        $draft = $this->getDraft($request, $parser);
        $modelDef = $draft?->getModel(trim($modelName)) ?? [];

        $code = match ($type) {
            'factory' => $generator->generateFactory(trim($modelName), $modelDef),
            'seeder' => $generator->generateSeeder(trim($modelName), $modelDef),
            'tests' => $generator->generateTests(trim($modelName), $modelDef, $request->input('framework', 'pest')),
            'controller' => $generator->generateControllerMethods(trim($modelName), $modelDef, $request->input('stack', 'inertia-react')),
            'policy' => $generator->generatePolicy(trim($modelName), $modelDef),
            'request' => $generator->generateFormRequest(trim($modelName), $modelDef, $request->input('action', 'store')),
            default => null,
        };

        if ($code === null) {
            return response()->json(['error' => 'AI code generation is not available.'], 503);
        }

        return response()->json(['code' => $code]);
    }

    /**
     * Generate boilerplate for adding a feature.
     */
    public function generateBoilerplate(Request $request, AIBoilerplateGenerator $generator, DraftParser $parser): JsonResponse
    {
        $modelName = $request->input('model');
        $feature = $request->input('feature');

        if (! is_string($modelName) || trim($modelName) === '') {
            return response()->json(['error' => 'Model name is required.'], 422);
        }

        if (! is_string($feature) || trim($feature) === '') {
            return response()->json(['error' => 'Feature name is required.'], 422);
        }

        $draft = $this->getDraft($request, $parser);
        $modelDef = $draft?->getModel(trim($modelName)) ?? [];

        $boilerplate = $generator->generateFeatureBoilerplate(trim($modelName), $modelDef, trim($feature));

        if ($boilerplate === null) {
            return response()->json(['error' => 'AI boilerplate generation is not available.'], 503);
        }

        return response()->json($boilerplate);
    }

    /**
     * Generate complete files for a model.
     */
    public function generateComplete(Request $request, AIBoilerplateGenerator $generator, DraftParser $parser): JsonResponse
    {
        $modelName = $request->input('model');
        $type = $request->input('type', 'model'); // model, migration, factory, seeder, tests, resource

        if (! is_string($modelName) || trim($modelName) === '') {
            return response()->json(['error' => 'Model name is required.'], 422);
        }

        $draft = $this->getDraft($request, $parser);
        $modelDef = $draft?->getModel(trim($modelName)) ?? [];

        $code = match ($type) {
            'model' => $generator->generateCompleteModel(trim($modelName), $modelDef),
            'migration' => $generator->generateCompleteMigration(trim($modelName), $modelDef),
            'factory' => $generator->generateCompleteFactory(trim($modelName), $modelDef),
            'seeder' => $generator->generateCompleteSeeder(trim($modelName), $modelDef, $request->integer('count', 10)),
            'tests' => $generator->generateCompleteTests(trim($modelName), $modelDef, $request->input('framework', 'pest')),
            'resource' => $generator->generateResource(trim($modelName), $modelDef),
            default => null,
        };

        if ($code === null) {
            return response()->json(['error' => 'AI code generation is not available or unsupported type.'], 503);
        }

        return response()->json(['code' => $code]);
    }

    /**
     * Get the draft from request or file.
     */
    private function getDraft(Request $request, DraftParser $parser): ?Draft
    {
        $yaml = $request->input('yaml');

        if (is_string($yaml) && trim($yaml) !== '') {
            try {
                $data = Yaml::parse($yaml);

                if (is_array($data)) {
                    return new Draft(
                        models: $data['models'] ?? [],
                        actions: $data['actions'] ?? [],
                        pages: $data['pages'] ?? [],
                    );
                }
            } catch (\Throwable) {
                return null;
            }
        }

        $path = config('architect.draft_path', base_path('draft.yaml'));

        if (File::exists($path)) {
            try {
                return $parser->parse($path);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
