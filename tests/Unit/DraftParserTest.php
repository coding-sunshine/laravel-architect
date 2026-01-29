<?php

declare(strict_types=1);

use CodingSunshine\Architect\Services\DraftParser;
use CodingSunshine\Architect\Support\Draft;

beforeEach(function () {
    $this->parser = app(DraftParser::class);
});

it('parses valid YAML into Draft', function () {
    $path = sys_get_temp_dir().'/draft-test-'.uniqid().'.yaml';
    file_put_contents($path, <<<'YAML'
schema_version: "1.0"
models:
  Post:
    title: string:255
    content: longtext
actions:
  CreatePost:
    model: Post
pages:
  Post: {}
YAML
    );
    try {
        $draft = $this->parser->parse($path);
        expect($draft)->toBeInstanceOf(Draft::class)
            ->and($draft->modelNames())->toBe(['Post'])
            ->and($draft->getModel('Post'))->toHaveKey('title')
            ->and($draft->actions)->toHaveKey('CreatePost')
            ->and($draft->pages)->toHaveKey('Post')
            ->and($draft->schemaVersion)->toBe('1.0');
    } finally {
        @unlink($path);
    }
});

it('throws when file does not exist', function () {
    $this->parser->parse('/nonexistent/draft.yaml');
})->throws(InvalidArgumentException::class, 'Draft file not found');

it('throws when YAML is invalid', function () {
    $path = sys_get_temp_dir().'/draft-bad-'.uniqid().'.yaml';
    file_put_contents($path, 'invalid: yaml: [unclosed');
    try {
        $this->parser->parse($path);
    } finally {
        @unlink($path);
    }
})->throws(InvalidArgumentException::class);
