<?php

namespace Szado\Tests\React\ConnectionPool;

use Szado\React\ConnectionPool\ConnectionState;

class Adapter implements \Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface
{
    public function getState(): \Szado\React\ConnectionPool\ConnectionState
    {
        return ConnectionState::Ready;
    }

    public function getConnection(): object
    {
        return new \stdClass();
    }
}