<?php

declare(strict_types=1);

use CodingSunshine\Architect\Schema\SchemaValidator;

beforeEach(function () {
    $this->validator = new SchemaValidator;
});

it('returns no errors for valid draft with models', function () {
    $data = [
        'schema_version' => '1.0',
        'models' => [
            'Post' => [
                'title' => 'string:255',
                'content' => 'longtext',
            ],
        ],
    ];
    expect($this->validator->validate($data))->toBe([]);
});

it('returns no errors for valid draft with actions only', function () {
    $data = [
        'schema_version' => '1.0',
        'actions' => [
            'CreatePost' => ['model' => 'Post'],
        ],
    ];
    expect($this->validator->validate($data))->toBe([]);
});

it('returns no errors for valid draft with pages only', function () {
    $data = [
        'schema_version' => '1.0',
        'pages' => ['Post' => []],
    ];
    expect($this->validator->validate($data))->toBe([]);
});

it('returns error when models actions and pages are all empty', function () {
    $data = ['schema_version' => '1.0'];
    $errors = $this->validator->validate($data);
    expect($errors)->not->toBe([])
        ->and($errors[0])->toContain('models')
        ->and($errors[0])->toContain('actions')
        ->and($errors[0])->toContain('pages');
});
