<?php

declare(strict_types=1);

use CodingSunshine\Architect\Services\Generators\ModelGenerator;
use CodingSunshine\Architect\Support\Draft;

it('generates model file for draft model', function () {
    $draft = new Draft(models: [
        'Post' => [
            'title' => 'string:255',
            'body' => 'longtext',
            'published_at' => 'timestamp nullable',
        ],
    ]);
    $generator = app(ModelGenerator::class);
    $result = $generator->generate($draft, base_path('draft.yaml'));
    expect($result->generated)->not->toBeEmpty();
    $path = app_path('Models/Post.php');
    expect($result->generated)->toHaveKey($path);
    expect(file_exists($path))->toBeTrue();
    $content = (string) file_get_contents($path);
    expect($content)->toContain('namespace App\\Models')
        ->and($content)->toContain('class Post')
        ->and($content)->toContain('title')
        ->and($content)->toContain('body')
        ->and($content)->toContain('published_at');
});

it('does not support empty models draft', function () {
    $draft = new Draft(models: []);
    $generator = app(ModelGenerator::class);
    expect($generator->supports($draft))->toBeFalse();
});
