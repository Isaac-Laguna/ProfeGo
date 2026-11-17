<?php

// Compatibility adapter: existing includes of this file must keep receiving
// the shared $pdo connection. Credentials and options now live in the
// includes/config.php + includes/database.php factory.

require_once __DIR__ . '/../includes/database.php';

$pdo = profego_pdo();
