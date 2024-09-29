<?php

declare(strict_types=1);

namespace Shado\Tests\ResourcePool\Components;

use Shado\ResourcePool\ResourcePool;

class PoolFactory
{
    public static function createPoolWithLimit(int $limit): ResourcePool
    {
        return new ResourcePool(fn() => new IdentifiableResource(), $limit);
    }
}