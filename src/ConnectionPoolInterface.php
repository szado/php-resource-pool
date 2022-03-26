<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool;

use React\Promise\PromiseInterface;
use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;

interface ConnectionPoolInterface
{
    /**
     * @return PromiseInterface<ConnectionAdapterInterface, ConnectionPoolException>
     */
    public function get(): PromiseInterface;
}