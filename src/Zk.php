<?php

namespace BigBoom\Zookeeper;

use BigBoom\Zookeeper\Exceptions\ConnectionException;
use BigBoom\Zookeeper\Exceptions\NodeException;
use Illuminate\Contracts\Container\Container;
use BigBoom\Zookeeper\Exceptions\FrameworkNotSupportException;
use Zookeeper;
use Throwable;
use Exception;

class Zk
{
    const TYPE_EXCLUSIVE = 'exclusive';
    const TYPE_READ = 'read';
    const TYPE_WRITE = 'write';

    /**
     * Container
     * @var \Illuminate\Contracts\Container\Container;
     */
    protected $container;

    /**
     * @var string
     */
    protected $framework;

    /**
     * @var array
     */
    protected $config;

    /**
     * zookeeper connection
     *
     * @var Zookeeper
     */
    protected $zk;

    /**
     * ZK constructor.
     * @param Container $container
     * @param string $framework
     * @param array $config
     * @throws Throwable
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __construct(Container $container, string $framework, array $config)
    {
        $this->container = $container;
        $this->container->make('config');
        $this->setFramework($framework);
        $this->setConfig($config);
        $this->init();
    }

    /**
     * @param string $framework
     */
    protected function setFramework(string $framework): void
    {
        $framework = strtolower($framework);

        if (!in_array($framework, ['laravel', 'lumen'])) {
            throw new FrameworkNotSupportException($framework);
        }

        $this->framework = $framework;
    }

    /**
     * @return string
     */
    public function getFramework(): string
    {
        return $this->framework;
    }

    /**
     * @param array $config
     */
    protected function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * init zk connect
     *
     * @throws Throwable
     */
    protected function init()
    {
        $this->zk = new Zookeeper();
        if (!$this->config['is_watch']) {
            $this->connect($this->config['host']);
        } else {
            $this->connect($this->config['host'], [$this->config['watcher'], 'watch'], $this->config['watch_recv_timeout']);
        }
    }

    public function connect(string $zookeeperHost, callable $watcherCallback = null, int $timeout = 10000)
    {
        $counter = 0;
        $interval = 50; // Interval in milliseconds to check. Will double every time the connection couldn't be established
        $this->zk->connect($zookeeperHost, $watcherCallback, $timeout);
        do {
            if ($this->isConnected()) {
                break;
            }
            if ($counter === 3) {
                throw new ConnectionException('Could not connect to zookeeper server', $this->getState() ?: 255);
            }
            usleep($interval * 1000);
            $counter += 1;
            $interval *= 2;
        } while (true);
    }

    // todo 暂不开放
    private function close()
    {
        try {
            $this->zk->close();
        } catch (Throwable $e) {
        }
    }

    private function isConnected(): bool
    {
        try {
            return $this->getState() === Zookeeper::CONNECTED_STATE;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function create(string $node, string $contents, ?int $flags = 0, ?array $acl = null): string
    {
        $node = $this->formatNodePath($node);

        $acl = $acl ?? [["perms" => Zookeeper::PERM_ALL, "scheme" => "world", "id" => "anyone"]];
        $result = $this->zk->create($node, $contents, $acl, $flags);

        return (string)$result;
    }

    public function get(string $node, callable $watcherCallback = null, array &$stat = null, int $maxSize = 0): string
    {
        $node = $this->formatNodePath($node);

        $nodeContents = $this->zk->get($node, $watcherCallback, $stat, $maxSize);
        if ($nodeContents === false) {
            throw new NodeException(sprintf('Could not access node %s', $node), 1);
        }

        return (string)$nodeContents;
    }

    public function set(string $node, string $data): bool
    {
        $node = $this->formatNodePath($node);

        $result = $this->zk->set($node, $data);
        return (bool)$result;
    }

    public function getChildren(string $node, ?callable $watcherCallback = null): array
    {
        $node = $this->formatNodePath($node);

        $children = $this->zk->getChildren($node, $watcherCallback);
        if ($children === false) {
            throw new NodeException(sprintf('Could not list children of node %s', $node), 2);
        }
        return (array)$children;
    }

    public function exists(string $node, ?callable $watcherCallback = null): bool
    {
        $node = $this->formatNodePath($node);

        $exists = $this->zk->exists($node, $watcherCallback);
        return !empty($exists);
    }

    public function remove(string $node): bool
    {
        $remove = $this->zk->delete($node);
        return (bool)$remove;
    }

    public function ensurePath(string $node): bool
    {
        $parent = dirname('/' . trim($node, '/'));
        try {
            if ($this->exists($parent)) {
                return true;
            }
            if (!$this->ensurePath($parent)) {
                return false; // @codeCoverageIgnore
            }
            $this->create($parent, '');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function addAuth(string $scheme, string $cert, callable $completionCb)
    {
        return $this->zk->addAuth($scheme, $cert, $completionCb);
    }

    private function formatNodePath(string $node): string
    {
        $node = trim($node, '/');
        // regex to find invalid characters
        $pattern = '/' .
            '[' . // Start range match
            "\u{1}-\u{19}\u{7f}-\u{9f}" . // These don't display well, so zookeeper won't allow them
            "\u{d800}-\u{f8fff}\u{fff0}-\u{ffff}\u{f0000}-\u{fffff}" . // These following sequences are not allowed
            "\u{1fffe}-\u{1ffff}\u{2fffe}-\u{2ffff}\u{3fffe}-\u{3ffff}\u{4fffe}-\u{4ffff}" .
            "\u{5fffe}-\u{5ffff}\u{6fffe}-\u{6ffff}\u{7fffe}-\u{7ffff}\u{8fffe}-\u{8ffff}" .
            "\u{9fffe}-\u{9ffff}\u{afffe}-\u{affff}\u{bfffe}-\u{bffff}\u{cfffe}-\u{cffff}" .
            "\u{dfffe}-\u{dffff}\u{efffe}-\u{effff}" .
            "]" . // Close range match
            "/";
        $node = preg_replace($pattern, '', $node);
        $node = str_replace("\0", '', $node); // Now strip null bytes
        if (array_intersect(explode('/', trim($node, '/')), ['.', '..', 'zookeeper'])) {
            throw new Exception(sprintf('%s is an invalid path!', $node), 3);
        }
        return '/' . trim(preg_replace('@//+@', '/', $node), '/');
    }

    /**
     * @throws Throwable
     */
    private function getState(): int
    {
        return (int)$this->zk->getState();
    }

    public function lock(string $key, int $timeout = 0): ?string
    {
        return $this->doLock($key, $timeout, self::TYPE_EXCLUSIVE);
    }

    public function writeLock(string $key, int $timeout = 0): ?string
    {
        return $this->doLock($key, $timeout, self::TYPE_WRITE);
    }

    public function readLock(string $key, int $timeout = 0): ?string
    {
        return $this->doLock($key, $timeout, self::TYPE_READ);
    }

    private function doLock(string $key, int $timeout = 0, string $mode = self::TYPE_EXCLUSIVE): ?string
    {
        try {
            $fullKey = $this->getLockName($key, $mode);
            $lockKey = $this->createLockKey($fullKey);

            if (!$this->waitForLock($lockKey, $fullKey, $timeout, $mode)) {
                // Clean up
                $this->remove($lockKey);
                return null;
            }

            return $lockKey;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function unlock(string $key): bool
    {
        return $this->remove($key);
    }

    public function isLocked(string $key, string $mode = 'exclusive')
    {
        $lockName = $this->getLockName($key);
        return !$this->waitForLock($lockName, $lockName, 0, $mode);
    }

    /**
     * @param string $key
     * @return string
     * @throws Exception
     */
    private function createLockKey(string $key): string
    {
        if (!$this->ensurePath($key)) {
            throw new Exception('Could not create parent node!');
        }

        $flags = Zookeeper::EPHEMERAL | Zookeeper::SEQUENCE;
        return $this->create($key, '1', $flags);
    }

    private function getLockName(string $key, string $type = 'exclusive'): string
    {
        switch ($type) {
            case self::TYPE_READ:
                $name = 'read-';
                break;
            case self::TYPE_WRITE:
            case self::TYPE_EXCLUSIVE:
            default:
                $name = 'lock-';
                break;
        }
        return $key . '/' . $name;
    }

    private function waitForLock(string $acquiredKey, string $baseKey, int $timeout, string $mode): bool
    {
        $deadline = microtime(true) + $timeout;
        $nameFilter = $indexFilter = null;
        $parent = dirname($baseKey);
        switch ($mode) {
            case static::TYPE_READ:
                $nameFilter = $this->getLockName($parent, static::TYPE_WRITE);
                break;
            case static::TYPE_WRITE:
            case static::TYPE_EXCLUSIVE:
                $nameFilter = '';
                $indexFilter = $this->getIndex($acquiredKey);
                break;
        }
        while (true) {
            if (!$this->isCurrentlyLocked($baseKey, $indexFilter, $nameFilter)) {
                return true;
            }
            if ($deadline <= microtime(true)) {
                break;
            }
            usleep(100000); // sleep for a tenth of a second
        }
        return false;
    }

    private function getIndex(string $key): ?int
    {
        if (!preg_match("/[0-9]+$/", $key, $matches)) {
            return null;
        }
        return intval(ltrim($matches[0], '0'));
    }

    /**
     * Check if the key is currently locked
     *
     * Providing an index filter will restrict the check to higher priority nodes (i.e. smaller numbers). If you use the
     * default name filter (empty string), the nameFilter will be set to $baseKey. If a name filter is provided, the
     * method will only match locks that share a base key name.
     *
     * @param string $baseKey
     * @param int|null $indexFilter
     * @param string|null $nameFilter
     * @return bool
     */
    private function isCurrentlyLocked(string $baseKey, ?int $indexFilter = null, ?string $nameFilter = ''): bool
    {
        $parent = dirname($baseKey);
        if (!$this->zk->exists($parent)) {
            return false;
        }
        if (is_string($nameFilter) && empty($nameFilter)) {
            $nameFilter = $baseKey;
        }
        $children = $this->zk->getChildren($parent);
        foreach ($children as $childKey) {
            $child = "$parent/$childKey";
            if (!is_null($nameFilter) && strpos($child, $nameFilter) !== 0) {
                continue;
            }
            if (is_null($indexFilter)) {
                return true;
            }
            $child_index = $this->getIndex($childKey);
            if (is_null($child_index)) {
                // Not a sequence node
                continue;
            }
            if ($child_index < $indexFilter) {
                // smaller index
                return true;
            }
        }
        return false;
    }
}
