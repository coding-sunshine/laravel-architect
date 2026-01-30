<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Facades\File;

final class StateManager
{
    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $path = config('architect.state_path', base_path('.architect-state.json'));

        if (! File::exists($path)) {
            return $this->defaultState();
        }

        try {
            $content = File::get($path);
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : $this->defaultState();
        } catch (\JsonException) {
            return $this->defaultState();
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function save(array $state): void
    {
        $path = config('architect.state_path', base_path('.architect-state.json'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function getDraftHash(string $draftPath): ?string
    {
        $state = $this->load();
        $drafts = $state['drafts'] ?? [];

        return $drafts[$draftPath]['hash'] ?? null;
    }

    /**
     * Returns the path of a generated migration that creates the given table, if any.
     */
    public function getGeneratedPathForTable(string $table): ?string
    {
        $state = $this->load();
        $generated = $state['generated'] ?? [];

        foreach ($generated as $path => $meta) {
            if (is_array($meta) && ($meta['table'] ?? null) === $table) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $generated
     */
    public function update(string $draftPath, string $draftHash, array $generated): void
    {
        $state = $this->load();
        $state['version'] = '1.0.0';
        $state['lastRun'] = now()->toIso8601String();
        $state['drafts'] = $state['drafts'] ?? [];
        $state['drafts'][$draftPath] = [
            'hash' => $draftHash,
            'lastBuilt' => now()->toIso8601String(),
        ];
        $state['generated'] = array_merge($state['generated'] ?? [], $generated);
        $this->save($state);
    }

    /**
     * Save backup of file contents before overwrite (for revert last build).
     *
     * @param  array<string, string>  $backup  path => content
     */
    public function saveLastBuildBackup(array $backup): void
    {
        $state = $this->load();
        $state['last_build_backup'] = $backup;
        $this->save($state);
    }

    /**
     * @return array<string, string> path => content
     */
    public function getLastBuildBackup(): array
    {
        $state = $this->load();

        return $state['last_build_backup'] ?? [];
    }

    public function clearLastBuildBackup(): void
    {
        $state = $this->load();
        unset($state['last_build_backup']);
        $this->save($state);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultState(): array
    {
        return [
            'version' => '1.0.0',
            'lastRun' => null,
            'drafts' => [],
            'generated' => [],
        ];
    }
}
