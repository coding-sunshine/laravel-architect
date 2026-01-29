<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Facades\File;

final class UiDriverDetector
{
    private const DRIVER_INERTIA_REACT = 'inertia-react';

    private const DRIVER_LIVEWIRE_FLUX = 'livewire-flux';

    private const DRIVER_LIVEWIRE_FLUX_PRO = 'livewire-flux-pro';

    private const DRIVER_BLADE = 'blade';

    public function detect(): string
    {
        if ($this->hasInertiaReactAndShadcn()) {
            return self::DRIVER_INERTIA_REACT;
        }

        if ($this->hasFluxPro()) {
            return self::DRIVER_LIVEWIRE_FLUX_PRO;
        }

        if ($this->hasLivewireAndFlux()) {
            return self::DRIVER_LIVEWIRE_FLUX;
        }

        return self::DRIVER_BLADE;
    }

    public static function supportedDrivers(): array
    {
        return [
            self::DRIVER_INERTIA_REACT,
            self::DRIVER_LIVEWIRE_FLUX,
            self::DRIVER_LIVEWIRE_FLUX_PRO,
            self::DRIVER_BLADE,
        ];
    }

    private function hasInertiaReactAndShadcn(): bool
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
            || isset($require['@inertiajs/react']);

        if (! $hasInertia) {
            return false;
        }

        if (File::exists(base_path('components.json'))) {
            return true;
        }

        $uiPath = resource_path('js/components/ui');
        if (File::isDirectory($uiPath)) {
            $hasButton = File::exists($uiPath.'/button.tsx') || File::exists($uiPath.'/button.jsx');
            if ($hasButton) {
                return true;
            }
        }

        return false;
    }

    private function hasLivewireAndFlux(): bool
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

        return isset($require['livewire/livewire'])
            && (isset($require['livewire/flux']) || isset($require['livewire/flux-ui']));
    }

    private function hasFluxPro(): bool
    {
        if (! $this->hasLivewireAndFlux()) {
            return false;
        }

        if (env('FLUX_PRO_LICENSE_KEY')) {
            return true;
        }

        if (config('flux.pro_license_key')) {
            return true;
        }

        return false;
    }
}
