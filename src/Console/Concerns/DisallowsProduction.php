<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Console\Concerns;

trait DisallowsProduction
{
    /**
     * Abort if running in production. Call at the start of handle().
     * Returns self::FAILURE if in production, so the command should return the result.
     */
    protected function disallowProduction(): ?int
    {
        if (! app()->environment('production')) {
            return null;
        }

        $this->error('Architect is for local development only. Do not run in production.');

        return self::FAILURE;
    }
}
