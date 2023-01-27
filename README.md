# [unstable] szado/reactphp-connection-pool

Async and flexible pool for any type of connections built on top of [ReactPHP](https://reactphp.org/).

Connection pooling allows you to easily manage range of connections with some remote service (e.g. a database server). You can define how many connections your app can estabilish or how to react when all connections are busy at the same time.

- **State monitoring** - each adapter maintains its connection state ("Ready", "Busy" or "Disconnected"). Based on this, the connection selector can determine if the specific connection is currently ready to use. This is especially useful when you use stateful operations (e.g. database transactions).
- **Flexible** - manage any type of connections by implementing your own connection adapter and specify how connections are to be selected for use by passing proper connection selector.
- **Lightweight and simple** - in assumptions it is a simple component that can be freely extended according to your preferences.

## Requirements

- PHP >= 8.1 (fibers, enums)

## Examples

```php
class MyConnectionAdapter implements Szado\React\ConnectionPool\ConnectionAdapters\ConnectionAdapterInterface
{
  // Implementation of adapter for your connection.
}

$pool = new Szado\React\ConnectionPool\ConnectionPool(fn () => new MyConnectionAdapter());
$adapter = React\Async\await($pool->get());
$connection = React\Async\await($adapter->getConnection());
// `$connection` is ready to use :)
```

## Additional Configuration

You can pass additional parameters to pool constructor:

- `connectionSelectorClass` - define algorithm used for selecting connections (by default simple `UsageConnectionSelector` is used).
- `connectionsLimit` - maximum number of connections that can be created (`null` for unlimited).
- `retryLimit` - how many times try to search for an active connection before rejecting (`null` for unlimited, `0` for immediately rejection if none at the moment).
- `retryEverySec` - check for available connections every how many seconds (only if $retryLimit is enabled).
- `loop` - instance of `React\EventLoop\LoopInterface` to use.

## Todo
- Built-in adapters (`clue/reactphp-redis`, `friends-of-reactphp/mysql`).
- Connection selector based on Round Robin algorithm.
- Reconnection.

## At the end...
- Run tests: `./vendor/bin/phpunit`
- Feel free to submit your PR
- Licence: MIT
