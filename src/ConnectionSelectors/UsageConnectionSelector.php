<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool\ConnectionSelectors;

use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;
use Szado\React\ConnectionPool\ConnectionState;

class UsageConnectionSelector implements ConnectionSelectorInterface
{
    public function __construct(private \SplObjectStorage $connections)
    {
    }

    public function select(): ConnectionAdapterInterface|null
    {
        $leastUsed = null;

        /** @var ConnectionAdapterInterface $connection */
        foreach ($this->connections as $connection) {
            if ($this->connections[$connection] === null) {
                $this->connections[$connection] = 0;
            }

            if ($leastUsed === null) {
                $leastUsed = $connection;
                continue;
            }

            if (
                $this->connections[$connection] < $this->connections[$leastUsed]
                && $connection->getState() === ConnectionState::Ready
            ) {
                $leastUsed = $connection;
            }
        }

        if ($leastUsed) {
            $this->connections[$leastUsed] = $this->connections[$leastUsed] + 1;
        }

        return $leastUsed;
    }
}