<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool;

use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;

interface ConnectionPoolInterface
{
    public function get(): ConnectionAdapterInterface;
}