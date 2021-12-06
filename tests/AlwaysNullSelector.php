<?php

namespace Szado\Tests\React\ConnectionPool;

use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;
use Szado\React\ConnectionPool\ConnectionSelectors\ConnectionSelectorInterface;

class AlwaysNullSelector implements ConnectionSelectorInterface
{
    public function __construct(\SplObjectStorage $connections)
    {
    }

    public function select(): ConnectionAdapterInterface|null
    {
        return null;
    }
}