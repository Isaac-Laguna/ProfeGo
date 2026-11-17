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

if (!is_file($root . '/includes/csrf.php')) {
    fwrite(STDERR, "FAIL: includes/csrf.php does not exist yet.\n");
    exit(1);
}

require_once $root . '/includes/csrf.php';

// Session-bound CSRF primitive (RF-9). The session is a simulated in-memory
// array: no real cookies are used and no database data is involved, because
// the token is session-bound only.

check(function_exists('ensure_csrf_token'), 'ensure_csrf_token() is defined');
check(function_exists('submitted_csrf_is_valid'), 'submitted_csrf_is_valid() is defined');

$session = [];
$token = ensure_csrf_token($session);
check(
    is_string($token) && preg_match('/^[0-9a-f]{64}$/', $token) === 1,
    'token is exactly 64 lowercase hex characters'
);
check(
    isset($session['csrf_token']) && $session['csrf_token'] === $token,
    'token is stored under the fixed session key'
);
check(ensure_csrf_token($session) === $token, 'token is stable across repeated calls');
check(ensure_csrf_token($session) === $token, 'token is unchanged after repeated issuance');

// A malformed stored value (missing key or garbage) must be regenerated.
$emptySession = [];
check(
    preg_match('/^[0-9a-f]{64}$/', ensure_csrf_token($emptySession)) === 1,
    'a missing stored value is regenerated as a valid token'
);
$corruptedSession = ['csrf_token' => 'garbage'];
$regenerated = ensure_csrf_token($corruptedSession);
check(
    preg_match('/^[0-9a-f]{64}$/', $regenerated) === 1,
    'a malformed stored value is regenerated as a valid token'
);
check(
    $corruptedSession['csrf_token'] === $regenerated && $regenerated !== 'garbage',
    'the regenerated value replaces the malformed stored value'
);

// Acceptance and rejection matrix for submitted_csrf_is_valid().
check(
    submitted_csrf_is_valid($session, $token) === true,
    'a matching token is accepted'
);

$foreignSession = [];
$foreignToken = ensure_csrf_token($foreignSession);
check(
    submitted_csrf_is_valid($session, $foreignToken) === false,
    'a token from a foreign session is rejected'
);

$emptyKeySession = [];
check(
    submitted_csrf_is_valid($emptyKeySession, $token) === false,
    'a submission against a session without a token is rejected'
);
check(submitted_csrf_is_valid($session, '') === false, 'an empty string is rejected');
check(submitted_csrf_is_valid($session, substr($token, 0, 63)) === false, 'a wrong-length token is rejected');
check(submitted_csrf_is_valid($session, $token . 'a') === false, 'an oversized token is rejected');
check(
    submitted_csrf_is_valid($session, str_repeat('g', 64)) === false,
    'a non-hex token is rejected'
);
check(
    submitted_csrf_is_valid($session, strtoupper($token)) === false,
    'an uppercase variant of the token is rejected'
);

// Non-string submissions must be rejected without triggering PHP type errors
// (strict_types is on; hash_equals would throw on int).
check(submitted_csrf_is_valid($session, ['token' => $token]) === false, 'an array submission is rejected');
check(submitted_csrf_is_valid($session, 123) === false, 'an int submission is rejected');
check(submitted_csrf_is_valid($session, null) === false, 'a null submission is rejected');
check(submitted_csrf_is_valid($session, true) === false, 'a bool submission is rejected');

// Validation must never mutate the session (no rotation, no re-issuance).
$sessionBefore = $session;
submitted_csrf_is_valid($session, 'not-a-token');
submitted_csrf_is_valid($session, $foreignToken);
check(submitted_csrf_is_valid($session, $token) === true, 'the token remains valid after rejections');
check(
    $session === $sessionBefore,
    'validation does not mutate the session'
);

// The legacy include contract: database/database.php still yields $pdo, and
// it is the exact same shared connection, with no duplicated credentials.
$pdo = null;
require $root . '/database/database.php';
check($pdo instanceof PDO, 'including database/database.php still yields $pdo');
check($pdo === $factoryConnection, 'the adapter $pdo is the same profego_pdo() connection');

echo "\n";
printf("Assertions: %d, Failures: %d\n", $assertions, $failures);
exit($failures === 0 ? 0 : 1);
