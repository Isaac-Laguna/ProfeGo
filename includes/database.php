<?php

declare(strict_types=1);

// Single shared PDO connection. Web requests and CLI verification layers must
// all obtain their connection here so the application has one persistence
// path and credentials are never duplicated across call sites.

function profego_pdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/config.php';
        $pdo = new PDO($config['dsn'], $config['user'], $config['password'], $config['options']);
    }

    return $pdo;
}
