<?php

declare(strict_types=1);

namespace CodingSunshine\Architect;

use CodingSunshine\Architect\Console\Commands\BuildCommand;
use CodingSunshine\Architect\Console\Commands\DraftCommand;
use CodingSunshine\Architect\Console\Commands\ImportCommand;
use CodingSunshine\Architect\Console\Commands\PlanCommand;
use CodingSunshine\Architect\Console\Commands\StatusCommand;
use CodingSunshine\Architect\Console\Commands\ValidateCommand;
use CodingSunshine\Architect\Contracts\GeneratorInterface;
use CodingSunshine\Architect\Services\BuildOrchestrator;
use CodingSunshine\Architect\Services\ChangeDetector;
use CodingSunshine\Architect\Services\DraftGenerator;
use CodingSunshine\Architect\Services\DraftParser;
use CodingSunshine\Architect\Services\Generators\ActionGenerator;
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
use CodingSunshine\Architect\Services\StateManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ArchitectServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('architect')
            ->hasConfigFile()
            ->hasCommands([
                DraftCommand::class,
                ValidateCommand::class,
                PlanCommand::class,
                BuildCommand::class,
                StatusCommand::class,
                ImportCommand::class,
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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../stubs' => base_path('stubs/architect'),
            ], 'architect-stubs');
        }
    }
}
