<?php

namespace Szado\Tests\React\ConnectionPool;

use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;
use Szado\React\ConnectionPool\ConnectionSelectors\ConnectionSelectorInterface;

class Selector implements ConnectionSelectorInterface
{
    public function __construct(private \SplObjectStorage $connectionAdapters)
    {
    }

    public function select(): ConnectionAdapterInterface|null
    {
        $this->connectionAdapters->rewind();

        return $this->connectionAdapters->count() ? $this->connectionAdapters->current() : null;
    }
}