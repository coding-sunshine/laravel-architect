<?php

declare(strict_types=1);

it('validates existing draft', function () {
    $path = base_path('draft-validate-' . uniqid() . '.yaml');
    config(['architect.draft_path' => $path]);
    file_put_contents($path, "schema_version: \"1.0\"\nmodels:\n  Post: {}");
    try {
        $this->artisan('architect:validate', ['draft' => $path])
            ->assertSuccessful();
    } finally {
        @unlink($path);
    }
});

it('fails when draft file not found', function () {
    $this->artisan('architect:validate', ['draft' => '/nonexistent/draft.yaml'])
        ->assertFailed();
});

it('fails when draft is invalid', function () {
    $path = base_path('draft-invalid-' . uniqid() . '.yaml');
    file_put_contents($path, 'invalid: yaml: [unclosed');
    try {
        $this->artisan('architect:validate', ['draft' => $path])
            ->assertFailed();
    } finally {
        @unlink($path);
    }
});
