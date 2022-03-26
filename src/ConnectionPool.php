<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;
use Szado\React\ConnectionPool\ConnectionSelectors\ConnectionSelectorInterface;
use Szado\React\ConnectionPool\ConnectionSelectors\UsageConnectionSelector;
use function React\Promise\reject;
use function React\Promise\resolve;

class ConnectionPool implements ConnectionPoolInterface
{
    protected \SplObjectStorage $connections;
    protected \SplObjectStorage $awaiting;
    protected ConnectionSelectorInterface $selector;

    /**
     * @param \Closure $connectionFactory A closure that returns a Promise and creates new connection adapter object.
     * @param class-string<ConnectionSelectorInterface> $connectionSelectorClass Connection selector to use on `getConnection()` call.
     * @param int|null $connectionsLimit Maximum number of connections that can be created (null for unlimited).
     * @param int|null $retryLimit How many times try to search for an active connection before rejecting (null for unlimited, 0 for immediately rejection if none at the moment).
     * @param float $retryEverySec Check for available connections every how many seconds (only if $retryLimit is enabled).
     * @param LoopInterface|null $loop
     */
    public function __construct(
        protected \Closure       $connectionFactory,
        string                   $connectionSelectorClass = UsageConnectionSelector::class,
        protected ?int           $connectionsLimit = null,
        protected ?int           $retryLimit = null,
        protected float          $retryEverySec = 0.001, // 1ms
        protected ?LoopInterface $loop = null
    )
    {
        $this->connections = new \SplObjectStorage();
        $this->awaiting = new \SplObjectStorage();
        $this->selector = new $connectionSelectorClass($this->connections);
        $this->loop ??= Loop::get();
    }

    public function get(): PromiseInterface
    {
        $connection = $this->selectConnection();

        if ($connection) {
            return resolve($connection);
        }

        if ($this->canMakeNewConnection()) {
            return $this->makeNewConnection()
                ->then(fn (ConnectionAdapterInterface $adapter) => $this->connections->attach($adapter))
                ->then(fn () => $this->get());
        }

        return $this->retryWithDelay();
    }

    protected function selectConnection(): ?ConnectionAdapterInterface
    {
        return $this->selector->select();
    }

    protected function canMakeNewConnection(): bool
    {
        return $this->connectionsLimit === null || $this->connections->count() < $this->connectionsLimit;
    }

    protected function makeNewConnection(): PromiseInterface
    {
        $promise = ($this->connectionFactory)();
        if ($promise instanceof PromiseInterface) {
            return $promise->then(
                null,
                fn (\Throwable $throwable) => throw new ConnectionPoolException("Error while creating new connection adapter: {$throwable->getMessage()}", previous: $throwable)
            );
        }
        return reject(new ConnectionPoolException('Connection factory closure must return PromiseInterface<ConnectionAdapterInterface>'));
    }

    protected function retryWithDelay(): PromiseInterface
    {
        if ($this->retryLimit !== null && $this->retryLimit < 1) {
            return reject(new ConnectionPoolException('No available connection to use'));
        }

        $deferred = new Deferred();

        if (!$this->awaiting->contains($deferred)) {
            $this->awaiting->attach($deferred, 0);
        }

        $this->loop->addPeriodicTimer($this->retryEverySec, function (TimerInterface $timer) use ($deferred) {
            $connectionAdapter = $this->selectConnection();

            if ($connectionAdapter) {
                $this->awaiting->detach($deferred);
                $this->loop->cancelTimer($timer);
                $deferred->resolve($connectionAdapter);
                return;
            }

            $this->awaiting[$deferred] += 1;
            if ($this->awaiting[$deferred] === $this->retryLimit) {
                $this->awaiting->detach($deferred);
                $this->loop->cancelTimer($timer);
                $deferred->reject(new ConnectionPoolException("No available connection to use; $this->retryLimit retries were made and reached the limit"));
            }
        });

        return $deferred->promise();
    }
}
