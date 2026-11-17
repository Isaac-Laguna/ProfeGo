<?php

declare(strict_types=1);

// Local database configuration for the USBWebServer environment. These
// defaults mirror the credentials previously hardcoded in
// database/database.php (already public in the repository). Deployments can
// override them through PROFEGO_DB_* environment variables instead of
// editing this file. Never expose these values in client-side code or logs.

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    getenv('PROFEGO_DB_HOST') ?: 'localhost',
    getenv('PROFEGO_DB_NAME') ?: 'profeGo'
);

return [
    'dsn' => $dsn,
    'user' => getenv('PROFEGO_DB_USER') ?: 'root',
    'password' => getenv('PROFEGO_DB_PASSWORD') ?: 'usbw',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
