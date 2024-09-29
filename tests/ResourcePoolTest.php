<?php

declare(strict_types=1);

namespace Shado\Tests\ResourcePool;

use OutOfRangeException;
use PHPUnit\Framework\TestCase;
use Shado\ResourcePool\FactoryController;
use Shado\ResourcePool\ResourcePool;
use Shado\Tests\ResourcePool\Components\IdentifiableResource;
use Shado\Tests\ResourcePool\Components\PoolFactory;

class ResourcePoolTest extends TestCase
{
    public function testBorrow()
    {
        $pool = PoolFactory::createPoolWithLimit(2);

        $r1 = $pool->borrow();
        $r2 = $pool->borrow();

        $this->assertIsObject($r1);
        $this->assertIsObject($r2);
        $this->assertNotSame($r1, $r2);
    }

    public function testBorrowAndReturnState()
    {
        $pool = PoolFactory::createPoolWithLimit(1);

        $this->assertEquals(0, $pool->debug()['all_count']);

        $r1 = $pool->borrow();

        $this->assertIsObject($r1);
        $this->assertEquals(1, $pool->debug()['all_count']);
        $this->assertEquals(1, $pool->debug()['borrowed_count']);

        $pool->return($r1);

        $this->assertEquals(1, $pool->debug()['all_count']);
        $this->assertEquals(1, $pool->debug()['available_count']);
    }

    public function testLimitReachedThrows()
    {
        $pool = PoolFactory::createPoolWithLimit(1);

        $pool->borrow();

        $this->expectException(OutOfRangeException::class);

        $pool->borrow();
    }

    public function testUnlimitedResources()
    {
        $pool = PoolFactory::createPoolWithLimit(0);

        $this->expectNotToPerformAssertions();

        $pool->borrow();
        $pool->borrow();
        $pool->borrow();
    }

    public function testFactoryControllerDetach()
    {
        $controller = null;
        $pool = new ResourcePool(function (FactoryController $factoryController) use (&$controller) {
            $controller = $factoryController;
            return new IdentifiableResource();
        }, 1);

        $this->assertNull($controller);

        $r1 = $pool->borrow();

        $this->assertInstanceOf(FactoryController::class, $controller);

        $pool->return($r1);
        $controller->detach();
        $r2 = $pool->borrow();

        $this->assertNotSame($r1, $r2);
    }
}
