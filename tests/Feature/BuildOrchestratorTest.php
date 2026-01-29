<?php

declare(strict_types=1);

use CodingSunshine\Architect\Services\BuildOrchestrator;

beforeEach(function () {
    $this->base = base_path();
    $this->statePath = $this->base.'/.architect-state-test-'.uniqid().'.json';
    $this->draftPath = $this->base.'/draft-test-'.uniqid().'.yaml';
    config(['architect.state_path' => $this->statePath]);
    config(['architect.draft_path' => $this->draftPath]);
    file_put_contents($this->draftPath, <<<'YAML'
schema_version: "1.0"
models:
  Post:
    title: string:255
    body: longtext
    published_at: timestamp nullable
actions:
  CreatePost:
    model: Post
  UpdatePost:
    model: Post
  DeletePost:
    model: Post
pages:
  Post: {}
YAML
    );
    $this->orchestrator = app(BuildOrchestrator::class);
});

afterEach(function () {
    if (isset($this->draftPath) && file_exists($this->draftPath)) {
        @unlink($this->draftPath);
    }
    if (isset($this->statePath) && file_exists($this->statePath)) {
        @unlink($this->statePath);
    }
});

it('returns failure when draft file does not exist', function () {
    $result = $this->orchestrator->build('/nonexistent/draft.yaml');
    expect($result->success)->toBeFalse()
        ->and($result->errors)->not->toBeEmpty()
        ->and($result->errors[0])->toContain('not found');
});

it('builds and generates files for valid draft', function () {
    $result = $this->orchestrator->build($this->draftPath);
    expect($result->success)->toBeTrue()
        ->and($result->errors)->toBe([])
        ->and($result->generated)->not->toBeEmpty();
});

it('returns noChanges on second build without draft change', function () {
    $this->orchestrator->build($this->draftPath);
    $result = $this->orchestrator->build($this->draftPath);
    expect($result->success)->toBeTrue();
    expect($result->generated)->toBe([]);
});

it('respects only option', function () {
    $result = $this->orchestrator->build($this->draftPath, ['model']);
    expect($result->success)->toBeTrue();
    $paths = array_keys($result->generated);
    expect($paths)->toContain(app_path('Models/Post.php'));
});
