<?php

declare(strict_types=1);

use CodingSunshine\Architect\Services\ChangeDetector;
use CodingSunshine\Architect\Services\StateManager;

beforeEach(function () {
    $this->statePath = sys_get_temp_dir() . '/architect-change-' . uniqid() . '.json';
    config(['architect.state_path' => $this->statePath]);
    $this->state = app(StateManager::class);
    $this->detector = app(ChangeDetector::class);
});

afterEach(function () {
    if (isset($this->statePath) && file_exists($this->statePath)) {
        @unlink($this->statePath);
    }
});

it('reports draft changed when no previous hash', function () {
    $path = sys_get_temp_dir() . '/draft-' . uniqid() . '.yaml';
    file_put_contents($path, 'models: {}');
    $hash = ChangeDetector::computeDraftHash($path);
    expect($this->detector->hasDraftChanged($path, $hash))->toBeTrue();
    @unlink($path);
});

it('reports draft unchanged when hash matches', function () {
    $path = 'draft.yaml';
    $hash = 'abc123';
    $this->state->update($path, $hash, []);
    expect($this->detector->hasDraftChanged($path, $hash))->toBeFalse();
});

it('reports draft changed when hash differs', function () {
    $path = 'draft.yaml';
    $this->state->update($path, 'old-hash', []);
    expect($this->detector->hasDraftChanged($path, 'new-hash'))->toBeTrue();
});

it('computes same hash for same content', function () {
    $path = sys_get_temp_dir() . '/draft-hash-' . uniqid() . '.yaml';
    $content = "schema_version: \"1.0\"\nmodels:\n  Post: {}";
    file_put_contents($path, $content);
    $h1 = ChangeDetector::computeDraftHash($path);
    $h2 = ChangeDetector::computeDraftHash($path);
    expect($h1)->toBe($h2)->and(strlen($h1))->toBe(64);
    @unlink($path);
});
