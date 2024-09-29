<?php

declare(strict_types=1);

namespace Shado\Tests\ResourcePool\Components;

class IdentifiableResource
{
    private static int $instanceCount = 0;

    public readonly int $id;

    public function __construct()
    {
        $this->id = ++self::$instanceCount;
    }
}