# QueryCache

A simple query caching layer built around
[PDO](http://php.net/manual/en/class.pdo.php).
It can cache to [Memcached](http://php.net/manual/en/class.memcached.php),
[APCu](http://php.net/manual/en/ref.apcu.php),
local request cache, or anything that implements the [CacheInterface](https://github.com/thumbtack/querycache/blob/master/src/CacheInterface.php).

## Composer Install:
```
"require": {
    "thumbtack/querycache": "^0.1"
}
```

## Basic Querying:
The basic `Query` interface is exposed through `read` and `write` methods,
which are wrappers around a common PDO data access patterns. `read` is used
for anything that is going to return a result set. `write` is used for
anything that does not return a result set, but might alter data.

```php
// initialize PDO object that Query will run on top of:
$dsn = 'mysql:dbname=testdb;host=127.0.0.1';
$pdo = new \PDO($dsn);
$query = new \QueryCache\Query($pdo);

$sql = 'SELECT id, name, email FROM users WHERE id = :id';
$params = [ ':id' => 1];
$results = $query->read($sql, $params);

//example response:
// $results = [
//   ['id' => 1, 'name' => 'Joe', 'email' => 'joe@example.com'],
// ];

$sql = 'SELECT id, name, email FROM users WHERE id IN (:id)';
$params = [ ':id' => [1, 2, 3] ];
$results = $query->read($sql, $params);

//example response:
// $results = [
//   ['id' => 1, 'name' => 'Joe', 'email' => 'joe@example.com'],
//   ['id' => 2, 'name' => 'Kim', 'email' => 'kim@example.com'],
//   ['id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com'],
// ];
```

The above example will create a
[prepared statement](http://php.net/manual/en/pdo.prepare.php) that *only*
uses named parameters. The parameters to bind get passed along. Since
`':id'` was an array in the 2nd query example it will expand to a list of
parameters to bind.

No caching is used in the above example.

## Cached Query Reads:
Cached queries work the same way as non-cached queries, except you specify
either a `result_set_cache` key/key template and/or `row_cache` key template
and pass that along in the 3rd parameter to `read` or `write`. A key template
is a string that has `:tokens` inside it. The `:tokens` should be identical
to at least a sub-set of the passed parameters. The tokens will be replaced
with individual values of parameters passed to the query. Here is the same
example as above, except with row level caching:

```php
// initialize PDO object that Query will run on top of:
$dsn = 'mysql:dbname=testdb;host=127.0.0.1';
$pdo = new \PDO($dsn);
$cache = new \QueryCache\LocalCache();
$query = new \QueryCache\Query($pdo, $cache);

$sql = 'SELECT id, name, email FROM users WHERE id = :id';
$params = [ ':id' => 1];
$options = [ 'row_cache' => '/users/:id' ];
$results = $query->read($sql, $params, $options);

//example response:
// $results = [
//   ['id' => 1, 'name' => 'Joe', 'email' => 'joe@example.com'],
// ];

$sql = 'SELECT id, name, email FROM users WHERE id IN (:id)';
$params = [ ':id' => [1, 2, 3] ];
$options = [ 'row_cache' => '/users/:id' ];
$results = $query->read($sql, $params);

//example response:
// $results = [
//   ['id' => 1, 'name' => 'Joe', 'email' => 'joe@example.com'],
//   ['id' => 2, 'name' => 'Kim', 'email' => 'kim@example.com'],
//   ['id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com'],
// ];
```

The above example defines a `row_cache` template string of `/users/:id`.
This lets us examine the passed parameters, build out all possible cache keys
for the passed parameters, and then see if we can satisfy the query from
cache. If we can we return the cached results. If we cannot we fall through
and perform the query logic. If we get a partial match, the parameters that
get passed to the final query are modified so that they only include
parameters for items that were not in cache. If we have to run a query we
will also cache the rows that get returned to their matching keys, so that
successive calls will hit cache.

A `row_cache` will cache each individual row returned in a result set, and
each key should evaluate to a string that will be unique for that row. A
`result_set_cache` caches an entire result set, and cannot fall through to
query the database if anything is found in cache.

Some other options exist when querying with a cache.
- `ttl` - this will set the ttl in seconds for any values that get cached
 during a call.
- `map` - this will convert a flat array result set into a map/associative
 array.
- `sort` - this is basically an SQL ORDER BY statement. We cannot guarantee
 the order the rows will be returned from cache when using row level caching.
 This allows us to enforce an ORDER.

Example using these parameters:

```php
// initialize PDO object that Query will run on top of:
$dsn = 'mysql:dbname=testdb;host=127.0.0.1';
$pdo = new \PDO($dsn);
$cache = new \QueryCache\LocalCache();
$query = new \QueryCache\Query($pdo, $cache);

$sql = 'SELECT id, name, email FROM users WHERE id IN (:id)';
$params = [ ':id' => [1, 2, 3] ];
$options = [
    'row_cache' => '/users/:id',
    'ttl' => 300,
    'map' => ['id', 'name'],
    'sort' => 'id DESC'
];
$results = $query->read($sql, $params);

//example response:
// $results = [
//   3 => [ 'Bob' => ['id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com'] ],
//   2 => [ 'Kim' => ['id' => 2, 'name' => 'Kim', 'email' => 'kim@example.com'] ],
//   1 => [ 'Joe' => ['id' => 1, 'name' => 'Joe', 'email' => 'joe@example.com'] ],
// ];
```

In the above, the `ttl` for the cached data is 300 seconds (5 minutes). We
`sort` the returned results by `id DESC`, and build a `map` from the
`id` and `name`.

You can also stack caches, so that you have layers of cache:
```php
// initialize PDO object that Query will run on top of:
$dsn = 'mysql:dbname=testdb;host=127.0.0.1';
$pdo = new \PDO($dsn);

$servers = [
    [ 'host' => '127.0.0.1', 'port' => 11211, 'weight' => 1, ]
];
$local = new \QueryCache\LocalCache();
$memcache = new \QueryCache\Memcache(['servers' => $servers]);
$stack = new \QueryCache\CacheStack([$local, $memcache]);

$query = new \QueryCache\Query($pdo, $stack);
```

This will read from `LocalCache` first, if it misses it will look at
`Memcache`. Then when writing, it will write to `Memcache` and follow that
up with an immediate write to `LocalCache`.

There is also a `CacheLog` that will log cache activity for a specific cache.

```php
// initialize PDO object that Query will run on top of:
$dsn = 'mysql:dbname=testdb;host=127.0.0.1';
$pdo = new \PDO($dsn);

$servers = [
    [
        'host' => '127.0.0.1',
        'port' => 11211,
        'weight' => 1,
    ]
];
$local = new \QueryCache\LocalCache();
$memcache = new \QueryCache\Memcache(['servers' => $servers]);
$logger = new Psr3Logger(); // not defined in this project
$logged_memcache = new \QueryCache\CacheLog($memcache, $logger);
$stack = new \QueryCache\CacheStack([$local, $logged_memcache]);

$query = new \QueryCache\Query($pdo, $stack);
```

This will log individual method calls to the `Memcache` object. If you do not
pass a psr-3 compatible logger to `CacheLog`, it will still keep track of all
the various cache activity and can be retrieved by calling
`CacheLog::GetActivityBuffer()`. This will return the amount of `calls` made
against the cache, overall `runtime` of the cache, then all individual call
`activity`. Each `activity` entry consists of `runtime` of the call, the
`class` called, the `method` called, the `keys` the method interacted with,
and how many of the keys in the call were a `hit` or a `miss`
(where appropriate).

## Cached Query Writes:

The interface for writing data is nearly identical to reading data, it just
has less options. You can only define a `row_cache` string/template and/or
a `result_set_cache` string/template. On write any cache keys we generate
will get evicted from cache.

Example write (building off previous example):
```php
// initialize PDO object that Query will run on top of:
$dsn = 'mysql:dbname=testdb;host=127.0.0.1';
$pdo = new \PDO($dsn);

$servers = [
    [
        'host' => '127.0.0.1',
        'port' => 11211,
        'weight' => 1,
    ]
];
$local = new \QueryCache\LocalCache();
$memcache = new \QueryCache\Memcache(['servers' => $servers]);
$logger = new Psr3Logger(); // not defined in this project
$logged_memcache = new \QueryCache\CacheLog($memcache, $logger);
$stack = new \QueryCache\CacheStack([$local, $logged_memcache]);

$query = new \QueryCache\Query($pdo, $stack);

$sql = 'UPDATE users SET name = :name, email = :email WHERE id = :id';
$params = [ ':name' => 'Sam', ':email' => 'sam@example.com', ':id' => 1 ];
$options = [ 'row_cache' => '/users/:id' ];
$query->write($sql, $params, $options);
```

The above will update the `users` table and when the update is done, it will
evict the `/users/1` cache key. So that the next time it is queried for it
will pull the updated value.


## TODOs:
- Make caching layer work with PSR-6 or update to a PSR-6 library.
- Add options for jitter, locking, and regeneration by eviction.
- Document additional options.
- Move things to CacheInterface that should be common across all implementors of CacheInterface.
- Build optional QueryOptions object so we can have better options type checks.
- Maybe support different [fetch modes](http://php.net/manual/en/pdostatement.setfetchmode.php),
  this makes the `map` and `sort` options harder to deal with.
