<?php

declare(strict_types=1);

use CodingSunshine\Architect\Services\StateManager;

beforeEach(function () {
    $this->statePath = sys_get_temp_dir() . '/architect-state-test-' . uniqid() . '.json';
    config(['architect.state_path' => $this->statePath]);
    $this->state = app(StateManager::class);
});

afterEach(function () {
    if (isset($this->statePath) && file_exists($this->statePath)) {
        @unlink($this->statePath);
    }
});

it('loads default state when file does not exist', function () {
    $data = $this->state->load();
    expect($data)->toHaveKeys(['version', 'lastRun', 'drafts', 'generated'])
        ->and($data['generated'])->toBe([])
        ->and($data['drafts'])->toBe([]);
});

it('saves and loads state', function () {
    $state = [
        'version' => '1.0.0',
        'lastRun' => now()->toIso8601String(),
        'drafts' => ['draft.yaml' => ['hash' => 'abc123']],
        'generated' => ['/path/to/file.php' => ['hash' => 'def', 'ownership' => 'regenerate']],
    ];
    $this->state->save($state);
    $loaded = $this->state->load();
    expect($loaded['drafts']['draft.yaml']['hash'])->toBe('abc123')
        ->and($loaded['generated'])->toHaveKey('/path/to/file.php');
});

it('returns draft hash for known draft path', function () {
    $this->state->update('draft.yaml', 'hash123', []);
    expect($this->state->getDraftHash('draft.yaml'))->toBe('hash123')
        ->and($this->state->getDraftHash('other.yaml'))->toBeNull();
});

it('returns generated path for table when tracked', function () {
    $this->state->update('draft.yaml', 'h', [
        '/db/migrations/2024_01_01_000000_create_posts_table.php' => [
            'path' => '/db/migrations/2024_01_01_000000_create_posts_table.php',
            'hash' => 'x',
            'ownership' => 'regenerate',
            'table' => 'posts',
        ],
    ]);
    expect($this->state->getGeneratedPathForTable('posts'))
        ->toBe('/db/migrations/2024_01_01_000000_create_posts_table.php')
        ->and($this->state->getGeneratedPathForTable('users'))->toBeNull();
});
