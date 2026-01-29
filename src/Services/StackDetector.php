<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Facades\File;

final class StackDetector
{
    private const STACK_INERTIA_REACT = 'inertia-react';

    private const STACK_INERTIA_VUE = 'inertia-vue';

    private const STACK_LIVEWIRE = 'livewire';

    private const STACK_VOLT = 'volt';

    private const STACK_BLADE = 'blade';

    public function detect(): string
    {
        if ($this->hasVolt()) {
            return self::STACK_VOLT;
        }

        if ($this->hasLivewire()) {
            return self::STACK_LIVEWIRE;
        }

        if ($this->hasInertiaVue()) {
            return self::STACK_INERTIA_VUE;
        }

        if ($this->hasInertiaReact()) {
            return self::STACK_INERTIA_REACT;
        }

        return self::STACK_BLADE;
    }

    private function hasInertiaReact(): bool
    {
        $composerPath = base_path('composer.json');
        if (! File::exists($composerPath)) {
            return false;
        }

        $content = File::get($composerPath);
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return false;
        }

        $require = array_merge(
            $data['require'] ?? [],
            $data['require-dev'] ?? []
        );

        $hasInertia = isset($require['inertiajs/inertia-laravel'])
            || isset($require['@inertiajs/react'])
            || isset($require['inertiajs/inertia-laravel']);

        if (! $hasInertia) {
            return false;
        }

        $pagesPath = resource_path('js/pages');
        if (! File::isDirectory($pagesPath)) {
            return true;
        }

        $files = File::glob($pagesPath . '/**/*.{tsx,jsx}', GLOB_BRACE);

        return is_array($files) && $files !== [];
    }

    private function hasInertiaVue(): bool
    {
        $composerPath = base_path('composer.json');
        if (! File::exists($composerPath)) {
            return false;
        }

        $content = File::get($composerPath);
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return false;
        }

        $require = array_merge(
            $data['require'] ?? [],
            $data['require-dev'] ?? []
        );

        if (! isset($require['@inertiajs/vue3']) && ! isset($require['inertiajs/inertia-laravel'])) {
            return false;
        }

        $pagesPath = resource_path('js/pages');
        if (! File::isDirectory($pagesPath)) {
            return false;
        }

        $files = File::glob($pagesPath . '/**/*.vue');

        return is_array($files) && $files !== [];
    }

    private function hasLivewire(): bool
    {
        $composerPath = base_path('composer.json');
        if (! File::exists($composerPath)) {
            return false;
        }

        $content = File::get($composerPath);
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return false;
        }

        $require = array_merge(
            $data['require'] ?? [],
            $data['require-dev'] ?? []
        );

        return isset($require['livewire/livewire']);
    }

    private function hasVolt(): bool
    {
        $composerPath = base_path('composer.json');
        if (! File::exists($composerPath)) {
            return false;
        }

        $content = File::get($composerPath);
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return false;
        }

        $require = array_merge(
            $data['require'] ?? [],
            $data['require-dev'] ?? []
        );

        return isset($require['livewire/volt']);
    }

    public static function supportedStacks(): array
    {
        return [
            self::STACK_INERTIA_REACT,
            self::STACK_INERTIA_VUE,
            self::STACK_LIVEWIRE,
            self::STACK_VOLT,
            self::STACK_BLADE,
        ];
    }
}
