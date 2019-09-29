<?php

namespace BigBoom\Zookeeper;

use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use BigBoom\Zookeeper\Exceptions\ErrorInfoException;
use BigBoom\Zookeeper\Exceptions\FrameworkNotSupportException;
use Zookeeper;
use Illuminate\Config\Repository;
use Throwable;
use Exception;

class ZK
{
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
     * zookeeper root node
     *
     * @var
     */
    protected $zkRootPath;

    /**
     * zookeeper node and nodeValue
     *
     * @var
     */
    protected $data;

    /**
     * @var
     */
    protected $file;

    /**
     * Zk constructor.
     * @param Container $container
     * @param string $framework
     * @param array $config
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __construct(Container $container, string $framework, array $config)
    {
        $this->container = $container;
        $this->container->make('config');
        $this->setFramework($framework);
        $this->setConfig($config);
        $this->setZkRootPath();
    }

    public function run()
    {
        $this->init();
    }

    /**
     * @Desc cache
     */
    public function cache()
    {
        $this->init(false);
        $this->getNode($this->zkRootPath);
    }

    /**
     * @param string $framework
     */
    protected function setFramework(string $framework)
    {
        $framework = strtolower($framework);

        if (!in_array($framework, ['laravel', 'lumen'])) {
            throw new FrameworkNotSupportException($framework);
        }

        $this->framework = $framework;
    }

    /**
     * @param array $config
     */
    protected function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getConfig()
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
        if (!$this->config['is_watch']) {
            $this->zk = new Zookeeper($this->config['host']);
        } else {
            $this->zk = new Zookeeper($this->config['host'], [$this->config['watcher'], 'watch'], $this->config['watch_recv_timeout']);
        }
    }

    public function close()
    {
        try {
            $this->zk->close();
        } catch (Throwable $e) {
        }
    }

    protected function reConnect()
    {
        $this->close();

        $this->init();
    }

    /**
     * @throws Throwable
     */
    private function getState(): int
    {
        return (int)$this->zk->getState();
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
//        $node = $this->formatNodePath($node);

        $acl = $acl ?? [["perms" => Zookeeper::PERM_ALL, "scheme" => "world", "id" => "anyone"]];
        $result = $this->zk->create($node, $contents, $acl, $flags);

        return (string)$result;
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
     * Monitor callback events{connection nodeEvent childNodeEvent}
     *
     * @param $eventType
     * @param $connectionState
     * @param $path
     */
    public function watch($eventType, $connectionState, $path)
    {
        switch ($eventType) {
            case Zookeeper::CREATED_EVENT:
                // 1 create node event 
            case Zookeeper::DELETED_EVENT:
                // 2 delete node event
            case Zookeeper::CHANGED_EVENT:
                // 3 change the nodeValue
                $this->getNodeValue($path);
                break;
            case Zookeeper::CHILD_EVENT:
                // 4 watch the child create or delete event
                $this->getNode($path);
                break;
            case Zookeeper::SESSION_EVENT:
                // -1 client disconnects or reconnect
                if (3 == $connectionState) {
                    $this->getNode($this->zkRootPath);
                }
                break;
            case Zookeeper::NOTWATCHING_EVENT:
                // -2 remove the watch event
            default:
        }
    }

    /**
     * @param string $scheme
     * @param string $cert
     * @param callable $completionCb
     * @return bool
     */
    public function addAuth(string $scheme, string $cert, callable $completionCb)
    {
        return $this->zk->addAuth($scheme, $cert, $completionCb);
    }


    public function setNode(string $path)
    {

    }

    /**
     * @param string $path
     */
    protected function getNode(string $path)
    {
        if ($this->zk->exists($path)) {
            $nodes = $this->zk->getChildren($path, [$this, 'watch']);
            if (empty($nodes)) {
                $this->getNodeValue($path);
            } else {
                foreach ($nodes as $node) {
                    $this->getNode($path . DIRECTORY_SEPARATOR . $node);
                }
            }
        }
    }

    /**
     * @Desc get nodeValue and cache it
     * @param $nodePath
     */
    protected function getNodeValue($nodePath)
    {
        $node = trim(str_replace('/', '.', str_replace($this->zkRootPath . DIRECTORY_SEPARATOR, '', $nodePath)));
        if ($this->zk->exists($nodePath)) {
            $stat = [];
            $nodeValue = $this->zk->get($nodePath, [$this, 'watch'], $stat);
            $this->data[$node] = $nodeValue;
        } else {
            if (isset ($this->data[$node])) {
                unset ($this->data[$node]);
            }
        }

        $this->cacheData();
    }


    /**
     * @Desc Cache the data
     */
    protected function cacheData()
    {
        $this->file = $this->container->make('files');
        if (!$this->file->isDirectory($this->config['cache'])) {
            $this->file->makeDirectory($this->config['cache']);
        }

        $this->file->put($this->config['cache'] . '/config.php', '<?php return ' . var_export($this->data, true) . ';' . PHP_EOL);
    }


    /**
     * @Desc return zookeeper data
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set zkRootPath
     */
    protected function setZkRootPath()
    {
        $this->zkRootPath = $this->config['path'] . DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $nodeName
     * @return string
     */
    public function getNodeData($nodeName = '')
    {
        $this->init(false);

        $data = '';
        $path = $this->zkRootPath . DIRECTORY_SEPARATOR . trim($nodeName, '/');
        if ($this->zk->exists($path)) {
            $data = rtrim($this->zk->get($path), '/');
        }
        return $data;
    }
}