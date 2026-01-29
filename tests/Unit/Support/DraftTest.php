<?php

declare(strict_types=1);

use CodingSunshine\Architect\Support\Draft;

it('returns model names', function () {
    $draft = new Draft(models: ['Post' => [], 'Comment' => []]);
    expect($draft->modelNames())->toBe(['Post', 'Comment']);
});

it('returns single model definition', function () {
    $draft = new Draft(models: ['Post' => ['title' => 'string']]);
    expect($draft->getModel('Post'))->toBe(['title' => 'string'])
        ->and($draft->getModel('Missing'))->toBeNull();
});

it('defaults to empty arrays and schema 1.0', function () {
    $draft = new Draft();
    expect($draft->models)->toBe([])
        ->and($draft->actions)->toBe([])
        ->and($draft->pages)->toBe([])
        ->and($draft->routes)->toBe([])
        ->and($draft->schemaVersion)->toBe('1.0');
});
