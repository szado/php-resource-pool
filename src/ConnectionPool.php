<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;
use Szado\React\ConnectionPool\ConnectionSelectors\ConnectionSelectorInterface;
use Szado\React\ConnectionPool\ConnectionSelectors\UsageConnectionSelector;
use function React\Async\await;

class ConnectionPool implements ConnectionPoolInterface
{
    protected \SplObjectStorage $connections;
    protected \SplObjectStorage $awaiting;
    protected ConnectionSelectorInterface $selector;

    /**
     * @param \Closure $connectionFactory Closure which creates connection adapter object with new connection.
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

    /**
     * Get connection from pool.
     * Throws error if no connection is available and cannot create the new one.
     * @throws ConnectionPoolException
     */
    public function get(): ConnectionAdapterInterface
    {
        $connection = $this->selectConnection();

        if (!$connection) {
            if ($this->canMakeNewConnection()) {
                $this->connections->attach($this->makeNewConnection());
                return $this->get();
            }
            return await($this->retryWithDelay());
        }

        return $connection;
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
        return ($this->connectionFactory)();
    }

    protected function retryWithDelay(Deferred $deferred = null): \React\Promise\Promise
    {
        if ($this->retryLimit !== null && $this->retryLimit < 1) {
            throw new ConnectionPoolException('No available connection to use');
        }

        $deferred ??= new Deferred();

        if (!$this->awaiting->contains($deferred)) {
            $this->awaiting->attach($deferred, 0);
        }

        if ($this->awaiting[$deferred] === $this->retryLimit) {
            $this->awaiting->detach($deferred);
            throw new ConnectionPoolException("No available connection to use; $this->retryLimit attempts were made");
        }

        $this->loop->addTimer($this->retryEverySec, function () use ($deferred) {
            $connectionAdapter = $this->selectConnection();

            if ($connectionAdapter) {
                $this->awaiting->detach($deferred);
                $deferred->resolve($connectionAdapter);
                return;
            }

            $this->awaiting[$deferred] = $this->awaiting[$deferred] + 1;
            $this->retryWithDelay($deferred);
        });

        return $deferred->promise();
    }
}
