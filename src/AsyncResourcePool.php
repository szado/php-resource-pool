<?php

declare(strict_types=1);

namespace Shado\ResourcePool;

use Exception;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Shado\ResourcePool\Exceptions\ResourceSelectingException;
use Throwable;
use function React\Async\await;

/**
 * Asynchronous wrapper for resource pool with retrying support.
 * @template ResourceT of object
 */
class AsyncResourcePool implements ResourcePoolInterface
{
    /**
     * @param ResourcePoolInterface $resourcePool The resource pool instance to use under the hood.
     * @param ?float $retryingTimeout The timeout in seconds for retrying to obtain a free resource: set to 0 for unlimited time, or null to disable retrying.
     */
    public function __construct(
        private readonly ResourcePoolInterface $resourcePool,
        private readonly ?float $retryingTimeout = 5,
    ) {}

    /**
     * Borrow a resource from the pool.
     * @throws Throwable
     * @return ResourceT
     */
    public function borrow(): object
    {
        return await($this->borrowAsync());
    }

    /**
     * Return the resource back to the pool.
     * @param ResourceT $resource
     */
    public function return(object $resource): void
    {
        $this->resourcePool->return($resource);
    }

    /**
     * @return PromiseInterface<ResourceT>
     */
    public function borrowAsync(): PromiseInterface
    {
        $deferred = new Deferred();
        $startTime = microtime(true);

        $makeTry = function () use (&$makeTry, $startTime, $deferred) {
            try {
                $deferred->resolve($this->resourcePool->borrow());
                return;
            } catch (Exception $exception) {

            }

            $timeout = $this->retryingTimeout;

            if ($timeout === 0 || $timeout !== null && (microtime(true) - $startTime) < $timeout) {
                Loop::futureTick($makeTry);
                return;
            }

            $deferred->reject(new ResourceSelectingException(
                "Cannot get free resource to borrow in given timeout",
                previous: $exception
            ));
        };

        $makeTry();

        return $deferred->promise();
    }
}