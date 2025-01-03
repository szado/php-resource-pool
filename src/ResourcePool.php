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
    private int $pendingCount = 0;

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
     *     pending_count: int,
     *     total_count: int,
     * }
     */
    public function debug(): array
    {
        return [
            'available_count' => $this->available->count(),
            'borrowed_count' => $this->borrowed->count(),
            'pending_count' => $this->pendingCount,
            'total_count' => $this->countTotal(),
        ];
    }

    private function tryCreateResource(): void
    {
        $noLimit = $this->limit === 0;

        if ($noLimit || $this->countTotal() < $this->limit) {
            $resource = null;
            $controller = new FactoryController(function () use (&$resource) {
                if ($resource) {
                    $this->borrowed->detach($resource);
                    $this->available->detach($resource);
                }
            });

            try {
                $this->pendingCount++;
                $resource = ($this->factory)($controller);
            } finally {
                $this->pendingCount--;
            }

            $this->available->attach($resource);
        }
    }

    private function countTotal(): int
    {
        return $this->available->count() + $this->borrowed->count() + $this->pendingCount;
    }
}