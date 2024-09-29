<?php

declare(strict_types=1);

namespace Shado\ResourcePool;

/**
 * @template ResourceT of object
 */
interface ResourcePoolInterface
{
    /**
     * Borrow a resource from the pool.
     * @return ResourceT
     */
    public function borrow(): object;

    /**
     * Return the resource back to the pool.
     * @param ResourceT $resource
     */
    public function return(object $resource): void;
}