<?php

namespace BigBoom\Zookeeper\Test;

use BigBoom\Zookeeper\Exceptions\NodeException;
use BigBoom\Zookeeper\Facades\Zk;
use Tests\TestCase;
use Zookeeper;
use Exception;

class ZookeeperTest extends TestCase
{
    public function testCreate()
    {
        $this->assertFalse(Zk::exists('/testCreate'));
        Zk::create('/testCreate', 'This is a test');
        $this->assertTrue(Zk::exists('/testCreate'));
        $this->assertEquals('This is a test', Zk::get('/testCreate'));
    }

    public function testGet()
    {
        Zk::create('/testGet', 'Lorem Ipsum');
        $this->assertEquals('Lorem Ipsum', Zk::get('/testGet'));
    }

    public function testGetWatcher()
    {
        Zk::ensurePath('/testGet/path');
        $ranListener = false;
        Zk::get('/testGet', function ($eventType, $_, $path) use (&$ranListener) {
            $ranListener = true;
            $this->assertEquals($eventType, \Zookeeper::CHANGED_EVENT);
            $this->assertEquals($path, '/testGet');
        });
        Zk::set('/testGet', '2');
        zookeeper_dispatch();
        $this->assertTrue($ranListener);
    }

    public function testGetThrowsErrorOnFalse()
    {
        $this->expectException(\ZookeeperNoNodeException::class);
        Zk::get('/qwerty');
    }

    public function testSet()
    {
        Zk::create('/testSet', 'Foobar');
        $this->assertTrue(Zk::set('/testSet', 'Bazbat'));
        $this->assertEquals('Bazbat', Zk::get('/testSet'));
    }

    public function testGetChildren()
    {
        Zk::ensurePath('/testGetChildren/1');
        $this->assertEquals([], Zk::getChildren('/testGetChildren'));
        $children = ['foo', 'bar', 'baz', 'bat', 'qwerty', 'bim', 'bam'];
        foreach ($children as $child) {
            Zk::create("/testGetChildren/$child", '1');
        }
        $this->assertEqualsCanonicalizing($children, Zk::getChildren('/testGetChildren'));
    }

    public function testGetChildrenWatcherOnRemove()
    {
        Zk::ensurePath('/testGetChildren/watcher/1');
        $ranListener = false;
        Zk::create('/testGetChildren/watcher/node', '1');
        Zk::getChildren('/testGetChildren/watcher', function ($type) use (&$ranListener) {
            $ranListener = true;
            $this->assertEquals(Zookeeper::CHILD_EVENT, $type);
        });
        Zk::remove('/testGetChildren/watcher/node');
        zookeeper_dispatch();
        $this->assertTrue($ranListener);
    }

    public function testGetChildrenWatcherOnCreate()
    {
        Zk::ensurePath('/testGetChildren/watcher2/1');
        $ranListener = false;
        Zk::create('/testGetChildren/watcher2/node', '1');
        Zk::getChildren('/testGetChildren/watcher2', function ($type) use (&$ranListener) {
            $ranListener = true;
            $this->assertEquals(Zookeeper::CHILD_EVENT, $type);
        });
        Zk::create('/testGetChildren/watcher2/node2', '1');
        zookeeper_dispatch();
        $this->assertTrue($ranListener);
    }

    public function testExists()
    {
        $this->assertFalse(Zk::exists('/testNode'));
        Zk::create('/testNode', '');
        $this->assertTrue(Zk::exists('/testNode'));
        Zk::remove('/testNode');
    }

    public function testExistsWatcher()
    {
        Zk::ensurePath('/testExists/watcher/node');
        $ranListener = false;
        Zk::exists('/testExists/watcher', function ($type, $_, $path) use (&$ranListener) {
            $ranListener = true;
            $this->assertEquals('/testExists/watcher', $path);
            $this->assertEquals(Zookeeper::CHANGED_EVENT, $type);
        });
        Zk::set('/testExists/watcher', '2');
        zookeeper_dispatch();
        $this->assertTrue($ranListener);
    }

    public function testRemove()
    {
        Zk::create('/foobar', '');
        $this->assertTrue(Zk::exists('/foobar'));
        Zk::remove('/foobar');
        $this->assertFalse(Zk::exists('/foobar'));
    }

    public function testEnsurePath()
    {
        $this->assertFalse(Zk::exists('/test'));
        $this->assertFalse(Zk::exists('/test/ensure'));
        $this->assertFalse(Zk::exists('/test/ensure/path'));
        Zk::ensurePath('/test/ensure/path/to');
        $this->assertTrue(Zk::exists('/test'));
        $this->assertTrue(Zk::exists('/test/ensure'));
        $this->assertTrue(Zk::exists('/test/ensure/path'));
        $this->assertFalse(Zk::exists('/test/ensure/path/to'));
    }

    /**
     * @dataProvider getInvalidNodePathParts
     */
   /* public function testInvalidNodePathParts($part)
    {
        $path = "test/$part/node";
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("$path is an invalid path!");
        Zk::exists($path);
    }*/

    public function getInvalidNodePathParts()
    {
        return [
            ['.'],
            ['..'],
            ['zookeeper'],
        ];
    }
}
