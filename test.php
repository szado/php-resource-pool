<?php

use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;
use Szado\React\ConnectionPool\ConnectionPool;
use Szado\React\ConnectionPool\ConnectionState;

require 'vendor/autoload.php';

$pool = new ConnectionPool(function () {
    return new class implements ConnectionAdapterInterface {
        private ConnectionState $connectionState = ConnectionState::Busy;

        public function getState(): ConnectionState
        {
            return $this->connectionState;
        }

        public function getConnection(): object
        {
            return new stdClass();
        }
    };
}, connectionsLimit: 1);

$pool->get();
$pool->get();
