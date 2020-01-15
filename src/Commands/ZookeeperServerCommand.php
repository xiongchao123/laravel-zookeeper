<?php

namespace BigBoom\Zookeeper\Commands;

use Illuminate\Console\Command;

class ZookeeperServerCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zookeeper:server {action : watch|info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Zookeeper watch config';

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

    protected $allowedActions;

    /**
     * Excute the console command.
     *
     */
    public function handle()
    {
        $this->setAllowedActions();
        $this->checkEnvironment();
        $this->loadConfig();
        $this->initAction();
        $this->runAction();
    }

    protected function setAllowedActions()
    {
        $this->allowedActions = [
            'watch', 'info'
        ];
    }

    /**
     * Check running environment
     */
    protected function checkEnvironment()
    {
        if (!extension_loaded('zookeeper')) {
            $this->error("Can't detect zookeeper extension installed.");

            exit(1);
        }
    }

    /**
     * Load configs
     */
    protected function loadConfig()
    {
        $this->config = $this->laravel->make('config')->get('zk_config');
    }

    /**
     * Initialize command action
     */
    protected function initAction()
    {
        $this->action = $this->argument('action');

        if (!in_array($this->action, $this->allowedActions, true)) {
            $this->error("Invalid argument '{$this->action}' . Expected { " . implode(',', $this->allowedActions) . " } .");
        }
    }

    /**
     * Run action
     */
    protected function runAction()
    {
        switch ($this->action) {
            case "info":
                $this->showInfo();
                break;
            case "watch":
                $this->watch();
                break;
        }
    }

    public function showInfo()
    {
        $this->info("Zookeeper Info:");
        $this->info(shell_exec("php --ri zookeeper"));
    }

    protected function watch()
    {
        $this->info("watching.....");
    }
}
