<?php

declare(strict_types=1);

it('lists packages and exits successfully', function () {
    $this->artisan('architect:packages')
        ->assertSuccessful();
});

it('outputs json when requested', function () {
    $this->artisan('architect:packages', ['--json' => true])
        ->assertSuccessful();
});
