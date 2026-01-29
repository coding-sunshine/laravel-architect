<?php

declare(strict_types=1);

beforeEach(function () {
    $this->statePath = base_path('.architect-state-buildcmd-' . uniqid() . '.json');
    $this->draftPath = base_path('draft-buildcmd-' . uniqid() . '.yaml');
    config(['architect.state_path' => $this->statePath]);
    config(['architect.draft_path' => $this->draftPath]);
    file_put_contents($this->draftPath, <<<'YAML'
schema_version: "1.0"
models:
  Item:
    name: string
actions:
  CreateItem:
    model: Item
pages:
  Item: {}
YAML
    );
});

afterEach(function () {
    if (isset($this->draftPath) && file_exists($this->draftPath)) {
        @unlink($this->draftPath);
    }
    if (isset($this->statePath) && file_exists($this->statePath)) {
        @unlink($this->statePath);
    }
});

it('builds successfully with draft argument', function () {
    $this->artisan('architect:build', ['draft' => $this->draftPath])
        ->assertSuccessful();
});

it('fails when draft not found', function () {
    $this->artisan('architect:build', ['draft' => '/nonexistent/draft.yaml'])
        ->assertFailed();
});

it('accepts only option', function () {
    $this->artisan('architect:build', [
        'draft' => $this->draftPath,
        '--only' => ['model'],
    ])->assertSuccessful();
});
