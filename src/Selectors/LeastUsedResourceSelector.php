<?php

declare(strict_types=1);

namespace Shado\ResourcePool\Selectors;

use WeakMap;

/**
 * @template ResourceT of object
 * @implements ResourceSelectorInterface<ResourceT>
 */
class LeastUsedResourceSelector implements ResourceSelectorInterface
{
    /**
     * @var WeakMap<object, int>
     */
    private WeakMap $usageCount;

    public function __construct()
    {
        $this->usageCount = new WeakMap();
    }

    /**
     * @param list<ResourceT> $resources
     * @return ResourceT|null
     */
    public function select(iterable $resources): object|null
    {
        $selectedResource = null;
        $minUsage = PHP_INT_MAX;

        foreach ($resources as $resource) {
            if (!isset($this->usageCount[$resource])) {
                $this->usageCount[$resource] = 0;
            }

            if ($this->usageCount[$resource] < $minUsage) {
                $minUsage = $this->usageCount[$resource];
                $selectedResource = $resource;
            }
        }

        if ($selectedResource !== null) {
            $this->usageCount[$selectedResource]++;
        }

        return $selectedResource;
    }
}