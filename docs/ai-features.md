# AI-Powered Features

Laravel Architect integrates with [Prism](https://github.com/prism-php/prism) to provide intelligent, AI-powered features that enhance your development workflow.

## Requirements

To use AI features, install Prism:

```bash
composer require prism-php/prism
```

Configure your AI provider in `.env`:

```env
# Anthropic (recommended)
ANTHROPIC_API_KEY=sk-...

# Or OpenAI
OPENAI_API_KEY=sk-...

# Or OpenRouter
OPENROUTER_API_KEY=sk-...
```

Configure the provider in `config/architect.php`:

```php
'ai' => [
    'enabled' => env('ARCHITECT_AI_ENABLED', true),
    'provider' => env('ARCHITECT_AI_PROVIDER', 'anthropic'),
    'model' => env('ARCHITECT_AI_MODEL'), // auto-selected per provider
],
```

## App Context (Fingerprint)

All AI calls receive a compact **fingerprint** of your app (stack, model names and tables, route count and sample, package names, conventions). No raw file contents or full codebase are sent. This keeps context small and consistent. The fingerprint is built from Laravel APIs (routes, schema, package discovery) and is cached for performance.

## Default Instructions

Every AI prompt is prepended with a default instruction: *"Check the application's installed packages and conventions first; prefer using an existing package (e.g. Filament, Power Grid, Inertia Tables) for the use case; only fall back to plain framework code when no suitable package is installed."* You can override this in `config/architect.php` under `ai.default_instructions`.

## AI Services

### AIPackageAnalyzer

Dynamically analyzes any installed Composer package to understand its capabilities.

**What it detects:**
- Traits the package provides (e.g., `InteractsWithMedia`, `Searchable`)
- Interfaces models should implement (e.g., `HasMedia`)
- Database migrations the package creates
- Configuration keys to check
- Artisan commands available
- Setup steps required

**Usage:**

```php
use CodingSunshine\Architect\Services\AI\AIPackageAnalyzer;

$analyzer = app(AIPackageAnalyzer::class);

// Analyze a package
$analysis = $analyzer->analyzePackage('spatie/laravel-medialibrary');
// Returns: traits, interfaces, migrations, config_keys, artisan_commands, etc.

// Check if it's a Laravel package
$isLaravel = $analyzer->isLaravelPackage('spatie/laravel-medialibrary'); // true

// Get quick hints without full AI analysis
$hints = $analyzer->getQuickHints('spatie/laravel-medialibrary');
// Returns: description, type, category
```

### AISchemaSuggestionService

Analyzes your draft schema and suggests improvements based on model semantics and installed packages.

**Capabilities:**
- Suggests missing fields based on model name (e.g., "Product" should have `price`, `sku`)
- Recommends relationships based on conventions
- Identifies missing models referenced in relationships
- Suggests package features that would benefit specific models
- Proposes validation rules for fields
- Identifies fields that should be indexed

**Usage:**

```php
use CodingSunshine\Architect\Services\AI\AISchemaSuggestionService;
use CodingSunshine\Architect\Support\Draft;

$service = app(AISchemaSuggestionService::class);
$draft = new Draft(models: [...]);

// Comprehensive analysis
$suggestions = $service->analyzeDraft($draft);
/*
Returns:
- field_suggestions: {model_name: [{field, type, reason}]}
- relationship_suggestions: {model_name: [{type, target, reason}]}
- feature_suggestions: {model_name: [{feature, reason}]}
- missing_models: [{name, reason, suggested_fields}]
- validation_suggestions: {model_name: {field_name: [rules]}}
- index_suggestions: {model_name: [fields_to_index]}
- naming_improvements: {model_name: [{current, suggested, reason}]}
*/

// Suggest fields for a specific model
$fields = $service->suggestFieldsForModel('Product', ['name', 'description']);
// Returns: [{field: 'price', type: 'decimal:10,2', reason: '...'}]

// Detect missing models
$missing = $service->detectMissingModels($draft);
// Returns models referenced but not defined
```

### AICodeGenerator

Generates contextually-aware code for models, factories, seeders, tests, and more.

**Features:**
- Package integration code (traits, interfaces, required methods)
- Migration columns with package-specific additions
- Factories with realistic, contextual fake data
- Seeders with proper relationship handling
- Comprehensive test cases
- Form Request validation rules
- Policies with ownership checks

**Usage:**

```php
use CodingSunshine\Architect\Services\AI\AICodeGenerator;

$generator = app(AICodeGenerator::class);

// Generate package integration code
$code = $generator->generatePackageIntegration('Product', $modelDef, 'spatie/laravel-medialibrary');
// Returns PHP code for traits, interfaces, and required methods

// Generate realistic factory
$factory = $generator->generateFactory('Product', $modelDef);
// Returns factory definition with contextual data (product names, prices, etc.)

// Generate comprehensive tests
$tests = $generator->generateTests('Product', $modelDef, 'pest');
// Returns Pest or PHPUnit tests covering CRUD, relationships, and features

// Generate Form Request
$request = $generator->generateFormRequest('Product', $modelDef, 'store');
// Returns complete Form Request class with rules and messages
```

### PackageAssistant

An interactive chat assistant that helps with schema design and code generation.

**Capabilities:**
- Answers questions about your schema
- Suggests improvements and features
- Helps configure packages
- Generates code snippets
- Explains Laravel concepts

**Usage in Studio UI:**
Press `A` or click "AI Assistant" to open the chat panel.

**Programmatic usage:**

```php
use CodingSunshine\Architect\Services\AI\PackageAssistant;
use CodingSunshine\Architect\Support\Draft;

$assistant = app(PackageAssistant::class);
$draft = new Draft(models: [...]);

// Chat with the assistant
$response = $assistant->chat('What fields should a Product model have?', $draft);
/*
Returns:
- response: The assistant's message
- suggestions: Follow-up questions to ask
- actions: Potential schema changes to apply
*/

// Get quick suggestions based on context
$suggestions = $assistant->getQuickSuggestions($draft);
// Returns: ['What fields should Product have?', 'Are there missing relationships?', ...]
```

### AIConflictDetector

Detects potential conflicts between installed packages.

**What it detects:**
- Direct conflicts (e.g., multiple auth packages)
- Namespace collisions
- Database table conflicts
- Configuration key conflicts
- Version incompatibilities

**Usage:**

```php
use CodingSunshine\Architect\Services\AI\AIConflictDetector;

$detector = app(AIConflictDetector::class);

// Detect all conflicts
$result = $detector->detectConflicts();
/*
Returns:
- conflicts: [{type, packages, description, severity, resolution}]
- warnings: [{type, package, message}]
- recommendations: ['best practice suggestions']
*/

// Check if a new package would conflict
$compatibility = $detector->checkPackageCompatibility('laravel/passport');
/*
Returns:
- compatible: bool
- conflicts: [{package, reason}]
- warnings: ['messages']
*/
```

### AISchemaValidator

Validates schemas against best practices with AI-powered analysis.

**Validation categories:**
- **Issues**: Missing fields, invalid types, convention violations
- **Performance**: N+1 risks, missing indexes
- **Security**: Mass assignment vulnerabilities, sensitive data exposure
- **Best Practices**: Soft deletes, timestamps, UUID usage

**Usage:**

```php
use CodingSunshine\Architect\Services\AI\AISchemaValidator;
use CodingSunshine\Architect\Support\Draft;

$validator = app(AISchemaValidator::class);
$draft = new Draft(models: [...]);

// Full validation
$result = $validator->validate($draft);
/*
Returns:
- valid: bool
- score: 0-100 quality score
- issues: [{severity, category, model, field, message, suggestion}]
- best_practices: [{category, status, message}]
- performance_warnings: [{model, issue, impact, fix}]
- security_concerns: [{model, field, issue, risk, mitigation}]
*/

// Quick validation (no AI, faster)
$issues = $validator->quickValidate('Product', $modelDef);
// Returns: [{type, message}]

// Get recommendations
$recommendations = $validator->getRecommendations($draft);
// Returns: [{category, recommendation, priority, implementation}]
```

### AIBoilerplateGenerator

Generates complete, ready-to-use boilerplate code for adding features.

**What it generates:**
- Complete model classes with all features
- Full migration files
- Complete factory classes with states
- Seeder classes with relationship handling
- Comprehensive test files
- API Resource classes

**Usage:**

```php
use CodingSunshine\Architect\Services\AI\AIBoilerplateGenerator;

$generator = app(AIBoilerplateGenerator::class);

// Generate boilerplate for adding a feature
$boilerplate = $generator->generateFeatureBoilerplate('Product', $modelDef, 'media');
/*
Returns:
- model_additions: PHP code to add to model
- migration_additions: Migration column definitions
- factory_additions: Factory state additions
- seeder_additions: Seeder code
- test_additions: Test cases
- config_changes: Config modifications needed
- setup_steps: Instructions
*/

// Generate complete model file
$model = $generator->generateCompleteModel('Product', $modelDef);
// Returns complete PHP model class file

// Generate complete test file
$tests = $generator->generateCompleteTests('Product', $modelDef, 'pest');
// Returns complete Pest test file
```

## API Endpoints

All AI features are accessible via the Studio API:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/architect/api/ai/chat` | POST | Chat with the assistant |
| `/architect/api/ai/chat/suggestions` | GET | Get quick chat suggestions |
| `/architect/api/ai/analyze-package` | POST | Analyze a package |
| `/architect/api/ai/suggestions` | POST | Get schema suggestions |
| `/architect/api/ai/suggest-fields` | POST | Suggest fields for a model |
| `/architect/api/ai/validate` | POST | Validate schema with AI |
| `/architect/api/ai/recommendations` | POST | Get schema recommendations |
| `/architect/api/ai/conflicts` | GET | Detect package conflicts |
| `/architect/api/ai/check-compatibility` | POST | Check package compatibility |
| `/architect/api/ai/generate-code` | POST | Generate code snippets |
| `/architect/api/ai/generate-boilerplate` | POST | Generate feature boilerplate |
| `/architect/api/ai/generate-complete` | POST | Generate complete files |

| `/architect/api/simple-generate` | POST | Generate draft from description; returns summary (models, actions, pages) + full YAML |
| `/architect/api/wizard/add-model` | POST | Wizard: add a model (optional: infer columns from DB) |
| `/architect/api/wizard/add-crud-resource` | POST | Wizard: add full CRUD for a model |
| `/architect/api/wizard/add-relationship` | POST | Wizard: add relationship between two models |
| `/architect/api/wizard/add-page` | POST | Wizard: add a page |

### Example API Usage

```javascript
// Chat with assistant
const response = await fetch('/architect/api/ai/chat', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        message: 'What fields should a Product model have?',
        yaml: currentDraftYaml
    })
});
const data = await response.json();
console.log(data.response); // Assistant's message

// Get field suggestions
const fields = await fetch('/architect/api/ai/suggest-fields', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        model: 'Product',
        existing_fields: ['name', 'description']
    })
});
```

## Studio UI Integration

### AI Assistant Panel
- Press `A` to open
- Ask questions in natural language
- Get contextual suggestions based on your schema
- Apply suggested YAML changes directly

### Describe with AI
- Generate complete drafts from natural language descriptions
- **Summary first** button: calls `simple-generate` and shows a summary (e.g. "2 models, 3 actions, 2 pages") plus optional YAML (toggle "Show YAML"); Apply merges into the draft. After a summary, **Expand to full draft** calls `draft-from-ai` with the same description to replace the proposed draft with the full YAML in one step.
- **Full draft** button: calls `draft-from-ai` for full AI draft generation in one step
- Review and edit before applying; merge with existing draft or replace

### New feature (wizards)
- **New feature** dropdown (no AI required): Add model, Add CRUD resource, Add relationship, Add page
- Each opens a small form; on Apply, the returned draft is merged into the current draft
- Uses app model and current draft only; useful for quick structural changes

### Features Panel
- View available schema features based on installed packages
- See which packages enable which features
- Get setup instructions for unavailable features

## Best Practices

1. **Be Specific**: When chatting with the assistant, provide context about your model's purpose
2. **Review Suggestions**: Always review AI-generated code before applying
3. **Iterate**: Use the chat to refine suggestions based on your specific needs
4. **Use Quick Suggestions**: The assistant provides contextual quick suggestions based on your schema
5. **Check Conflicts Early**: Run conflict detection before installing new packages

## Troubleshooting

### AI Not Available
```
AI assistant is not available. Please ensure Prism is installed and configured.
```
- Install Prism: `composer require prism-php/prism`
- Set your API key in `.env`
- Ensure `ARCHITECT_AI_ENABLED=true`

### Slow Responses
- AI features make external API calls which may take a few seconds
- Package analysis results are cached for 24 hours
- Use quick suggestions and quick validation for faster feedback

### Poor Suggestions
- Provide more context in your model definitions
- Include field comments or descriptions
- Be specific when asking the assistant
