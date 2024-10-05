<?php

declare(strict_types=1);

namespace Shado\Tests\ResourcePool;

use Exception;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use Shado\ResourcePool\AsyncResourcePool;
use Shado\ResourcePool\Exceptions\ResourceSelectingException;
use Shado\ResourcePool\ResourcePoolInterface;

class AsyncResourcePoolTest extends TestCase
{
    private ResourcePoolInterface $resourcePoolMock;
    private AsyncResourcePool $asyncResourcePool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resourcePoolMock = $this->createMock(ResourcePoolInterface::class);

        $this->asyncResourcePool = new AsyncResourcePool($this->resourcePoolMock);
    }

    public function testBorrowResourceSuccessfully(): void
    {
        $resource = new \stdClass();

        $this->resourcePoolMock
            ->method('borrow')
            ->willReturn($resource);

        $this->assertSame($resource, $this->asyncResourcePool->borrow());
    }

    public function testBorrowAsyncResourceSuccessfully(): void
    {
        $resource = new \stdClass();

        $this->resourcePoolMock
            ->method('borrow')
            ->willReturn($resource);

        $promise = $this->asyncResourcePool->borrowAsync();

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->then(function ($result) use ($resource) {
            $this->assertSame($resource, $result);
        });
    }

    public function testRetryingToBorrowResource(): void
    {
        $resource = new \stdClass();
        $callCount = 0;

        $this->resourcePoolMock
            ->method('borrow')
            ->willReturnCallback(function () use (&$callCount, $resource) {
                if ($callCount++ < 1) {
                    throw new Exception('Resource unavailable');
                }
                return $resource;
            });

        $promise = $this->asyncResourcePool->borrowAsync();

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->then(function ($result) use ($resource) {
            $this->assertSame($resource, $result);
        });

        Loop::run();
    }

    public function testThrowsExceptionWhenResourceCannotBeBorrowedWithinTimeout(): void
    {
        $this->asyncResourcePool = new AsyncResourcePool($this->resourcePoolMock, 0.001);

        $this->resourcePoolMock
            ->method('borrow')
            ->willThrowException(new Exception('Resource unavailable'));

        $promise = $this->asyncResourcePool->borrowAsync();

        $promise->then(
            null,
            function ($e) {
                $this->assertInstanceOf(ResourceSelectingException::class, $e);
            }
        );

        Loop::run();
    }

    public function testReturnResourceSuccessfully(): void
    {
        $resource = new \stdClass();

        $this->resourcePoolMock
            ->expects($this->once())
            ->method('return')
            ->with($resource);

        $this->asyncResourcePool->return($resource);
    }
}