<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Facades\File;

final class PackageDiscovery
{
    /**
     * Returns list of installed Composer packages (name => version).
     *
     * @return array<string, string>
     */
    public function installed(): array
    {
        $base = base_path('vendor/composer');

        if (File::exists($base.'/installed.json')) {
            return $this->fromInstalledJson($base.'/installed.json');
        }

        if (File::exists($base.'/installed.php')) {
            return $this->fromInstalledPhp($base.'/installed.php');
        }

        if (File::exists(base_path('composer.lock'))) {
            return $this->fromLock(base_path('composer.lock'));
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function fromInstalledPhp(string $path): array
    {
        $data = require $path;

        if (! is_array($data)) {
            return [];
        }

        $packages = $data['versions'] ?? $data;

        if (isset($data['versions']) && is_array($data['versions'])) {
            $packages = $data['versions'];
        }

        $result = [];

        foreach ($packages as $name => $meta) {
            if (is_string($meta)) {
                continue;
            }

            if (! is_array($meta)) {
                continue;
            }

            $version = $meta['version'] ?? $meta['version_normalized'] ?? 'dev';

            if (str_contains($version, '@')) {
                $version = explode('@', $version)[0];
            }

            $result[$name] = $version;
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function fromInstalledJson(string $path): array
    {
        $content = File::get($path);
        $data = json_decode($content, true);

        if (! is_array($data)) {
            return [];
        }

        $result = [];
        $list = $data['packages'] ?? $data['installed'] ?? [];

        if (! is_array($list)) {
            return [];
        }

        foreach ($list as $pkg) {
            if (! is_array($pkg) || ! isset($pkg['name']) || ! is_string($pkg['name'])) {
                continue;
            }

            $version = $pkg['version'] ?? $pkg['version_normalized'] ?? 'dev';

            if (str_contains((string) $version, '@')) {
                $version = explode('@', (string) $version)[0];
            }

            $result[$pkg['name']] = (string) $version;
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function fromLock(string $path): array
    {
        $content = File::get($path);
        $data = json_decode($content, true);

        if (! is_array($data)) {
            return [];
        }

        $result = [];

        foreach (['packages', 'packages-dev'] as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key])) {
                continue;
            }

            foreach ($data[$key] as $pkg) {
                if (! isset($pkg['name']) || ! is_string($pkg['name'])) {
                    continue;
                }

                $version = $pkg['version'] ?? $pkg['version_normalized'] ?? 'dev';

                if (str_contains((string) $version, '@')) {
                    $version = explode('@', (string) $version)[0];
                }

                $result[$pkg['name']] = (string) $version;
            }
        }

        return $result;
    }
}
