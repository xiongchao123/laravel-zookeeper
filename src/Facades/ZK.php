<?php

namespace BigBoom\Zookeeper\Facades;

use Illuminate\Support\Facades\Facade;

class ZK extends Facade
{
    protected static function getFacadeAccessor ()
    {
        return 'zk';
    }
}