<?php

namespace BigBoom\Zookeeper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Zk
 * @method static array getConfig()
 * @method static string create(string $node, string $contents, ?int $flags = 0, ?array $acl = null)
 * @method static string get(string $node, callable $watcherCallback = null, array &$stat = null, int $maxSize = 0): string
 * @method static bool set(string $node, string $data): bool
 * @method static array getChildren(string $node, ?callable $watcherCallback = null): array
 * @method static bool exists(string $node, ?callable $watcherCallback = null): bool
 * @method static bool remove(string $node): bool
 * @method static bool ensurePath(string $node): bool
 * @method static bool addAuth(string $scheme, string $cert, callable $completionCb)
 * @method static string lock(string $key, int $timeout = 0): ?string
 * @method static string writeLock(string $key, int $timeout = 0): ?string
 * @method static string readLock(string $key, int $timeout = 0): ?string
 * @method static bool unlock(string $key): bool
 * @method static bool isLocked(string $key, string $mode = 'exclusive') : bool
 *
 * @package BigBoom\Zookeeper\Facades
 * @see \BigBoom\Zookeeper\Zk;
 */
class Zk extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'zk';
    }
}