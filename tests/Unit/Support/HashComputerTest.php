<?php

declare(strict_types=1);

use CodingSunshine\Architect\Support\HashComputer;

it('computes sha256 hash of string', function () {
    $hash = HashComputer::compute('hello');
    expect($hash)->toBe(hash('sha256', 'hello'))
        ->and(strlen($hash))->toBe(64);
});

it('computes same hash for same content', function () {
    expect(HashComputer::compute('x'))->toBe(HashComputer::compute('x'));
});

it('computes different hash for different content', function () {
    expect(HashComputer::compute('a'))->not->toBe(HashComputer::compute('b'));
});

it('computes array hash via json', function () {
    $arr = ['a' => 1, 'b' => 2];
    $h = HashComputer::computeArray($arr);
    expect($h)->toBe(hash('sha256', (string) json_encode($arr)));
});
