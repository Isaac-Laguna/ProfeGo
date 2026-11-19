<?php

declare(strict_types=1);

/*
 * Thin HTTP adapter for the registration page (spec 001).
 *
 * Request handling, validation, persistence and rendering all live in
 * includes/register_controller.php. This file only starts the session,
 * forwards the request superglobals to the shared pipeline and emits the
 * returned status, headers and body. The old prototype INSERT and inline
 * markup were removed so the page is a single entry point for the pipeline.
 */

session_start();

require_once __DIR__ . '/../includes/register_controller.php';

$response = build_registration_response($_SERVER, $_POST, $_SESSION, $_GET);

http_response_code($response['status']);

foreach ($response['headers'] as $header) {
    header($header);
}

if ($response['status'] === 303) {
    exit;
}

echo $response['body'];
