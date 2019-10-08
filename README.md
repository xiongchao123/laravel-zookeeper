# laravel-zookeeper

## Installation

`composer require xiongchao/laravel-zookeeper`

If using Laravel, add the Service Provider to the `providers` array in `config/app.php`:
``` php
    [
        'providers' => [
            BigBoom\Zookeeper\ZookeeperServiceProvider::class,
        ],   
    ]
```

If using Lumen, appending the following line to `bootstrap/app.php`:

``` php
    $app->register(BigBoom\Zookeeper\ZookeeperServiceProvider::class);
```

If you need use Laravel Facades, add the `aliases` array in `config/app.php`:
``` php
    [
        'aliases' => [
                'Zk' => BigBoom\Zookeeper\Facades\Zk::class,
        ],
    ]
```

## Using
```php
//example
<?php
use BigBoom\Zookeeper\Facades\Zk;

class TestController extends controller {

    public function test ()
    {
        $nodeValue = ZK::getNodeData('');
    }
}

```


