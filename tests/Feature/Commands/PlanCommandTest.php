<?php

declare(strict_types=1);

it('shows plan for valid draft', function () {
    $path = base_path('draft-plan-'.uniqid().'.yaml');
    config(['architect.draft_path' => $path]);
    file_put_contents($path, "schema_version: \"1.0\"\nmodels:\n  Post: {}\nactions:\n  CreatePost: {}\npages:\n  Post: {}");
    try {
        $this->artisan('architect:plan', ['draft' => $path])
            ->assertSuccessful()
            ->expectsTable(['Component', 'Count'], [['Models', 1], ['Actions', 1], ['Pages', 1]]);
    } finally {
        @unlink($path);
    }
});

it('fails when draft not found', function () {
    $this->artisan('architect:plan', ['draft' => '/nonexistent/draft.yaml'])
        ->assertFailed();
});
