# shado/php-resource-pool

A PHP library providing basic resource pooling support, most commonly used as a database connection pool.

Resource pooling allows you to easily manage a range of concurrently maintained resources. You can define how many of them can be created or which algorithm should be used for selecting the next resource from the pool.

## Requirements

- PHP >= 8.1

## Example

```php
$factory = function (\Shado\ResourcePool\FactoryController $controller) {
    $newConnection = new DbConnection();
    $newConnection->onClose($controller->detach(...)); // When connection closes, detach it from pool
    return $newConnection;
};

$pool = new \Shado\ResourcePool\ResourcePool($factory, 10);

$connection = $pool->borrow(); // `$connection` is ready to use :)
// $connection->query(...);
$pool->return($connection);
```

## At the end...
- Run tests: `./vendor/bin/phpunit tests`.
- Feel free to create an issue or submit your PR! 🤗
- Licence: MIT.
