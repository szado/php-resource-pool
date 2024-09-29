<?php

declare(strict_types=1);

namespace Shado\ResourcePool;

use Closure;

readonly class FactoryController
{
    public function __construct(
        private Closure $detach,
    ) {}

    /**
     * Remove resource from the pool and prevent its future use.
     */
    public function detach(): void
    {
        ($this->detach)();
    }
}