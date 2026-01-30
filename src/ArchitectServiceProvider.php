<?php

declare(strict_types=1);

namespace CodingSunshine\Architect;

use CodingSunshine\Architect\Console\Commands\BuildCommand;
use CodingSunshine\Architect\Console\Commands\CheckCommand;
use CodingSunshine\Architect\Console\Commands\DraftCommand;
use CodingSunshine\Architect\Console\Commands\ExplainCommand;
use CodingSunshine\Architect\Console\Commands\FixCommand;
use CodingSunshine\Architect\Console\Commands\ImportCommand;
use CodingSunshine\Architect\Console\Commands\PackagesCommand;
use CodingSunshine\Architect\Console\Commands\PlanCommand;
use CodingSunshine\Architect\Console\Commands\StarterCommand;
use CodingSunshine\Architect\Console\Commands\StatusCommand;
use CodingSunshine\Architect\Console\Commands\ValidateCommand;
use CodingSunshine\Architect\Console\Commands\WatchCommand;
use CodingSunshine\Architect\Console\Commands\WhyCommand;
use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Http\Controllers\ArchitectAIController;
use CodingSunshine\Architect\Http\Controllers\ArchitectApiController;
use CodingSunshine\Architect\Http\Controllers\ArchitectStudioController;
use CodingSunshine\Architect\Services\AI\AIBoilerplateGenerator;
use CodingSunshine\Architect\Services\AI\AICodeGenerator;
use CodingSunshine\Architect\Services\AI\AIConflictDetector;
use CodingSunshine\Architect\Services\AI\AIPackageAnalyzer;
use CodingSunshine\Architect\Services\AI\AISchemaSuggestionService;
use CodingSunshine\Architect\Services\AI\AISchemaValidator;
use CodingSunshine\Architect\Services\AI\PackageAssistant;
use CodingSunshine\Architect\Services\AppModelService;
use CodingSunshine\Architect\Services\BuildOrchestrator;
use CodingSunshine\Architect\Services\BuildPlanner;
use CodingSunshine\Architect\Services\ChangeDetector;
use CodingSunshine\Architect\Services\CrudStackResolver;
use CodingSunshine\Architect\Services\DraftGenerator;
use CodingSunshine\Architect\Services\DraftParser;
use CodingSunshine\Architect\Services\Generators\ActionGenerator;
use CodingSunshine\Architect\Services\Generators\ApiControllerGenerator;
use CodingSunshine\Architect\Services\Generators\ControllerGenerator;
use CodingSunshine\Architect\Services\Generators\FactoryGenerator;
use CodingSunshine\Architect\Services\Generators\MigrationGenerator;
use CodingSunshine\Architect\Services\Generators\ModelGenerator;
use CodingSunshine\Architect\Services\Generators\PageGenerator;
use CodingSunshine\Architect\Services\Generators\RequestGenerator;
use CodingSunshine\Architect\Services\Generators\RouteGenerator;
use CodingSunshine\Architect\Services\Generators\SeederGenerator;
use CodingSunshine\Architect\Services\Generators\TestGenerator;
use CodingSunshine\Architect\Services\Generators\TypeScriptGenerator;
use CodingSunshine\Architect\Services\GeneratorVariantResolver;
use CodingSunshine\Architect\Services\ImportService;
use CodingSunshine\Architect\Services\PackageDiscovery;
use CodingSunshine\Architect\Services\PackageRegistry;
use CodingSunshine\Architect\Services\PackageSuggestionService;
use CodingSunshine\Architect\Services\PackageValidationService;
use CodingSunshine\Architect\Services\RouteDiscovery;
use CodingSunshine\Architect\Services\SchemaDiscovery;
use CodingSunshine\Architect\Services\StackDetector;
use CodingSunshine\Architect\Services\StateManager;
use CodingSunshine\Architect\Services\StudioContextService;
use CodingSunshine\Architect\Services\UiDriverDetector;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ArchitectServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('architect')
            ->hasConfigFile()
            ->hasViews('architect')
            ->hasCommands([
                DraftCommand::class,
                ValidateCommand::class,
                PlanCommand::class,
                BuildCommand::class,
                StatusCommand::class,
                ImportCommand::class,
                PackagesCommand::class,
                ExplainCommand::class,
                WatchCommand::class,
                FixCommand::class,
                StarterCommand::class,
                WhyCommand::class,
                CheckCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Architect::class);
        $this->app->singleton(StateManager::class);
        $this->app->singleton(DraftParser::class);
        $this->app->singleton(DraftGenerator::class);
        $this->app->singleton(ChangeDetector::class);
        $this->app->singleton(BuildOrchestrator::class);
        $this->app->singleton(BuildPlanner::class);
        $this->app->singleton(StackDetector::class);
        $this->app->singleton(PackageDiscovery::class);
        $this->app->singleton(PackageRegistry::class, function ($app) {
            return new PackageRegistry(config('architect.packages', []));
        });
        $this->app->singleton(UiDriverDetector::class);
        $this->app->singleton(ImportService::class);
        $this->app->singleton(RouteDiscovery::class);
        $this->app->singleton(SchemaDiscovery::class);
        $this->app->singleton(AppModelService::class);
        $this->app->singleton(CrudStackResolver::class);

        // Package-aware services
        $this->app->singleton(GeneratorVariantResolver::class);
        $this->app->singleton(PackageSuggestionService::class);
        $this->app->singleton(PackageValidationService::class);

        // AI-powered services (require Prism)
        $this->app->singleton(AIPackageAnalyzer::class);
        $this->app->singleton(AISchemaSuggestionService::class);
        $this->app->singleton(AICodeGenerator::class);
        $this->app->singleton(AIConflictDetector::class);
        $this->app->singleton(AISchemaValidator::class);
        $this->app->singleton(AIBoilerplateGenerator::class);
        $this->app->singleton(PackageAssistant::class);

        $this->app->singleton(StudioContextService::class);

        $this->registerGenerators();
    }

    private function registerGenerators(): void
    {
        $generators = [
            'model' => ModelGenerator::class,
            'migration' => MigrationGenerator::class,
            'factory' => FactoryGenerator::class,
            'seeder' => SeederGenerator::class,
            'action' => ActionGenerator::class,
            'controller' => ControllerGenerator::class,
            'api_controller' => ApiControllerGenerator::class,
            'request' => RequestGenerator::class,
            'route' => RouteGenerator::class,
            'page' => PageGenerator::class,
            'typescript' => TypeScriptGenerator::class,
            'test' => TestGenerator::class,
        ];

        foreach ($generators as $name => $class) {
            $this->app->singleton("architect.generator.{$name}", $class);
            $this->app->tag("architect.generator.{$name}", GeneratorInterface::class);
        }

        $this->app->singleton('architect.generators', function ($app) use ($generators) {
            $instances = [];
            foreach (array_keys($generators) as $name) {
                $instances[$name] = $app->make("architect.generator.{$name}");
            }

            return $instances;
        });
    }

    public function packageBooted(): void
    {
        if (config('architect.stack') === 'auto') {
            config(['architect.stack' => $this->app->make(StackDetector::class)->detect()]);
        }

        if (config('architect.ui.driver') === 'auto') {
            config(['architect.ui.driver' => $this->app->make(UiDriverDetector::class)->detect()]);
        }

        if (! $this->app->environment('production')) {
            $this->registerStudioRoute();
            $this->registerApiRoutes();
            $this->registerAssetRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../stubs' => base_path('stubs/architect'),
            ], 'architect-stubs');
        }
    }

    private function registerStudioRoute(): void
    {
        $prefix = config('architect.ui.route_prefix', 'architect');
        $this->app->booted(function () use ($prefix) {
            $router = $this->app->make('router');
            $router->middleware('web')->get($prefix, ArchitectStudioController::class)->name('architect.studio');
            $router->middleware('web')->post($prefix.'/validate', [ArchitectStudioController::class, 'validate'])->name('architect.studio.validate');
        });
    }

    private function registerApiRoutes(): void
    {
        $prefix = config('architect.ui.route_prefix', 'architect');
        $apiPrefix = $prefix.'/api';
        $this->app->booted(function () use ($apiPrefix) {
            $router = $this->app->make('router');
            $router->middleware('web')->get($apiPrefix.'/context', [ArchitectApiController::class, 'context'])->name('architect.api.context');
            $router->middleware('web')->get($apiPrefix.'/draft', [ArchitectApiController::class, 'getDraft'])->name('architect.api.draft.get');
            $router->middleware('web')->put($apiPrefix.'/draft', [ArchitectApiController::class, 'putDraft'])->name('architect.api.draft.put');
            $router->middleware('web')->post($apiPrefix.'/validate', [ArchitectApiController::class, 'validateDraft'])->name('architect.api.validate');
            $router->middleware('web')->post($apiPrefix.'/plan', [ArchitectApiController::class, 'plan'])->name('architect.api.plan');
            $router->middleware('web')->post($apiPrefix.'/build', [ArchitectApiController::class, 'build'])->name('architect.api.build');
            $router->middleware('web')->post($apiPrefix.'/revert', [ArchitectApiController::class, 'revert'])->name('architect.api.revert');
            $router->middleware('web')->post($apiPrefix.'/draft-from-ai', [ArchitectApiController::class, 'draftFromAi'])->name('architect.api.draft-from-ai');
            $router->middleware('web')->post($apiPrefix.'/simple-generate', [ArchitectApiController::class, 'simpleGenerate'])->name('architect.api.simple-generate');
            $router->middleware('web')->get($apiPrefix.'/starters', [ArchitectApiController::class, 'starters'])->name('architect.api.starters');
            $router->middleware('web')->get($apiPrefix.'/starters/{name}', [ArchitectApiController::class, 'getStarter'])->name('architect.api.starters.get');
            $router->middleware('web')->post($apiPrefix.'/import', [ArchitectApiController::class, 'import'])->name('architect.api.import');
            $router->middleware('web')->get($apiPrefix.'/status', [ArchitectApiController::class, 'status'])->name('architect.api.status');
            $router->middleware('web')->get($apiPrefix.'/explain', [ArchitectApiController::class, 'explain'])->name('architect.api.explain');
            $router->middleware('web')->get($apiPrefix.'/preview', [ArchitectApiController::class, 'preview'])->name('architect.api.preview');
            $router->middleware('web')->post($apiPrefix.'/analyze', [ArchitectApiController::class, 'analyze'])->name('architect.api.analyze');
            $router->middleware('web')->post($apiPrefix.'/wizard/add-model', [ArchitectApiController::class, 'wizardAddModel'])->name('architect.api.wizard.add-model');
            $router->middleware('web')->post($apiPrefix.'/wizard/add-crud-resource', [ArchitectApiController::class, 'wizardAddCrudResource'])->name('architect.api.wizard.add-crud-resource');
            $router->middleware('web')->post($apiPrefix.'/wizard/add-relationship', [ArchitectApiController::class, 'wizardAddRelationship'])->name('architect.api.wizard.add-relationship');
            $router->middleware('web')->post($apiPrefix.'/wizard/add-page', [ArchitectApiController::class, 'wizardAddPage'])->name('architect.api.wizard.add-page');

            // AI-powered endpoints
            $aiPrefix = $apiPrefix.'/ai';
            $router->middleware('web')->post($aiPrefix.'/chat', [ArchitectAIController::class, 'chat'])->name('architect.api.ai.chat');
            $router->middleware('web')->get($aiPrefix.'/chat/suggestions', [ArchitectAIController::class, 'chatSuggestions'])->name('architect.api.ai.chat.suggestions');
            $router->middleware('web')->post($aiPrefix.'/analyze-package', [ArchitectAIController::class, 'analyzePackage'])->name('architect.api.ai.analyze-package');
            $router->middleware('web')->post($aiPrefix.'/suggestions', [ArchitectAIController::class, 'suggestions'])->name('architect.api.ai.suggestions');
            $router->middleware('web')->post($aiPrefix.'/suggest-fields', [ArchitectAIController::class, 'suggestFields'])->name('architect.api.ai.suggest-fields');
            $router->middleware('web')->post($aiPrefix.'/validate', [ArchitectAIController::class, 'validateSchema'])->name('architect.api.ai.validate');
            $router->middleware('web')->post($aiPrefix.'/recommendations', [ArchitectAIController::class, 'recommendations'])->name('architect.api.ai.recommendations');
            $router->middleware('web')->get($aiPrefix.'/conflicts', [ArchitectAIController::class, 'conflicts'])->name('architect.api.ai.conflicts');
            $router->middleware('web')->post($aiPrefix.'/check-compatibility', [ArchitectAIController::class, 'checkCompatibility'])->name('architect.api.ai.check-compatibility');
            $router->middleware('web')->post($aiPrefix.'/generate-code', [ArchitectAIController::class, 'generateCode'])->name('architect.api.ai.generate-code');
            $router->middleware('web')->post($aiPrefix.'/generate-boilerplate', [ArchitectAIController::class, 'generateBoilerplate'])->name('architect.api.ai.generate-boilerplate');
            $router->middleware('web')->post($aiPrefix.'/generate-complete', [ArchitectAIController::class, 'generateComplete'])->name('architect.api.ai.generate-complete');
        });
    }

    private function registerAssetRoutes(): void
    {
        $prefix = config('architect.ui.route_prefix', 'architect');
        $distPath = dirname(__DIR__).'/resources/dist';
        $this->app->booted(function () use ($prefix, $distPath) {
            $router = $this->app->make('router');
            $router->middleware('web')->get($prefix.'/assets/studio.js', function () use ($distPath) {
                $path = $distPath.'/studio.js';

                return file_exists($path)
                    ? response()->file($path, [
                        'Content-Type' => 'application/javascript',
                        'Cache-Control' => 'no-cache, no-store, must-revalidate',
                        'Pragma' => 'no-cache',
                        'Expires' => '0',
                    ])
                    : response('Studio assets not built. Run npm run build in the package resources/js.', 404);
            })->name('architect.assets.studio.js');
            $router->middleware('web')->get($prefix.'/assets/studio.css', function () use ($distPath) {
                $path = $distPath.'/studio.css';

                return file_exists($path)
                    ? response()->file($path, [
                        'Content-Type' => 'text/css',
                        'Cache-Control' => 'no-cache, no-store, must-revalidate',
                        'Pragma' => 'no-cache',
                        'Expires' => '0',
                    ])
                    : response('Studio assets not built. Run npm run build in the package resources/js.', 404);
            })->name('architect.assets.studio.css');
        });
    }
}
