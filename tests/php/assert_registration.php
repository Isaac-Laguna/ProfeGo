<?php

declare(strict_types=1);

/*
 * Layer 2 integration harness for spec 001 (create-account).
 *
 * Exercises the real registration domain service through the application's
 * own PDO connection (profego_pdo()). Every writing scenario uses the RNF-7
 * per-run token, captures only the identifiers it created, and cleans them up
 * in a finally block: tutor profiles before accounts, followed by a
 * zero-residue assertion by captured identifiers. Tables are never truncated,
 * the pre-existing empty prototype row is never read for assertions or
 * touched, and all scenarios run sequentially in one process (no parallel
 * writing tests).
 *
 * CLI-only: a direct HTTP request returns 404.
 */

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

// Test-first red state: the domain service must not exist yet on first run.
if (!is_file($root . '/includes/registration_service.php')) {
    fwrite(STDERR, "FAIL: includes/registration_service.php does not exist yet.\n");
    exit(1);
}

require_once $root . '/includes/database.php';
require_once $root . '/includes/validation.php';
require_once $root . '/includes/registration_service.php';
require_once $root . '/tests/php/test_support.php';

$pdo = profego_pdo();

const TEST_PASSWORD = 'Clave12345';
const WRONG_PASSWORD = 'OtraClave999';
const DUPLICATE_EMAIL_MESSAGE = 'Este correo electrónico ya está registrado.';
const DUPLICATE_PHONE_MESSAGE = 'Este teléfono ya está registrado.';
const DUPLICATE_ID_MESSAGE = 'Esta cédula o ID ya está registrada.';
const SQLI_NAME = "O'Brien' OR 'a' LIKE 'a'--";

/**
 * Build a complete, valid value set for one run token; overrides let a single
 * field be replaced by an already-registered value for duplicate scenarios.
 */
function test_values(string $token, array $overrides = []): array
{
    $values = [
        'name' => token_person_name($token),
        'lastname' => token_person_name($token, 'Apellido de Prueba'),
        'email' => token_email($token),
        'phone' => token_phone($token),
        'id' => token_document($token),
        'password' => TEST_PASSWORD,
        'password_confirm' => TEST_PASSWORD,
        'role' => 'estudiante',
    ];

    return array_merge($values, $overrides);
}

/** @return array{email: string, id: string, phone: string} */
function identifiers_for(array $values): array
{
    return [
        'email' => $values['email'],
        'id' => $values['id'],
        'phone' => $values['phone'],
    ];
}

/**
 * Count only real test rows: the pre-existing prototype row has correo = ''.
 * Counting it in aggregate would be reading it for assertions, so it is
 * deliberately excluded.
 *
 * @return array{0: int, 1: int} accounts, tutor profiles
 */
function row_counts(PDO $pdo): array
{
    $users = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE correo <> ''")->fetchColumn();
    $profiles = (int) $pdo->query('SELECT COUNT(*) FROM perfiles_docente')->fetchColumn();

    return [$users, $profiles];
}

/** Read back a full account row (including the hash) through the application PDO. */
function read_account_row(PDO $pdo, string $email): ?array
{
    $statement = $pdo->prepare('SELECT * FROM usuarios WHERE correo = :correo');
    $statement->execute([':correo' => $email]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function read_tutor_profile(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT * FROM perfiles_docente WHERE id_docente = :id');
    $statement->execute([':id' => $id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

/** Assert one duplicate pre-check scenario returns one message per field and writes nothing. */
function assert_duplicates(PDO $pdo, array $values, array $expected, string $label): void
{
    $before = row_counts($pdo);
    $duplicates = find_duplicate_fields($pdo, $values);
    $after = row_counts($pdo);

    check($before === $after, $label . ': row counts unchanged');
    check(count($duplicates) === count($expected), $label . ': exactly one error per duplicated field');
    foreach ($expected as $field => $message) {
        check(($duplicates[$field] ?? null) === $message, $label . ': ' . $field . ' duplicate message');
    }
}

echo "Layer 2 registration integration (single process; writing tests are not run in parallel)\n";
check(function_exists('find_duplicate_fields'), 'find_duplicate_fields() is defined by the service');
check(function_exists('register_account'), 'register_account() is defined by the service');

$baselineCounts = row_counts($pdo);
printf(
    "Baseline counted rows (empty prototype row excluded): %d accounts, %d tutor profiles\n\n",
    $baselineCounts[0],
    $baselineCounts[1]
);

$capturedSets = [];

try {
    // --- Scenario A: student persistence, normalization, hash round trip (AC-01) ---
    $tokenA = per_run_token();
    $valuesA = test_values($tokenA);
    $beforeA = row_counts($pdo);
    $resultA = register_account($pdo, $valuesA);
    $afterA = row_counts($pdo);

    check($resultA['ok'] === true, 'student registration returns ok=true');
    check(isset($resultA['id']) && (int) $resultA['id'] > 0, 'student registration returns a positive id');
    check($afterA === [$beforeA[0] + 1, $beforeA[1]], 'student registration adds exactly one account and no profile');

    $rowA = read_account_row($pdo, $valuesA['email']);
    check($rowA !== null, 'student account is readable back through the application PDO path');
    check((int) ($rowA['id'] ?? 0) === (int) $resultA['id'], 'read-back id matches the returned id');
    check(($rowA['nombre'] ?? null) === $valuesA['name'], 'name stored normalized');
    check(($rowA['apellido'] ?? null) === $valuesA['lastname'], 'lastname stored normalized');
    check(($rowA['correo'] ?? null) === $valuesA['email'], 'email stored in lowercase');
    check(($rowA['correo'] ?? '') === strtolower((string) ($rowA['correo'] ?? '')), 'stored email is lowercase');
    check(($rowA['telefono'] ?? null) === $valuesA['phone'], 'phone stored as digits only');
    check(preg_match('/\A[0-9]{7,20}\z/', (string) ($rowA['telefono'] ?? '')) === 1, 'stored phone is 7-20 ASCII digits');
    check(($rowA['documento'] ?? null) === $valuesA['id'], 'Cedula/ID stored exactly as typed');
    check(($rowA['rol'] ?? null) === 'estudiante', 'student row has rol=estudiante');
    check(($rowA['estado'] ?? null) === 'activo', 'student row uses the activo default');
    check(($rowA['fecha_registro'] ?? null) !== null, 'student row has a non-null fecha_registro');

    $hashA = (string) ($rowA['password_hash'] ?? '');
    check($hashA !== '', 'a password hash is stored');
    check($hashA !== TEST_PASSWORD, 'stored hash differs from the plaintext password');
    check(password_verify(TEST_PASSWORD, $hashA) === true, 'stored hash verifies the correct password');
    check(password_verify(WRONG_PASSWORD, $hashA) === false, 'stored hash rejects an incorrect password');
    $capturedSets[] = identifiers_for($valuesA);

    // --- Scenario B: tutor account plus pending profile (AC-02) ---
    $tokenB = per_run_token();
    $valuesB = test_values($tokenB, ['role' => 'docente']);
    $beforeB = row_counts($pdo);
    $resultB = register_account($pdo, $valuesB);
    $afterB = row_counts($pdo);

    check($resultB['ok'] === true, 'tutor registration returns ok=true');
    check($afterB === [$beforeB[0] + 1, $beforeB[1] + 1], 'tutor registration adds one account and one profile');

    $rowB = read_account_row($pdo, $valuesB['email']);
    check(($rowB['rol'] ?? null) === 'docente', 'tutor account has rol=docente');
    check(($rowB['estado'] ?? null) === 'activo', 'tutor account uses the activo default');
    check(($rowB['fecha_registro'] ?? null) !== null, 'tutor account has a non-null fecha_registro');

    $profileB = read_tutor_profile($pdo, (int) $resultB['id']);
    check($profileB !== null, 'tutor profile row exists for the new account');
    check((int) ($profileB['id_docente'] ?? 0) === (int) $resultB['id'], 'tutor profile references the new account id');
    check(($profileB['estado_verificacion'] ?? null) === 'pendiente', 'tutor profile state is pendiente');
    check((float) ($profileB['calificacion_prom'] ?? -1) === 0.0, 'tutor profile rating is numeric 0.00');
    check((int) ($profileB['total_tutoria'] ?? -1) === 0, 'tutor profile total_tutoria is 0');
    $capturedSets[] = identifiers_for($valuesB);

    // --- Duplicate pre-checks: email, phone, Cedula, combined (AC-05 zero-row part) ---
    $tokenC = per_run_token();
    assert_duplicates(
        $pdo,
        test_values($tokenC, ['email' => $valuesA['email']]),
        ['email' => DUPLICATE_EMAIL_MESSAGE],
        'duplicate email'
    );

    $tokenD = per_run_token();
    assert_duplicates(
        $pdo,
        test_values($tokenD, ['phone' => $valuesA['phone']]),
        ['phone' => DUPLICATE_PHONE_MESSAGE],
        'duplicate phone'
    );

    $tokenE = per_run_token();
    assert_duplicates(
        $pdo,
        test_values($tokenE, ['id' => $valuesA['id']]),
        ['id' => DUPLICATE_ID_MESSAGE],
        'duplicate Cedula/ID'
    );

    $tokenF = per_run_token();
    assert_duplicates(
        $pdo,
        test_values($tokenF, [
            'email' => $valuesA['email'],
            'phone' => $valuesA['phone'],
            'id' => $valuesA['id'],
        ]),
        [
            'email' => DUPLICATE_EMAIL_MESSAGE,
            'phone' => DUPLICATE_PHONE_MESSAGE,
            'id' => DUPLICATE_ID_MESSAGE,
        ],
        'combined duplicates'
    );

    // --- Case-insensitive duplication (behavioral proof of RF-6 uniqueness) ---
    $tokenG = per_run_token();
    assert_duplicates(
        $pdo,
        test_values($tokenG, ['email' => strtoupper($valuesA['email'])]),
        ['email' => DUPLICATE_EMAIL_MESSAGE],
        'case-insensitive email duplicate'
    );

    $tokenH = per_run_token();
    $documentLower = token_document($tokenH);
    $documentUpper = strtoupper($documentLower);
    $valuesH = test_values($tokenH, ['id' => $documentUpper]);
    $resultH = register_account($pdo, $valuesH);
    check($resultH['ok'] === true, 'account with uppercase Cedula/ID is created');
    $rowH = read_account_row($pdo, $valuesH['email']);
    check(($rowH['documento'] ?? null) === $documentUpper, 'Cedula/ID is stored exactly as typed (uppercase)');
    $capturedSets[] = identifiers_for($valuesH);

    $tokenHProbe = per_run_token();
    assert_duplicates(
        $pdo,
        test_values($tokenHProbe, ['id' => $documentLower]),
        ['id' => DUPLICATE_ID_MESSAGE],
        'case-insensitive Cedula/ID duplicate'
    );

    // --- Database unique-collision path: bypass the pre-check on purpose ---
    $tokenI = per_run_token();
    $collisionValues = test_values($tokenI, [
        'email' => $valuesA['email'],
        'phone' => $valuesA['phone'],
        'id' => $valuesA['id'],
    ]);
    $beforeCollision = row_counts($pdo);
    $originalIds = captured_account_ids($pdo, ['email' => $valuesA['email']]);
    $resultI = register_account($pdo, $collisionValues);
    $afterCollision = row_counts($pdo);
    check($resultI['ok'] === false, 'SQLSTATE 23000 collision returns ok=false');
    check(is_array($resultI['duplicates'] ?? null), 'collision result carries a duplicates map');
    check(count($resultI['duplicates'] ?? []) === 3, 'collision maps all three duplicated fields');
    check(($resultI['duplicates']['email'] ?? null) === DUPLICATE_EMAIL_MESSAGE, 'collision email message matches');
    check(($resultI['duplicates']['phone'] ?? null) === DUPLICATE_PHONE_MESSAGE, 'collision phone message matches');
    check(($resultI['duplicates']['id'] ?? null) === DUPLICATE_ID_MESSAGE, 'collision Cedula message matches');
    check($beforeCollision === $afterCollision, 'collision leaves row counts unchanged');
    check(
        captured_account_ids($pdo, ['email' => $valuesA['email']]) === $originalIds,
        'collision leaves no partial account row'
    );
    check($pdo->inTransaction() === false, 'collision leaves no open transaction');

    // --- Atomicity: CLI-only rollback seam leaves neither account nor profile (RF-3) ---
    $tokenJ = per_run_token();
    $valuesJ = test_values($tokenJ, ['role' => 'docente']);
    $beforeJ = row_counts($pdo);
    $seamThrew = false;
    try {
        register_account($pdo, $valuesJ, ['fail_tutor_profile' => true]);
    } catch (Throwable $exception) {
        $seamThrew = true;
        check(
            str_contains($exception->getMessage(), 'forced tutor profile failure'),
            'CLI seam throws the forced tutor-profile failure'
        );
    }
    check($seamThrew === true, 'CLI rollback seam aborts the registration');
    check(row_counts($pdo) === $beforeJ, 'rollback seam leaves no account and no tutor profile');
    check(captured_account_ids($pdo, identifiers_for($valuesJ)) === [], 'no account survives the forced failure');
    check($pdo->inTransaction() === false, 'rollback seam leaves no open transaction');
    $capturedSets[] = identifiers_for($valuesJ);

    // --- Collation verification (environmental finding; no schema change) ---
    $statement = $pdo->prepare(
        "SELECT COLLATION_NAME FROM information_schema.COLUMNS "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'documento'"
    );
    $statement->execute();
    $columnCollation = $statement->fetchColumn();

    $statement = $pdo->prepare(
        'SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()'
    );
    $statement->execute();
    $databaseCollation = $statement->fetchColumn();

    check(
        is_string($columnCollation)
            && str_starts_with($columnCollation, 'utf8mb4_')
            && str_ends_with($columnCollation, '_ci'),
        'usuarios.documento uses a utf8mb4 case-insensitive (_ci) collation'
    );
    check($databaseCollation === 'utf8mb4_unicode_ci', 'database default collation is utf8mb4_unicode_ci');
    printf(
        "INFO: usuarios.documento collation = %s; database default = %s; "
        . "tables inherit utf8mb4_general_ci from DEFAULT CHARSET=utf8mb4; no schema change was made.\n\n",
        $columnCollation,
        $databaseCollation
    );

    // --- Allowed-character SQL-injection payload stored and read back literally (AC-08a) ---
    check(strlen(SQLI_NAME) === 26, 'SQLi payload is 26 characters long');
    check(mb_strlen(SQLI_NAME) <= 30, 'SQLi payload stays within the 30-character name bound');
    check(preg_match('/^[\p{L} \'-]+$/u', SQLI_NAME) === 1, 'SQLi payload uses only characters allowed in a name');

    $tokenL = per_run_token();
    $valuesL = test_values($tokenL, ['name' => SQLI_NAME]);
    $unrelatedBefore = read_account_row($pdo, $valuesA['email']);
    $beforeL = row_counts($pdo);
    $resultL = register_account($pdo, $valuesL);
    $afterL = row_counts($pdo);

    check($resultL['ok'] === true, 'registration with the SQLi payload succeeds');
    check($afterL === [$beforeL[0] + 1, $beforeL[1]], 'SQLi registration adds exactly one account and no profile');
    $rowL = read_account_row($pdo, $valuesL['email']);
    check(($rowL['nombre'] ?? null) === SQLI_NAME, 'SQLi payload stored literally in the name column');
    $appRowL = find_account_by_email($pdo, $valuesL['email']);
    check(($appRowL['nombre'] ?? null) === SQLI_NAME, 'SQLi payload read back through the application data path');
    $unrelatedAfter = read_account_row($pdo, $valuesA['email']);
    check($unrelatedAfter === $unrelatedBefore, 'an unrelated account row is unaffected by the SQLi registration');
    $capturedSets[] = identifiers_for($valuesL);
} finally {
    echo "\nCleanup (captured identifiers only; tutor profiles before accounts):\n";
    foreach ($capturedSets as $index => $set) {
        $cleanup = delete_captured_rows($pdo, $set);
        printf(
            "  set %d: profiles=%d, accounts=%d, residue=%d\n",
            $index + 1,
            $cleanup['profiles'],
            $cleanup['accounts'],
            $cleanup['residue']
        );
        check($cleanup['residue'] === 0, 'cleanup set ' . ($index + 1) . ' leaves zero residue');
    }

    $totalResidue = 0;
    foreach ($capturedSets as $set) {
        $totalResidue += count_captured_rows($pdo, $set);
    }
    check($totalResidue === 0, 'final zero-residue assertion by captured identifiers');
    check(row_counts($pdo) === $baselineCounts, 'row counts restored to baseline after cleanup');

    echo "\n";
    printf("Assertions: %d, Failures: %d\n", $assertions, $failures);
}

exit($failures === 0 ? 0 : 1);
