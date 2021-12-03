<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool\ConnectionSelectors;

use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;

interface ConnectionSelectorInterface
{
    public function __construct(\SplObjectStorage $connectionAdapters);
    public function select(): ConnectionAdapterInterface|null;
}