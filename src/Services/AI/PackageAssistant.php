<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services\AI;

use CodingSunshine\Architect\Services\AppModelService;
use CodingSunshine\Architect\Services\PackageDiscovery;
use CodingSunshine\Architect\Support\Draft;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Tool;

/**
 * AI-powered conversational assistant for Architect Studio.
 *
 * Provides an intelligent chat interface that can:
 * - Answer questions about the current schema
 * - Suggest improvements and features
 * - Help configure packages
 * - Generate code snippets
 * - Explain Laravel concepts
 */
final class PackageAssistant extends AIServiceBase
{
    /**
     * Conversation history for context.
     *
     * @var array<array{role: string, content: string}>
     */
    private array $conversationHistory = [];

    public function __construct(
        private readonly AppModelService $appModelService,
        private readonly PackageDiscovery $packageDiscovery,
        private readonly AIPackageAnalyzer $packageAnalyzer,
        private readonly AISchemaSuggestionService $suggestionService,
    ) {}

    /**
     * Process a user message and return a response.
     *
     * @return array{response: string, suggestions?: array<string>, actions?: array<array{type: string, payload: mixed}>}
     */
    public function chat(string $message, ?Draft $draft = null): array
    {
        if (! $this->isAvailable()) {
            return [
                'response' => 'AI assistant is not available. Please ensure Prism is installed and configured.',
            ];
        }

        $context = $this->buildContext($draft);
        $tools = $this->buildTools($draft);

        try {
            $response = Prism::text()
                ->using($this->getProvider(), $this->getModel())
                ->withSystemPrompt($this->getSystemPrompt($context))
                ->withPrompt($message)
                ->withTools($tools)
                ->withMaxSteps(3)
                ->asText();

            $this->conversationHistory[] = ['role' => 'user', 'content' => $message];
            $this->conversationHistory[] = ['role' => 'assistant', 'content' => $response->text ?? ''];

            return $this->parseResponse($response->text ?? '');
        } catch (\Throwable $e) {
            return [
                'response' => 'I encountered an error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get quick suggestions based on current context.
     *
     * @return array<string>
     */
    public function getQuickSuggestions(?Draft $draft = null): array
    {
        if ($draft === null || $draft->modelNames() === []) {
            return [
                'What models do I need for an e-commerce app?',
                'How do I add user authentication?',
                'Suggest a basic blog schema',
            ];
        }

        $modelNames = $draft->modelNames();

        return [
            "What fields should {$modelNames[0]} have?",
            'Are there any missing relationships?',
            'What packages would improve this schema?',
            'Help me add soft deletes',
            'Generate a seeder for my models',
        ];
    }

    /**
     * Reset conversation history.
     */
    public function resetConversation(): void
    {
        $this->conversationHistory = [];
    }

    /**
     * Get conversation history.
     *
     * @return array<array{role: string, content: string}>
     */
    public function getHistory(): array
    {
        return $this->conversationHistory;
    }

    /**
     * Build context information for the AI.
     *
     * @return array<string, mixed>
     */
    private function buildContext(?Draft $draft): array
    {
        $installed = $this->packageDiscovery->installed();
        $knownPackages = array_filter(
            $installed,
            fn (string $name) => $this->packageAnalyzer->isLaravelPackage($name),
            ARRAY_FILTER_USE_KEY
        );

        return [
            'draft' => $draft !== null ? [
                'models' => $draft->models,
                'actions' => array_keys($draft->actions),
                'pages' => array_keys($draft->pages),
            ] : null,
            'fingerprint' => $this->appModelService->fingerprint(),
            'installed_packages' => array_keys($installed),
            'laravel_packages' => array_keys($knownPackages),
            'conversation_length' => count($this->conversationHistory),
        ];
    }

    /**
     * Build tools for the assistant.
     *
     * @return array<Tool>
     */
    private function buildTools(?Draft $draft): array
    {
        $tools = [];

        // Tool to suggest fields
        $tools[] = Tool::as('suggest_fields')
            ->for('Suggest database fields for a model based on its name and purpose')
            ->withStringParameter('model_name', 'The name of the model')
            ->withStringParameter('purpose', 'What the model is used for')
            ->using(function (string $model_name, string $purpose): string {
                $suggestions = $this->suggestionService->suggestFieldsForModel($model_name);

                return $suggestions !== null
                    ? json_encode($suggestions, JSON_PRETTY_PRINT)
                    : 'Unable to generate suggestions';
            });

        // Tool to analyze a package
        $tools[] = Tool::as('analyze_package')
            ->for('Get detailed information about what a Laravel package provides')
            ->withStringParameter('package_name', 'The Composer package name (e.g., spatie/laravel-medialibrary)')
            ->using(function (string $package_name): string {
                $analysis = $this->packageAnalyzer->analyzePackage($package_name, false);

                return $analysis !== null
                    ? json_encode($analysis, JSON_PRETTY_PRINT)
                    : "Package '{$package_name}' not found or not installed";
            });

        // Tool to list installed packages
        $tools[] = Tool::as('list_packages')
            ->for('List all Laravel packages installed in the application')
            ->using(function (): string {
                $installed = $this->packageDiscovery->installed();
                $laravelPackages = array_filter(
                    $installed,
                    fn (string $name) => $this->packageAnalyzer->isLaravelPackage($name),
                    ARRAY_FILTER_USE_KEY
                );

                return json_encode(array_keys($laravelPackages), JSON_PRETTY_PRINT);
            });

        // Tool to check schema
        if ($draft !== null) {
            $tools[] = Tool::as('check_schema')
                ->for('Check the current schema for issues and get suggestions')
                ->using(function () use ($draft): string {
                    $suggestions = $this->suggestionService->analyzeDraft($draft);

                    return $suggestions !== null
                        ? json_encode($suggestions, JSON_PRETTY_PRINT)
                        : 'Unable to analyze schema';
                });
        }

        return $tools;
    }

    /**
     * Get the system prompt for the assistant.
     *
     * @param  array<string, mixed>  $context
     */
    private function getSystemPrompt(array $context): string
    {
        $prompt = <<<'PROMPT'
You are an expert Laravel Architect assistant helping users design and build their applications.

Your capabilities:
- Help design database schemas and relationships
- Suggest appropriate Laravel packages for features
- Generate code snippets for models, migrations, controllers, etc.
- Explain Laravel concepts and best practices
- Configure and integrate packages

Guidelines:
- Be concise but helpful
- Provide practical, actionable advice
- Use code examples when helpful
- Consider the user's installed packages
- Follow Laravel conventions and best practices
- When suggesting schema changes, use Architect draft.yaml syntax

PROMPT;

        if (isset($context['fingerprint']) && $context['fingerprint'] !== []) {
            $prompt .= "\nCurrent app context (use this instead of full codebase; check packages first for the use case):\n";
            $prompt .= json_encode($context['fingerprint'], JSON_PRETTY_PRINT)."\n";
        }

        if ($context['draft'] !== null) {
            $prompt .= "\nCurrent schema:\n";
            $prompt .= 'Models: '.implode(', ', array_keys($context['draft']['models']))."\n";
            if ($context['draft']['actions'] !== []) {
                $prompt .= 'Actions: '.implode(', ', $context['draft']['actions'])."\n";
            }
        } else {
            $prompt .= "\nNo schema is currently loaded. Help the user create one.\n";
        }

        $prompt .= "\nInstalled Laravel packages: ".implode(', ', $context['laravel_packages']);

        return $this->prependDefaultInstructions($prompt);
    }

    /**
     * Parse the response for suggestions and actions.
     *
     * @return array{response: string, suggestions?: array<string>, actions?: array<array{type: string, payload: mixed}>}
     */
    private function parseResponse(string $text): array
    {
        $result = ['response' => $text];

        // Extract code blocks for potential actions
        if (preg_match_all('/```(?:yaml|yml)\n(.*?)```/s', $text, $yamlMatches)) {
            $result['actions'] = [];
            foreach ($yamlMatches[1] as $yaml) {
                $result['actions'][] = [
                    'type' => 'apply_yaml',
                    'payload' => trim($yaml),
                ];
            }
        }

        // Extract suggestions (lines starting with - or *)
        if (preg_match_all('/^[\-\*]\s+(.+)$/m', $text, $suggestionMatches)) {
            $result['suggestions'] = array_slice($suggestionMatches[1], 0, 5);
        }

        return $result;
    }
}
