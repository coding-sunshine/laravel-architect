<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Support;

final class HashComputer
{
    public static function compute(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function computeArray(array $data): string
    {
        return hash('sha256', (string) json_encode($data, JSON_THROW_ON_ERROR));
    }
}
