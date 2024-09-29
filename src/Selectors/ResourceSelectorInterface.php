<?php

declare(strict_types=1);

namespace Shado\ResourcePool\Selectors;

/**
 * @template ResourceT of object
 */
interface ResourceSelectorInterface
{
    /**
     * @param list<ResourceT> $resources
     * @return ResourceT | null
     */
    public function select(iterable $resources): object | null;
}