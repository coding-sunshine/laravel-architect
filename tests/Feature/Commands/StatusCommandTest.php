<?php

declare(strict_types=1);

it('shows status and exits successfully', function () {
    $this->artisan('architect:status')
        ->assertSuccessful();
});

it('shows table when state has generated files', function () {
    $statePath = base_path('.architect-state-status-'.uniqid().'.json');
    config(['architect.state_path' => $statePath]);
    $state = app(\CodingSunshine\Architect\Services\StateManager::class);
    $state->update('draft.yaml', 'hash1', [
        '/some/path.php' => ['path' => '/some/path.php', 'hash' => 'h', 'ownership' => 'regenerate'],
    ]);
    try {
        $this->artisan('architect:status')
            ->assertSuccessful()
            ->expectsTable(['Path', 'Hash', 'Ownership'], [['/some/path.php', 'h', 'regenerate']]);
    } finally {
        @unlink($statePath);
    }
});
