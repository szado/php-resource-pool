<?php

namespace Szado\Tests\React\ConnectionPool;

use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;
use Szado\React\ConnectionPool\ConnectionSelectors\ConnectionSelectorInterface;

class Selector implements ConnectionSelectorInterface
{
    public function __construct(private \SplObjectStorage $connections)
    {
    }

    public function select(): ConnectionAdapterInterface|null
    {
        $this->connections->rewind();

        return $this->connections->count() ? $this->connections->current() : null;
    }
}