<?php

namespace Szado\Tests\React\ConnectionPool;

use React\EventLoop\Loop;
use Szado\React\ConnectionPool\ConnectionPool;
use PHPUnit\Framework\TestCase;
use Szado\React\ConnectionPool\ConnectionPoolException;
use function React\Async\await;
use function React\Promise\resolve;

class ConnectionPoolTest extends TestCase
{
    private function getCorrectConstructorArgs(): array
    {
        return [
            'connectionFactory' => fn () => resolve(new Adapter()),
            'connectionSelectorClass' => Selector::class,
            'connectionsLimit' => null,
            'retryLimit' => null,
            'retryEverySec' => 0.001,
            'loop' => Loop::get()
        ];
    }

    public function testGet()
    {
        $cp = new ConnectionPool(...$this->getCorrectConstructorArgs());
        $this->assertInstanceOf(Adapter::class, await($cp->get()));

        $cp = new ConnectionPool(...[
            ...$this->getCorrectConstructorArgs(),
            'connectionsLimit' => 1
        ]);
        $a1 = await($cp->get());
        $a2 = await($cp->get());
        $this->assertEquals($a1, $a2);
    }

    public function testGetExceptionLimitReached()
    {
        $cp = new ConnectionPool(...[
            ...$this->getCorrectConstructorArgs(),
            'connectionSelectorClass' => AlwaysNullSelector::class,
            'connectionsLimit' => 1,
            'retryLimit' => 1,
        ]);
        $this->expectException(ConnectionPoolException::class);
        await($cp->get());
    }

    public function testGetExceptionNoAvailable()
    {
        $cp = new ConnectionPool(...[
            ...$this->getCorrectConstructorArgs(),
            'connectionSelectorClass' => AlwaysNullSelector::class,
            'connectionsLimit' => 1,
            'retryLimit' => 0,
        ]);
        $this->expectException(ConnectionPoolException::class);
        await($cp->get());
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
