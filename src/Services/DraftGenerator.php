<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use CodingSunshine\Architect\Schema\SchemaValidator;
use Illuminate\Support\Facades\File;

final class DraftGenerator
{
    public function __construct(
        private readonly SchemaValidator $validator
    ) {}

    /**
     * Generate YAML draft from natural language description.
     * When Prism/echolabs is not available or AI is disabled, returns a minimal stub.
     */
    public function generate(string $description, ?string $existingDraftPath = null): string
    {
        $aiEnabled = config('architect.ai.enabled', true);
        $prismAvailable = class_exists(\Prism\Prism\Facades\Prism::class);

        if ($aiEnabled && $prismAvailable) {
            return $this->generateWithAi($description, $existingDraftPath);
        }

        return $this->generateStub($description, $existingDraftPath);
    }

    private function generateWithAi(string $description, ?string $existingDraftPath): string
    {
        $existingYaml = $existingDraftPath && File::exists($existingDraftPath)
            ? File::get($existingDraftPath)
            : null;

        $maxRetries = (int) config('architect.ai.max_retries', 2);
        $retryWithFeedback = (bool) config('architect.ai.retry_with_feedback', true);
        $lastErrors = [];

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $prompt = $this->buildPrompt($description, $existingYaml, $retryWithFeedback ? $lastErrors : null);
            $systemPrompt = $this->getSystemPrompt();

            try {
                $response = \Prism\Prism\Facades\Prism::text()
                    ->withSystemPrompt($systemPrompt)
                    ->withPrompt($prompt)
                    ->asText();

                $yaml = $this->extractYaml($response->text ?? '');
                $data = \Symfony\Component\Yaml\Yaml::parse($yaml);

                if (is_array($data)) {
                    $errors = $this->validator->validate($data);
                    if ($errors === []) {
                        return $yaml;
                    }
                    $lastErrors = $errors;
                } else {
                    $lastErrors = ['Output must be a valid YAML object.'];
                }
            } catch (\Throwable $e) {
                $lastErrors = [$e->getMessage()];
            }
        }

        return $this->generateStub($description, $existingDraftPath);
    }

    private function generateStub(string $description, ?string $existingDraftPath): string
    {
        $name = $this->inferModelName($description);

        return <<<YAML
# Generated draft - edit manually or use AI (Prism) for full generation
schema_version: "1.0"

models:
  {$name}:
    # Add columns, e.g.:
    # title: string:400
    # content: longtext
    # published_at: timestamp nullable
    # author_id: id:User foreign
    #
    # relationships:
    #   belongsTo: User:author
    #   hasMany: Comment
    #
    # seeder:
    #   category: development
    #   count: 10

actions:
  Create{$name}:
    model: {$name}
    return: {$name}
  Update{$name}:
    model: {$name}
    params: [{$name}, attributes]
    return: void
  Delete{$name}:
    model: {$name}
    params: [{$name}]
    return: void

pages:
  {$name}: {}

routes:
  {$name}:
    resource: true

YAML;
    }

    /**
     * @param  array<int, string>|null  $validationErrors
     */
    private function buildPrompt(string $description, ?string $existingYaml, ?array $validationErrors = null): string
    {
        $prompt = "Generate a YAML scaffold definition for:\n\n{$description}";

        if ($existingYaml !== null && $existingYaml !== '') {
            $prompt .= "\n\nExtend this existing draft (only add new, do not duplicate):\n\n{$existingYaml}";
        }

        if ($validationErrors !== null && $validationErrors !== []) {
            $prompt .= "\n\nPrevious attempt failed validation. Fix these issues:\n";
            foreach ($validationErrors as $err) {
                $prompt .= "- {$err}\n";
            }
        }

        return $prompt;
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a Laravel application architect. Generate ONLY valid YAML matching the Architect draft schema.
Output only the YAML document, no markdown fences or explanations.

Schema rules:
- models: keys are singular StudlyCase (Post, Comment). Values are column definitions (type:modifiers) or nested keys (relationships, seeder, softDeletes).
- Column format: column_name: type:length optional_modifiers (e.g. title: string:400, published_at: timestamp nullable, author_id: id:User foreign)
- relationships: belongsTo, hasMany, hasOne, belongsToMany. Use "Model:alias" for aliases.
- seeder: category (essential|development|production), count, json: true
- actions: model name -> action name -> params, validation, save, events
- pages: model name -> page name (index, show, create, edit) -> props, form
PROMPT;
    }

    private function extractYaml(string $text): string
    {
        if (preg_match('/^---\s*\n(.*?)(?=---|\z)/s', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/```(?:yaml|yml)?\s*\n(.*?)```/s', $text, $m)) {
            return trim($m[1]);
        }

        return trim($text);
    }

    private function inferModelName(string $description): string
    {
        $words = preg_split('/\s+/', trim($description), 3);
        $first = $words[0] ?? 'Item';

        return ucfirst(strtolower($first));
    }
}
