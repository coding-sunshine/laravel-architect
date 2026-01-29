<?php

declare(strict_types=1);

use CodingSunshine\Architect\Services\DraftGenerator;

beforeEach(function () {
    config(['architect.ai.enabled' => false]);
    $this->generator = app(DraftGenerator::class);
});

it('returns stub YAML when AI disabled', function () {
    config(['architect.ai.enabled' => false]);
    $yaml = $this->generator->generate('blog posts');
    expect($yaml)->toContain('schema_version')
        ->and($yaml)->toContain('models')
        ->and($yaml)->toContain('Blog')
        ->and($yaml)->toContain('CreateBlog')
        ->and($yaml)->toContain('pages');
});

it('infers model name from description', function () {
    $yaml = $this->generator->generate('Product catalog');
    expect($yaml)->toContain('Product')
        ->and($yaml)->toContain('CreateProduct');
});

it('includes extend context when existing draft path given', function () {
    $tmp = sys_get_temp_dir() . '/existing-' . uniqid() . '.yaml';
    file_put_contents($tmp, "schema_version: \"1.0\"\nmodels:\n  User: {}");
    try {
        $yaml = $this->generator->generate('add posts', $tmp);
        expect($yaml)->toContain('schema_version')->and($yaml)->toContain('models');
    } finally {
        @unlink($tmp);
    }
});
