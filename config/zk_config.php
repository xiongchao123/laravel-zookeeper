<?php
/**
 * zookeeper_config.
 * User: ly
 * Date: 2019/5/16
 * Time: 18:23
 */

return [
    'host' => env('ZK_HOST', '127.0.0.1:2181'),
    'is_watch' => false,
    'watcher' => '',  // e.g.
    'watch_recv_timeout' => 10000,
//    'path' => env('ZK_PATH', ''),
	'cache' => storage_path('zookeeper')
];