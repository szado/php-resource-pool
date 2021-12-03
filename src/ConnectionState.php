<?php

declare(strict_types=1);

namespace Szado\React\ConnectionPool;

enum ConnectionState
{
    case Ready;
    case Busy;
    case Disconnected;
}