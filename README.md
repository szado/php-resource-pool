# shado/php-resource-pool

A PHP library providing resource pooling support, commonly used as a database connection pool.

Resource pooling allows you to easily manage a range of concurrently maintained resources. You can define how many of them can be created or which algorithm should be used for selecting the next resource from the pool.

The library includes two implementations of the pool:

- `ResourcePool` – provides basic pooling logic, including borrowing and returning resources. 
- `AsyncResourcePool` – a [ReactPHP](https://reactphp.org/)-based implementation that adds resource retry functionality to the basic features.

This library is resource-agnostic, meaning you can use it with any type of resource, such as database connections, file handles, or network sockets.

## Installation

You can install the library using Composer:

```bash
composer require shado/php-resource-pool
```

## Requirements

- PHP >= 8.1

> [!TIP]
> Thanks to Fibers, you can freely use the ReactPHP-based implementation in your traditional PHP projects.
> 
> New to ReactPHP? Check out the [ReactPHP documentation](https://reactphp.org/).

## Example

### Basic usage of the `ResourcePool`

```php
$factory = function (\Shado\ResourcePool\FactoryController $controller) {
    $newConnection = new DbConnection();
    $newConnection->onClose($controller->detach(...));
    $newConnection->onError($controller->detach(...));
    return $newConnection;
};

$pool = new \Shado\ResourcePool\ResourcePool($factory, limit: 10);

$connection = $pool->borrow(); // `$connection` is ready to use :)

try {
// $connection->query(...);
} finally {
    $pool->return($connection);
}
```

The factory function is responsible for creating new resources. It receives a FactoryController which can be used to 
detach the resource from the pool (e.g., when it closes or an error occurs).

In this example, the pool lazily creates new resources using the factory function and limits the number 
of concurrently maintained resources to 10.

If all resources are in use, the `borrow` method throws an `Shado\ResourcePool\Exceptions\ResourceSelectingException`.

### Using the retry functionality with `AsyncResourcePool`

```php
$factory = function (\Shado\ResourcePool\FactoryController $controller) {
    $newConnection = new DbConnection();
    $newConnection->onClose($controller->detach(...));
    $newConnection->onError($controller->detach(...));
    return $newConnection;
};

$pool = new \Shado\ResourcePool\AsyncResourcePool(
    new \Shado\ResourcePool\ResourcePool($factory, limit: 10),
    retryingTimeout: null,
    retryingDelay: 0.01
);

$connection = $pool->borrow(); // `$connection` is ready to use :)
// ...or you can make use of the Promise:
// $connection = \React\Async\await($pool->borrowAsync());

try {
    // $connection->query(...);
} finally {
    $pool->return($connection);
}
```

After wrapping the `ResourcePool` with the `AsyncResourcePool`, you can use the retry functionality. 

If all resources are in use, the `borrow` method waits up to the specified `retryingTimeout` (or indefinitely if set to `null`) 
and retry borrowing a resource after the specified `retryingDelay`.

## At the end...
- Run tests: `./vendor/bin/phpunit tests`.
- Feel free to create an issue or submit your PR! 🤗
- Licence: MIT.
