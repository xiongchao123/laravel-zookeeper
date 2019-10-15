<?php

namespace Tests\Feature;

use BigBoom\Zookeeper\Facades\Zk;
use Mockery;
use Tests\TestCase;

class ZkLockTest extends TestCase
{
    public function testLock()
    {
        $this->assertNotNull(Zk::lock('/lockTest1/xlock'));
    }

    public function testUnlock()
    {
        $key = Zk::lock('/lockTest1/xlock2');
        $this->assertNotNull($key);
        Zk::unlock($key);
    }

    public function testLockBlocksLock()
    {
        $toLock = '/lockTest2/xlock';
        $key1 = Zk::lock($toLock);
        $this->assertNotNull($key1);
        $this->assertNull(Zk::lock($toLock));
        Zk::unlock($key1);
        $this->assertNotNull(Zk::lock($toLock));
    }

    public function testLockCatchesExceptions()
    {
        $zk = Mockery::mock(\BigBoom\Zookeeper\Zk::class);
        $zk->shouldReceive('ensurePath')->andReturn(false);
        $this->assertFalse($zk->ensurePath('/lockTest2/noLock'));
    }

    public function testWriteLockBlocksLock()
    {
        $toLock = '/lockTest2/wlock';
        $key1 = Zk::writeLock($toLock);
        $this->assertNotNull($key1);
        $this->assertNull(Zk::lock($toLock));
        Zk::unlock($key1);
    }

    public function testReadLockDoesNotBlockReadLock()
    {
        $toLock = '/lockTest3/rlock';
        $key1 = Zk::readLock($toLock);
        $key2 = Zk::readLock($toLock);
        $this->assertNotSame($key1, $key2);
        $this->assertNotNull($key1);
        $this->assertNotNull($key2);
    }

    public function testWriteLockBlocksReadLocks()
    {
        $toLock = '/lockTest4/rlock';
        $this->assertNotNull(Zk::readLock($toLock));
        $this->assertNotNull(Zk::readLock($toLock));
        $key3 = Zk::writeLock($toLock);
        $this->assertNotNull($key3);
        $this->assertNull(Zk::readLock($toLock));
        Zk::unlock($key3);
        $this->assertNotNull(Zk::readLock($toLock));
    }

    public function testLockTimeout()
    {
        $toLock = '/lockTest5/lock';
        $key1 = Zk::lock($toLock);
        $before = time();
        $this->assertNull(Zk::lock($toLock, 2));
        $after = time();
        // Time should be at least two seconds past
        $this->assertGreaterThanOrEqual(2, $after - $before);
        Zk::unlock($key1);
        Zk::lock($toLock, 10);
        // Time should not be close to the ten second timeout
        $this->assertLessThanOrEqual(1, time() - $after);
    }

    public function testNonLockNodesDoNotBlockLock()
    {
        $toLock = '/lockTest6/lock';
        Zk::ensurePath("$toLock/1");
        Zk::create("$toLock/lock-", 'Testing');
        $this->assertNotNull(Zk::lock($toLock));
    }

    public function testIsLocked()
    {
        $toLock = '/lockTest7/isLocked';
        $this->assertFalse(Zk::isLocked($toLock));
        Zk::lock($toLock);
        $this->assertTrue(Zk::isLocked($toLock));
    }

}
