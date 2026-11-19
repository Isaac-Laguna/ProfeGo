<?php

declare(strict_types=1);

/*
 * CLI-only test-support helpers for spec 001 (create-account).
 *
 * Why this file exists (RNF-7, plan.md "Testing strategy"): every writing
 * test layer must use unique per-run identifiers, must clean up only the
 * rows it created, and must delete tutor profiles before their accounts.
 * These helpers centralize that mechanism so integration scripts and
 * post-browser-run verification share one implementation instead of
 * duplicating cleanup logic per script.
 *
 * The file is CLI-only: any direct HTTP request returns 404 so the helpers
 * can never be driven through the web server.
 *
 * Library use (from test scripts):
 *   per_run_token()                 unique "pid-time-hex8" identifier
 *   token_email($token)             unique email, token in the local part
 *   token_phone($token)             unique digits-only phone (19 digits)
 *   token_document($token)          unique cedula/ID (letters/digits/hyphens)
 *   token_person_name($token, $base) base name + unique letters-only suffix, <= 30 chars
 *   token_name_suffix($token)       the 8-letter suffix alone
 *   support_reset_assertions() / support_check() / support_failures() /
 *   support_print_summary()         assertion counter, same style as
 *                                   tests/php/assert_foundation.php
 *   captured_account_ids($pdo, $ids)         account IDs by captured identifiers
 *   count_captured_rows($pdo, $ids)          residue count (accounts + profiles)
 *   delete_captured_rows($pdo, $ids)         cleanup: profiles BEFORE accounts
 *   find_account_by_email($pdo, $email)      read-only lookup for inspect mode
 *
 * CLI modes (run from the repository root; except cleanup, which only ever
 * runs against rows a test created and captured, nothing here writes rows):
 *
 *   php tests/php/test_support.php smoke
 *       Token-generator self-check; prints two tokens and their encoded
 *       email/phone/cedula/name shapes. Pure computation, no database use.
 *
 *   php tests/php/test_support.php inspect --email=<captured email>
 *       Read-only account lookup: prints the account columns plus the tutor
 *       profile state. Never prints the password hash (RF-8).
 *
 *   php tests/php/test_support.php render --role=estudiante
 *       Prints ONLY the controller-rendered registration document for a
 *       simulated GET with the requested preselected role (estudiante/docente;
 *       any other or missing value renders estudiante). No database write.
 *       Used for recorded evidence of the server-rendered role signals.
 *
 *   php tests/php/test_support.php cleanup --email=<email> --id=<cedula/ID> [--phone=<phone>]
 *       Deletes the rows created by a test run, matching ONLY the captured
 *       identifiers (never a universal prefix, never a truncation): tutor
 *       profiles first, then their accounts, then reports zero residue.
 *
 * Cleanup ordering note: delete_captured_rows() runs the perfiles_docente
 * DELETE before the usuarios DELETE. The foreign key
 * perfiles_docente.id_docente -> usuarios.id forbids removing an account
 * that still has a tutor profile, so the profile delete must come first.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 404 Not Found');
    exit;
}

// ---------------------------------------------------------------------------
// Per-run identifier generation and field encodings
// ---------------------------------------------------------------------------

/**
 * Unique identifier for one test run: process id, timestamp, and 8 random
 * hex characters. Two successive calls return different values.
 */
function per_run_token(): string
{
    return getmypid() . '-' . time() . '-' . bin2hex(random_bytes(4));
}

/** @return array{0: string, 1: string, 2: string} pid, time, and hex parts */
function token_parts(string $token): array
{
    $parts = explode('-', $token, 3);
    if (count($parts) !== 3) {
        throw new InvalidArgumentException('Malformed per-run token.');
    }

    return $parts;
}

/**
 * Unique email for one run: the token sits in the local part. ASCII only,
 * lowercase, at most 150 characters, and valid per FILTER_VALIDATE_EMAIL.
 */
function token_email(string $token): string
{
    return 'run.' . $token . '@example.invalid';
}

/**
 * Unique digits-only phone for one run. Each hex character expands
 * injectively to two decimal digits ('0' -> '00' ... 'f' -> '15'), so
 * distinct tokens produce distinct phones. The "506" prefix plus the
 * 8-character expansion yields exactly 19 digits (7-20 are allowed).
 */
function token_phone(string $token): string
{
    [, , $hex] = token_parts($token);
    $digits = '506';
    foreach (str_split($hex) as $character) {
        $digits .= str_pad((string) hexdec($character), 2, '0', STR_PAD_LEFT);
    }

    return $digits;
}

/**
 * Unique cedula/ID for one run: "id-" + the last six digits of the token's
 * timestamp + "-" + the token's 8 hex characters. 18 characters of ASCII
 * letters, digits, and hyphens (5-20 are allowed). Lowercase by
 * construction, so case-insensitive uniqueness behaves like exact match.
 */
function token_document(string $token): string
{
    [, $time, $hex] = token_parts($token);

    return 'id-' . substr($time, -6) . '-' . $hex;
}

/**
 * Letters-only suffix for one run (names do not allow digits). Each hex
 * character maps injectively to a letter: 'a'-'f' stay, '0'-'9' map to
 * 'g'-'p'. Eight letters, so a realistic base name plus the suffix stays
 * within the 30-character name limit.
 */
function token_name_suffix(string $token): string
{
    [, , $hex] = token_parts($token);
    $letters = '';
    foreach (str_split($hex) as $character) {
        $letters .= ($character >= '0' && $character <= '9')
            ? chr(ord('g') + (int) $character)
            : $character;
    }

    return $letters;
}

/**
 * A realistic test name carrying the run's unique letters-only suffix.
 * The base must leave room for one space plus the 8-letter suffix.
 * (Named token_person_name() because token_name() is a PHP built-in.)
 */
function token_person_name(string $token, string $base = 'Estudiante de Prueba'): string
{
    $name = $base . ' ' . token_name_suffix($token);
    if (mb_strlen($name) > 30) {
        throw new InvalidArgumentException('Base name too long: the result must stay within 30 characters.');
    }

    return $name;
}

// ---------------------------------------------------------------------------
// Assertion counter (same style as tests/php/assert_foundation.php)
// ---------------------------------------------------------------------------

$supportAssertions = 0;
$supportFailures = 0;

function support_reset_assertions(): void
{
    global $supportAssertions, $supportFailures;

    $supportAssertions = 0;
    $supportFailures = 0;
}

function support_check(bool $condition, string $label): void
{
    global $supportAssertions, $supportFailures;

    $supportAssertions++;
    if ($condition) {
        echo "PASS: {$label}\n";

        return;
    }

    $supportFailures++;
    echo "FAIL: {$label}\n";
}

function support_failures(): int
{
    global $supportFailures;

    return $supportFailures;
}

function support_print_summary(): void
{
    global $supportAssertions, $supportFailures;

    printf("Assertions: %d, Failures: %d\n", $supportAssertions, $supportFailures);
}

// ---------------------------------------------------------------------------
// Captured-identifier lookup and cleanup (RNF-7)
// ---------------------------------------------------------------------------

/**
 * IDs of the accounts created by one test run, matched ONLY by the captured
 * identifiers (email and/or cedula/ID and/or phone). Never a prefix, never
 * a truncation. At least one identifier is required.
 *
 * The interpolated column names are fixed internal literals, never user
 * input; the values are bound as parameters.
 *
 * @param array $identifiers e.g. ['email' => ..., 'id' => ..., 'phone' => ...]
 * @return list<int>
 */
function captured_account_ids(PDO $pdo, array $identifiers): array
{
    $columns = ['email' => 'correo', 'id' => 'documento', 'phone' => 'telefono'];
    $clauses = [];
    $parameters = [];
    foreach ($columns as $key => $column) {
        if (isset($identifiers[$key]) && $identifiers[$key] !== '') {
            $clauses[] = "{$column} = :{$key}";
            $parameters[":{$key}"] = $identifiers[$key];
        }
    }
    if ($clauses === []) {
        throw new InvalidArgumentException('Cleanup requires at least one captured identifier.');
    }

    $statement = $pdo->prepare('SELECT id FROM usuarios WHERE ' . implode(' OR ', $clauses));
    $statement->execute($parameters);

    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN, 0));
}

/**
 * Number of test rows still present for the captured identifiers: the
 * accounts themselves plus any tutor profile belonging to them.
 */
function count_captured_rows(PDO $pdo, array $identifiers): int
{
    $ids = captured_account_ids($pdo, $identifiers);
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM perfiles_docente WHERE id_docente IN ({$placeholders})"
    );
    $statement->execute($ids);

    return count($ids) + (int) $statement->fetchColumn();
}

/**
 * Failure-safe cleanup of one test run's rows, matched only by the captured
 * identifiers. Order matters: tutor profiles are deleted BEFORE their
 * accounts, because perfiles_docente.id_docente references usuarios.id.
 * Tables are never truncated and no prefix matching is used.
 *
 * @return array{profiles: int, accounts: int, residue: int}
 */
function delete_captured_rows(PDO $pdo, array $identifiers): array
{
    $ids = captured_account_ids($pdo, $identifiers);
    $profilesDeleted = 0;
    $accountsDeleted = 0;
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Step 1: tutor profiles first; the FK
        // perfiles_docente.id_docente -> usuarios.id requires this order.
        $statement = $pdo->prepare(
            "DELETE FROM perfiles_docente WHERE id_docente IN ({$placeholders})"
        );
        $statement->execute($ids);
        $profilesDeleted = $statement->rowCount();

        // Step 2: accounts last, after every tutor profile above is gone.
        $statement = $pdo->prepare(
            "DELETE FROM usuarios WHERE id IN ({$placeholders})"
        );
        $statement->execute($ids);
        $accountsDeleted = $statement->rowCount();
    }

    return [
        'profiles' => $profilesDeleted,
        'accounts' => $accountsDeleted,
        'residue' => count_captured_rows($pdo, $identifiers),
    ];
}

/**
 * Read-only account lookup by email, including the tutor-profile state.
 * password_hash is deliberately not selected: it must never be exposed
 * (RF-8).
 */
function find_account_by_email(PDO $pdo, string $email): ?array
{
    $statement = $pdo->prepare(
        'SELECT u.id, u.nombre, u.apellido, u.correo, u.telefono, u.documento,
                u.rol, u.estado, u.fecha_registro,
                pd.estado_verificacion, pd.calificacion_prom, pd.total_tutoria
         FROM usuarios u
         LEFT JOIN perfiles_docente pd ON pd.id_docente = u.id
         WHERE u.correo = :email'
    );
    $statement->execute([':email' => $email]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

// ---------------------------------------------------------------------------
// CLI modes
// ---------------------------------------------------------------------------

function support_usage(): void
{
    fwrite(STDERR, <<<USAGE
    Usage:
      php tests/php/test_support.php smoke
      php tests/php/test_support.php render --role=estudiante
      php tests/php/test_support.php inspect --email=<captured email>
      php tests/php/test_support.php cleanup --email=<email> --id=<cedula/ID> [--phone=<phone>]

    USAGE);
}

// Included as a library by a test script: define the helpers, run no mode.
if (!isset($argv[0]) || realpath($argv[0]) !== realpath(__FILE__)) {
    return;
}

$mode = $argv[1] ?? '';
$flags = [];
foreach (array_slice($argv, 2) as $argument) {
    $argument = (string) $argument;
    if (preg_match('/^--([a-z_]+)=(.*)$/s', $argument, $match) === 1) {
        $flags[$match[1]] = $match[2];

        continue;
    }
    fwrite(STDERR, "Unknown argument: {$argument}\n");
    support_usage();
    exit(2);
}

require_once dirname(__DIR__, 2) . '/includes/database.php';

switch ($mode) {
    case 'render':
        require_once dirname(__DIR__, 2) . '/includes/register_controller.php';
        $requestedRole = $flags['role'] ?? 'estudiante';
        $role = is_allowed_registration_role($requestedRole) ? $requestedRole : 'estudiante';
        $session = [];
        $response = build_registration_response(
            ['REQUEST_METHOD' => 'GET'],
            [],
            $session,
            ['rol' => $role]
        );
        echo $response['body'];
        exit(0);

    case 'smoke':
        support_reset_assertions();
        echo "Per-run token smoke check (pure computation; no database use)\n\n";

        $tokenA = per_run_token();
        $tokenB = per_run_token();

        support_check($tokenA !== $tokenB, 'two successive per_run_token() values differ');
        support_check(preg_match('/^\d+-\d{10}-[0-9a-f]{8}$/', $tokenA) === 1, 'token A matches pid-time-hex8');
        support_check(preg_match('/^\d+-\d{10}-[0-9a-f]{8}$/', $tokenB) === 1, 'token B matches pid-time-hex8');

        $emailA = token_email($tokenA);
        $emailB = token_email($tokenB);
        support_check($emailA !== $emailB, 'emails differ for the two tokens');
        support_check(str_contains($emailA, $tokenA), 'email local part contains its token');
        support_check(preg_match('/^[!-~]+$/', $emailA) === 1, 'email is ASCII-only');
        support_check($emailA === strtolower($emailA), 'email is lowercase');
        support_check(strlen($emailA) <= 150, 'email is at most 150 characters');
        support_check(filter_var($emailA, FILTER_VALIDATE_EMAIL) !== false, 'email passes FILTER_VALIDATE_EMAIL');

        $phoneA = token_phone($tokenA);
        $phoneB = token_phone($tokenB);
        support_check($phoneA !== $phoneB, 'phones differ for the two tokens');
        support_check(preg_match('/^\d{7,20}$/', $phoneA) === 1, 'phone is digits-only within 7-20 digits');

        $idA = token_document($tokenA);
        $idB = token_document($tokenB);
        support_check($idA !== $idB, 'cedula/ID values differ for the two tokens');
        support_check(preg_match('/^[A-Za-z0-9-]{5,20}$/', $idA) === 1, 'cedula/ID is 5-20 chars of letters, digits, hyphens');
        support_check(preg_match('/[A-Za-z0-9]/', $idA) === 1, 'cedula/ID contains at least one alphanumeric character');
        support_check($idA === strtolower($idA), 'cedula/ID is lowercase (stable under case-insensitive uniqueness)');

        $nameA = token_person_name($tokenA);
        $nameB = token_person_name($tokenB);
        $lastnameA = token_person_name($tokenA, 'Apellido de Prueba');
        support_check($nameA !== $nameB, 'names differ for the two tokens');
        support_check(preg_match("/^[\p{L}' -]+$/u", $nameA) === 1, 'name uses letters, spaces, hyphens, apostrophes only');
        support_check(mb_strlen($nameA) <= 30, 'default name stays within 30 characters');
        support_check(mb_strlen($lastnameA) <= 30, 'lastname example stays within 30 characters');

        echo "\n";
        echo "Token A:     {$tokenA}\n";
        echo "Token B:     {$tokenB}\n";
        echo "Email A:     {$emailA}\n";
        echo "Phone A:     {$phoneA}\n";
        echo "Cedula/ID A: {$idA}\n";
        echo "Name A:      {$nameA}\n";
        echo "Lastname A:  {$lastnameA}\n";
        echo "\n";
        support_print_summary();
        exit(support_failures() === 0 ? 0 : 1);

    case 'inspect':
        if (!isset($flags['email']) || $flags['email'] === '') {
            fwrite(STDERR, "inspect requires --email=<captured email>\n");
            support_usage();
            exit(2);
        }
        $account = find_account_by_email(profego_pdo(), $flags['email']);
        if ($account === null) {
            echo "NOT FOUND: no account matches correo = {$flags['email']}\n";
            exit(0);
        }
        foreach ($account as $column => $value) {
            printf("%-20s %s\n", (string) $column, $value === null ? '(null)' : (string) $value);
        }
        exit(0);

    case 'cleanup':
        $identifiers = [
            'email' => $flags['email'] ?? '',
            'id' => $flags['id'] ?? '',
            'phone' => $flags['phone'] ?? '',
        ];
        if (count(array_filter($identifiers, 'strlen')) === 0) {
            fwrite(STDERR, "cleanup requires at least one of --email, --id, --phone\n");
            support_usage();
            exit(2);
        }
        $result = delete_captured_rows(profego_pdo(), $identifiers);
        printf(
            "Deleted tutor profiles: %d, accounts: %d. Remaining rows for the captured identifiers: %d\n",
            $result['profiles'],
            $result['accounts'],
            $result['residue']
        );
        exit($result['residue'] === 0 ? 0 : 1);

    default:
        fwrite(STDERR, "Unknown mode: {$mode}\n");
        support_usage();
        exit(2);
}
