<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool\ConnectionAdapters;

use Szado\React\ConnectionPool\ConnectionState;

interface ConnectionAdapterInterface
{
    public function getState(): ConnectionState;
    public function getConnection(): object;
}