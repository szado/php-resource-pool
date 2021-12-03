<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool;

use React\Promise\PromiseInterface;

interface ConnectionPoolInterface
{
    public function getConnection(): PromiseInterface;
}