<?php

namespace Szado\Tests\React\ConnectionPool;

use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface;
use Szado\React\ConnectionPool\ConnectionPool;
use PHPUnit\Framework\TestCase;
use function React\Promise\resolve;

class ConnectionPoolTest extends TestCase
{
    private function getCorrectConstructorArgs(): array
    {
        return [
            'connectionFactory' => fn () => resolve(),
            'connectionAdapterClass' => Adapter::class,
            'connectionSelectorClass' => Selector::class,
            'connectionsLimit' => null,
            'retryLimit' => null,
            'retryEverySec' => 0.001,
            'loop' => Loop::get()
        ];
    }

    public function test__construct()
    {
        $this->expectException(\TypeError::class);
        new ConnectionPool(...[
            ...$this->getCorrectConstructorArgs(),
            'connectionAdapterClass' => \stdClass::class
        ]);
    }

    public function testGetConnection()
    {
        $cp = new ConnectionPool(...$this->getCorrectConstructorArgs());
        $this->assertInstanceOf(PromiseInterface::class, $cp->getConnection());
    }

    public function test_canMakeNewConnection()
    {
        $cp = new ConnectionPool(...$this->getCorrectConstructorArgs());
        $ref = new \ReflectionMethod($cp, 'canMakeNewConnection');
        $ref->setAccessible(true);
        $this->assertTrue($ref->invoke($cp), 'limit===null');

        $cp = new ConnectionPool(...[
            ...$this->getCorrectConstructorArgs(),
            'connectionsLimit' => 0
        ]);
        $ref = new \ReflectionMethod($cp, 'canMakeNewConnection');
        $ref->setAccessible(true);
        $this->assertFalse($ref->invoke($cp), 'limit===0');
    }

}
