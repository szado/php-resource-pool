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
     * @param class-string<ConnectionAdapterInterface> $connectionAdapterClass Class to use as adapter for single connection created by factory.
     * @param class-string<ConnectionSelectorInterface> $connectionSelectorClass Connection selector to use on `getConnection()` call.
     * @param int|null $connectionsLimit Maximum number of connections that can be created (null for unlimited).
     * @param float $retryLimit How many times try to search for an active connection before rejecting (0 for immediately rejection if none at the moment).
     * @param float|null $retryEverySec Check for available connections every how many seconds (null for immediately rejection if none at the moment).
     * @param LoopInterface|null $loop
     */
    public function __construct(
        protected \Closure       $connectionFactory,
        protected string         $connectionAdapterClass,
        string                   $connectionSelectorClass = UsageConnectionSelector::class,
        protected ?int           $connectionsLimit = null,
        protected float          $retryLimit = 0,
        protected ?float         $retryEverySec = null,
        protected ?LoopInterface $loop = null
    )
    {
        if (!is_subclass_of($this->connectionAdapterClass, ConnectionAdapterInterface::class)) {
            throw new \TypeError('Class given in `connectionAdapterClass` must implements ConnectionAdapterInterface');
        }
        $this->connections = new \SplObjectStorage();
        $this->awaiting = new \SplObjectStorage();
        $this->selector = new $connectionSelectorClass($this->connections);
        $this->loop ??= Loop::get();
    }

    /**
     * Get connection from pool.
     * Reject if no connection is available and cannot create the new one.
     */
    public function getConnection(): PromiseInterface
    {
        $connection = $this->selectConnection();

        if (!$connection) {
            if ($this->canMakeNewConnection()) {
                try {
                    /** @var ConnectionAdapterInterface $connection */
                    $connection = await($this->makeNewConnection());
                    $this->connections->attach($connection);
                } catch (\Throwable $exception) {
                    return reject($exception);
                }
            } else {
                $deferred = new Deferred();
                $this->loop->futureTick(fn () => $this->retry($deferred));
                return $deferred->promise();
            }
        }

        return resolve($connection->getConnection());
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
        return ($this->connectionFactory)()
            ->then(fn (object $connection) => new $this->connectionAdapterClass($connection));
    }

    protected function retry(Deferred $deferred): void
    {
        if ($this->retryLimit === null || $this->retryLimit < 1 || $this->retryEverySec === null) {
            $deferred->reject(new ConnectionPoolException('No available connection to use'));
            return;
        }

        if (!$this->awaiting->contains($deferred)) {
            $this->awaiting->attach($deferred, 0);
        }

        if ($this->awaiting[$deferred] === $this->retryLimit) {
            $deferred->reject(new ConnectionPoolException("No available connection to use; $this->retryLimit attempts were made"));
            return;
        }

        $this->loop->addTimer($this->retryEverySec, function () use ($deferred) {
            $connectionAdapter = $this->selectConnection();

            if ($connectionAdapter) {
                $this->awaiting->detach($deferred);
                $deferred->resolve($connectionAdapter->getConnection());
                return;
            }

            $this->awaiting[$deferred] = $this->awaiting[$deferred] + 1;
            $this->retry($deferred);
        });
    }
}