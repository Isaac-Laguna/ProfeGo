<?php

declare(strict_types=1);

// CLI-only guard: over HTTP this script must not be reachable (Layer 0 / RNF-7).
if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 404 Not Found');
    exit;
}

$assertions = 0;
$failures = 0;

function check(bool $condition, string $label): void
{
    global $assertions, $failures;

    $assertions++;
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
}

$root = dirname(__DIR__, 2);

if (!is_file($root . '/includes/database.php')) {
    fwrite(STDERR, "FAIL: includes/database.php does not exist yet.\n");
    exit(1);
}

require_once $root . '/includes/database.php';

// The factory is defined and returns a real, reusable PDO connection.
check(function_exists('profego_pdo'), 'profego_pdo() is defined');

$factoryConnection = profego_pdo();
check($factoryConnection instanceof PDO, 'profego_pdo() returns a PDO instance');
check(profego_pdo() === $factoryConnection, 'profego_pdo() reuses the same static connection');

// The configuration array exists and forces utf8mb4 without leaking values.
$config = require $root . '/includes/config.php';
check(is_array($config), 'includes/config.php returns an array');
check(
    isset($config['dsn'], $config['user'], $config['password'], $config['options']),
    'config exposes dsn, user, password, and options keys'
);
check(
    is_string($config['dsn']) && str_contains($config['dsn'], 'dbname=profeGo'),
    'config DSN keeps the profeGo database name'
);
check(
    is_string($config['dsn']) && str_contains($config['dsn'], 'charset=utf8mb4'),
    'config DSN forces charset=utf8mb4'
);

// The connection itself runs with utf8mb4 and the required PDO options.
$charsetRow = $factoryConnection
    ->query('SELECT @@character_set_client AS client, @@character_set_connection AS connection')
    ->fetch(PDO::FETCH_ASSOC);
check(is_array($charsetRow) && $charsetRow['client'] === 'utf8mb4', 'character_set_client is utf8mb4');
check(is_array($charsetRow) && $charsetRow['connection'] === 'utf8mb4', 'character_set_connection is utf8mb4');

check(
    $factoryConnection->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION,
    'connection uses PDO::ERRMODE_EXCEPTION'
);
check(
    $factoryConnection->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE) === PDO::FETCH_ASSOC,
    'connection defaults to PDO::FETCH_ASSOC'
);
// pdo_mysql reports ATTR_EMULATE_PREPARES as int 0/1, not bool.
check(
    $factoryConnection->getAttribute(PDO::ATTR_EMULATE_PREPARES) === 0,
    'connection disables emulated prepares'
);

// SELECT 1 proves the connection executes queries.
$one = $factoryConnection->query('SELECT 1')->fetchColumn();
check((int) $one === 1, 'SELECT 1 executes successfully');

// The legacy include contract: database/database.php still yields $pdo, and
// it is the exact same shared connection, with no duplicated credentials.
$pdo = null;
require $root . '/database/database.php';
check($pdo instanceof PDO, 'including database/database.php still yields $pdo');
check($pdo === $factoryConnection, 'the adapter $pdo is the same profego_pdo() connection');

echo "\n";
printf("Assertions: %d, Failures: %d\n", $assertions, $failures);
exit($failures === 0 ? 0 : 1);
