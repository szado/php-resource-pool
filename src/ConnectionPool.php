<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;
use Szado\React\ConnectionPool\ConnectionSelectors\ConnectionSelectorInterface;
use Szado\React\ConnectionPool\ConnectionSelectors\UsageConnectionSelector;
use function React\Async\await;
use function React\Promise\reject;
use function React\Promise\resolve;

class ConnectionPool implements ConnectionPoolInterface
{
    protected \SplObjectStorage $connections;
    protected \SplObjectStorage $awaiting;
    protected ConnectionSelectorInterface $selector;

    /**
     * @param \Closure $connectionFactory Closure which creates connection object and returns promise.
     * @param class-string<ConnectionSelectorInterface> $connectionSelectorClass Connection selector to use on `getConnection()` call.
     * @param int|null $connectionsLimit Maximum number of connections that can be created (null for unlimited).
     * @param float|null $retryLimit How many times try to search for an active connection before rejecting (null for unlimited, 0 for immediately rejection if none at the moment).
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

    /**
     * Get connection from pool.
     * Reject if no connection is available and cannot create the new one.
     * @return PromiseInterface<ConnectionAdapterInterface, ConnectionPoolException>
     */
    public function get(): PromiseInterface
    {
        $connection = $this->selectConnection();

        if (!$connection) {
            if ($this->canMakeNewConnection()) {
                try {
                    $this->connections->attach($this->makeNewConnection());
                } catch (\Throwable $exception) {
                    return reject($exception);
                }
            } else {
                $deferred = new Deferred();
                $this->loop->futureTick(fn () => $this->retry($deferred));
                return $deferred->promise();
            }
        }

        return resolve($connection);
    }

    protected function selectConnection(): ?ConnectionAdapterInterface
    {
        return $this->selector->select();
    }

    protected function canMakeNewConnection(): bool
    {
        return $this->connectionsLimit === null || $this->connections->count() < $this->connectionsLimit;
    }

    protected function makeNewConnection(): ConnectionAdapterInterface
    {
        return await(($this->connectionFactory)());
    }

    protected function retry(Deferred $deferred): void
    {
        if ($this->retryLimit !== null && $this->retryLimit < 1) {
            $deferred->reject(new ConnectionPoolException('No available connection to use'));
            return;
        }

        if (!$this->awaiting->contains($deferred)) {
            $this->awaiting->attach($deferred, 0);
        }

        if ($this->awaiting[$deferred] === $this->retryLimit) {
            $this->awaiting->detach($deferred);
            $deferred->reject(new ConnectionPoolException("No available connection to use; $this->retryLimit attempts were made"));
            return;
        }

        $this->loop->addTimer($this->retryEverySec, function () use ($deferred) {
            $connectionAdapter = $this->selectConnection();

            if ($connectionAdapter) {
                $this->awaiting->detach($deferred);
                $deferred->resolve($connectionAdapter);
                return;
            }

            $this->awaiting[$deferred] = $this->awaiting[$deferred] + 1;
            $this->retry($deferred);
        });
    }
}