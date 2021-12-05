<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool\ConnectionSelectors;

use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;
use Szado\React\ConnectionPool\ConnectionState;

class UsageConnectionSelector implements ConnectionSelectorInterface
{
    public function __construct(protected \SplObjectStorage $connections)
    {
    }

    public function select(): ConnectionAdapterInterface|null
    {
        $leastUsed = null;

        /** @var ConnectionAdapterInterface $connection */
        foreach ($this->connections as $connection) {
            if ($connection->getState() !== ConnectionState::Ready) {
                continue;
            }

            if ($leastUsed === null) {
                $leastUsed = $connection;
                continue;
            }

            $this->connections[$connection] ??= 0;

            if ($this->connections[$connection] < $this->connections[$leastUsed]) {
                $leastUsed = $connection;
            }
        }

        if ($leastUsed) {
            $this->connections[$leastUsed] += 1;
        }

        return $leastUsed;
    }
}