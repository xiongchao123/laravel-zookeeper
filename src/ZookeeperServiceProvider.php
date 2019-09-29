<?php

namespace BigBoom\Zookeeper;

use Illuminate\Support\ServiceProvider;
use BigBoom\Zookeeper\Commands\ZookeeperServerCommand;

class ZookeeperServiceProvider extends ServiceProvider
{
    protected $defer = false;

    protected static $server;

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/zk_config.php' => config_path('zk_config.php')
        ]);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerServer();
        $this->registerCommands();
    }

    /**
     * @Desc Register Zookeeper Server
     */
    protected function registerServer()
    {
        $this->app->singleton(ZK::class, function ($app) {
            if (is_null(static::$server)) {
                $this->createZookeeperServer();
            }
            return static::$server;
        });

        $this->app->alias(ZK::class, 'zk');
    }

    /**
     * @Desc Regiter Commands
     */
    protected function registerCommands()
    {
        $this->commands([
            ZookeeperServerCommand::class,
        ]);
    }

    /**
     * @Desc Create Zookeeper server
     */
    protected function createZookeeperServer()
    {
        $config = $this->app->make('config')->get('zk_config');
        static::$server = new Zk($this->app, 'laravel', $config);
    }
}
