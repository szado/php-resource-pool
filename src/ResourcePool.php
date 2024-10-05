<?php

declare(strict_types=1);

namespace Shado\ResourcePool;

use Closure;
use Shado\ResourcePool\Exceptions\ResourceSelectingException;
use Shado\ResourcePool\Selectors\LeastUsedResourceSelector;
use Shado\ResourcePool\Selectors\ResourceSelectorInterface;
use SplObjectStorage;

/**
 * Basic implementation of resource pool.
 * @template ResourceT of object
 */
class ResourcePool implements ResourcePoolInterface
{
    private SplObjectStorage $available;
    private SplObjectStorage $borrowed;

    /**
     * @param Closure(FactoryController):ResourceT $factory Resource factory closure.
     * @param int $limit Limit of coexisting resources, 0 for unlimited.
     * @param ResourceSelectorInterface $selector Resource selector to use.
     */
    public function __construct(
        private readonly Closure $factory,
        private readonly int $limit,
        private readonly ResourceSelectorInterface $selector = new LeastUsedResourceSelector(),
    ) {
        $this->available = new SplObjectStorage();
        $this->borrowed = new SplObjectStorage();
    }

    /**
     * Borrow a resource from the pool.
     * @return ResourceT
     * @throws ResourceSelectingException
     */
    public function borrow(): object
    {
        if (!$this->available->count()) {
            $this->tryCreateResource();
        }

        $resource = $this->selector->select($this->available);

        if (!$resource) {
            throw new ResourceSelectingException('No available resource to borrow');
        }

        if (!$this->available->contains($resource)) {
            throw new ResourceSelectingException('Resource selected by selector is not available or unknown');
        }

        $this->available->detach($resource);
        $this->borrowed->attach($resource);

        return $resource;
    }

    /**
     * Return the resource back to the pool.
     * @param ResourceT $resource
     */
    public function return(object $resource): void
    {
        if (!$this->borrowed->contains($resource)) {
            // Ignore the fact that the resource doesn't exist
            // it may have been detached by the factory in the meantime.
            return;
        }

        $this->borrowed->detach($resource);
        $this->available->attach($resource);
    }

    /**
     * Get debug data about the pool.
     * @return array{
     *     available_count: int,
     *     borrowed_count: int,
     *     all_count: int,
     * }
     */
    public function debug(): array
    {
        $available = $this->available->count();
        $borrowed = $this->borrowed->count();
        return ['available_count' => $available, 'borrowed_count' => $borrowed, 'all_count' => $available + $borrowed];
    }

    private function tryCreateResource(): void
    {
        $allCount = $this->available->count() + $this->borrowed->count();
        $noLimit = $this->limit === 0;

        if ($noLimit || $allCount < $this->limit) {
            $resource = null;
            $controller = new FactoryController(function () use (&$resource) {
                if ($resource) {
                    $this->borrowed->detach($resource);
                    $this->available->detach($resource);
                }
            });
            $resource = ($this->factory)($controller);
            $this->available->attach($resource);
        }
    }
}