<?php

$ms = fn ($t) => round((microtime(true) - $t) * 1000) . 'ms';

$conn = config('database.connections.mysql');
echo 'db host(write)=' . $conn['host'] . ' read=' . ($conn['read']['host'] ?? '(none)') . ' write=' . ($conn['write']['host'] ?? '(none)') . PHP_EOL;

$t = microtime(true);
try {
    new PDO('mysql:host=mysql-primary;port=3306;dbname=' . $conn['database'], $conn['username'], $conn['password'], [PDO::ATTR_TIMEOUT => 5]);
    echo 'primary raw connect: ' . $ms($t) . PHP_EOL;
} catch (\Throwable $e) {
    echo 'primary ERR: ' . $e->getMessage() . PHP_EOL;
}

$t = microtime(true);
try {
    new PDO('mysql:host=mysql-replica;port=3306;dbname=' . $conn['database'], $conn['username'], $conn['password'], [PDO::ATTR_TIMEOUT => 5]);
    echo 'replica raw connect: ' . $ms($t) . PHP_EOL;
} catch (\Throwable $e) {
    echo 'replica ERR: ' . $e->getMessage() . PHP_EOL;
}

$t = microtime(true);
try {
    $r = \Illuminate\Support\Facades\Redis::ping();
    echo 'redis ping: ' . $ms($t) . ' -> ' . var_export($r, true) . PHP_EOL;
} catch (\Throwable $e) {
    echo 'redis ERR: ' . $e->getMessage() . PHP_EOL;
}

foreach (['select 1 as x', 'select count(*) as c from products'] as $sql) {
    $t = microtime(true);
    try {
        \Illuminate\Support\Facades\DB::connection('mysql')->select($sql);
        echo "db read [$sql]: " . $ms($t) . PHP_EOL;
    } catch (\Throwable $e) {
        echo "db read ERR [$sql]: " . $e->getMessage() . PHP_EOL;
    }
}

$t = microtime(true);
try {
    \Illuminate\Support\Facades\DB::connection('mysql')->statement('select 1');
    echo 'db write-path: ' . $ms($t) . PHP_EOL;
} catch (\Throwable $e) {
    echo 'db write ERR: ' . $e->getMessage() . PHP_EOL;
}
