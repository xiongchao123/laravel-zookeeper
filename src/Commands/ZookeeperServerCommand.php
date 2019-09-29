<?php

namespace BigBoom\Zookeeper\Commands;

use Illuminate\Console\Command;
use BigBoom\Zookeeper\Zk;
use Symfony\Component\Process\Process;

class ZookeeperServerCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zookeeper:server {action : start|cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Zookeeper Cache config';

    /**
     * The console command action. start|cache
     * 
     * @var string
     */
    protected $action;

    /**
     * The configs for this package.
     *
     * @var array
     */
    protected $config;

    /**
     * Excute the console command.
     *
     */
    public function handle ()
    {
        $this->checkEnviroment();
        $this->loadConfig();
        $this->initAction();
        $this->runAction();
    }

    /**
     * Check running enviroment
     */
    protected function checkEnviroment ()
    {
        if (! extension_loaded('zookeeper')) {
            $this->error("Can't detect zookeeper extension installed.");

            exit(1);
        }
    }

    /**
     * Load configs
     */
    protected function loadConfig ()
    {
        $this->config = $this->laravel->make('config')->get('zk_config');
    }

    /**
     * Intitalize command action
     */
    protected function initAction ()
    {
        $this->action = $this->argument('action');

        if (! in_array($this->action, ['start', 'cache'], true)) {
            $this->error("Invalid argument '{$this->action}' . Expected 'start', 'stop', 'restart'.");
        }
    }

    /**
     * Run action
     */
    protected function runAction ()
    {
        $this->{$this->action}();
    }

    /**
     * Start
     */
    protected function start ()
    {
        $zk = $this->laravel->make(Zk::class);
        $zk->run();
        $this->info("Zoookeeper data cached to {$this->config['cache']}/config.php successfully!");
        $this->info("Please Enter 'Ctrl + C' to Stop ");
        while (true) {
            sleep(1);
        }
    }

    /**
     * Cache
     */
    protected function cache ()
    {
        $zk = $this->laravel->make(Zk::class);
        $zk->cache();
        $this->info("Zoookeeper data cached to {$this->config['cache']}/config.php successfully!");
    }


}