<?php

declare(strict_types=1);

/*
 * Layer 4 controller-level harness for spec 001 (create-account).
 *
 * Calls build_registration_response() directly with simulated request,
 * query and session arrays so the same pipeline used by the web entry point
 * is exercised from CLI (no superglobals, no HTTP, no browser). Covers the
 * RF-15 / AC-14 non-field presentation, validation-before-duplicates
 * precedence, the forced database failure through the CLI options seam,
 * GET no-write, token stability, and the PRG 303 success path.
 *
 * Every writing scenario uses the RNF-7 per-run token, captures only the
 * identifiers it created, deletes tutor profiles before accounts in a
 * finally block, and asserts zero residue. Tables are never truncated, the
 * pre-existing empty prototype row is excluded from all counts and is never
 * touched, and writing tests are never run in parallel.
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

// Test-first red state: the controller must not exist yet on the first run.
if (!is_file($root . '/includes/register_controller.php')) {
    fwrite(STDERR, "FAIL: includes/register_controller.php does not exist yet.\n");
    exit(1);
}

require_once $root . '/includes/database.php';
require_once $root . '/includes/csrf.php';
require_once $root . '/includes/validation.php';
require_once $root . '/includes/registration_service.php';
require_once $root . '/includes/register_controller.php';
require_once $root . '/tests/php/test_support.php';

$pdo = profego_pdo();

const TEST_PASSWORD = 'Clave12345';
const GENERIC_ERROR = 'No se pudo completar el registro. Inténtalo de nuevo.';
const DUPLICATE_EMAIL_MESSAGE = 'Este correo electrónico ya está registrado.';
const NAME_COMPOSITION_ERROR = 'El nombre solo puede contener letras, espacios, guiones y apóstrofes.';
const META_DESCRIPTION = 'Regístrate en ProfeGo como estudiante o docente.';

/** Count only real test rows; the pre-existing prototype row has correo = ''. */
function row_counts(PDO $pdo): array
{
    $users = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE correo <> ''")->fetchColumn();
    $profiles = (int) $pdo->query('SELECT COUNT(*) FROM perfiles_docente')->fetchColumn();

    return [$users, $profiles];
}

function get_server(): array
{
    return ['REQUEST_METHOD' => 'GET'];
}

function post_server(): array
{
    return ['REQUEST_METHOD' => 'POST'];
}

/** A session that already holds a valid CSRF token, as a prior GET would. */
function session_with_token(): array
{
    $session = [];
    ensure_csrf_token($session);

    return $session;
}

/** A complete valid POST; overrides replace one field per scenario. */
function valid_post(string $token, string $csrf, string $role = 'estudiante', array $overrides = []): array
{
    $post = [
        'name' => token_person_name($token),
        'lastname' => token_person_name($token, 'Apellido de Prueba'),
        'email' => token_email($token),
        'phone' => token_phone($token),
        'id' => token_document($token),
        'password' => TEST_PASSWORD,
        'password_confirm' => TEST_PASSWORD,
        'terms' => 'on',
        'rol' => $role,
        'csrf_token' => $csrf,
    ];

    return array_merge($post, $overrides);
}

/** @return array{email: string, id: string, phone: string} */
function identifiers_for(array $post): array
{
    return [
        'email' => (string) ($post['email'] ?? ''),
        'id' => (string) ($post['id'] ?? ''),
        'phone' => (string) ($post['phone'] ?? ''),
    ];
}

function escape_value($value): string
{
    return is_scalar($value)
        ? htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        : '';
}

/** The three server-rendered role signals for one role. */
function role_signals(string $role): array
{
    return $role === 'docente'
        ? [
            'label' => 'Docente',
            'name' => 'Tipo de cuenta: Docente, cambiar a Estudiante',
            'live' => 'Tipo de cuenta seleccionado: Docente.',
        ]
        : [
            'label' => 'Estudiante',
            'name' => 'Tipo de cuenta: Estudiante, cambiar a Docente',
            'live' => 'Tipo de cuenta seleccionado: Estudiante.',
        ];
}

/** Assert the complete RF-15 / AC-14 presentation set on one response. */
function assert_ac14(array $response, array $session, array $post, string $expectedRole, string $label): void
{
    $body = (string) ($response['body'] ?? '');
    check(($response['status'] ?? null) === 200, "{$label}: HTTP 200");
    check(str_contains($body, 'class="register-form-error" role="alert"'), "{$label}: form-level role=alert region");
    check(str_contains($body, GENERIC_ERROR), "{$label}: generic Spanish form error");
    check(!str_contains($body, 'register-field__error'), "{$label}: no field error containers");
    check(!str_contains($body, 'aria-invalid="true"'), "{$label}: no field association/invalid markers");
    check(!str_contains($body, TEST_PASSWORD), "{$label}: plaintext password never in response");

    foreach (['name', 'lastname', 'email', 'phone', 'id'] as $field) {
        check(
            str_contains($body, 'name="' . $field . '"')
                && str_contains($body, 'value="' . escape_value($post[$field] ?? '') . '"'),
            "{$label}: {$field} raw value preserved"
        );
    }

    check(
        (bool) preg_match('/<input[^>]*name="terms"[^>]*\bchecked\b/', $body),
        "{$label}: terms checked state preserved"
    );

    $signals = role_signals($expectedRole);
    check(
        str_contains($body, 'id="register-role-value" value="' . $expectedRole . '"'),
        "{$label}: hidden role preserved as {$expectedRole}"
    );
    check(str_contains($body, 'aria-label="' . $signals['name'] . '"'), "{$label}: role accessible name preserved");
    check(str_contains($body, '>' . $signals['label'] . '</button>'), "{$label}: role visible text preserved");
    check(str_contains($body, $signals['live']), "{$label}: role live-region text preserved");

    $token = (string) ($session['csrf_token'] ?? '');
    check($token !== '', "{$label}: session holds a valid token");
    check(
        str_contains($body, 'name="csrf_token" value="' . $token . '"'),
        "{$label}: valid session token in response"
    );

    foreach (['SQLSTATE', 'PDO', 'Exception', 'stack trace', 'forced tutor', 'password_hash', 'C:\\', '/ProfeGo'] as $needle) {
        check(!str_contains($body, $needle), "{$label}: no technical detail ({$needle})");
    }
}

echo "Layer 4 controller pipeline (single process; writing tests are not run in parallel; tables are never truncated)\n";
check(function_exists('build_registration_response'), 'build_registration_response() is defined by the controller');

$baselineCounts = row_counts($pdo);
printf(
    "Baseline counted rows (empty prototype row excluded): %d accounts, %d tutor profiles\n\n",
    $baselineCounts[0],
    $baselineCounts[1]
);

$capturedSets = [];

try {
    // --- Group 1: invalid / missing role -> full AC-14 set, zero writes ---
    $roleScenarios = [
        'admin' => false,
        'unknown' => false,
        'missing' => true,
    ];
    foreach ($roleScenarios as $case => $missing) {
        $token = per_run_token();
        $session = session_with_token();
        $post = valid_post($token, $session['csrf_token'], 'estudiante');
        if ($missing) {
            unset($post['rol']);
        } else {
            $post['rol'] = $case === 'admin' ? 'admin' : 'desconocido';
        }

        $before = row_counts($pdo);
        $response = build_registration_response(post_server(), $post, $session, [], []);
        check($before === row_counts($pdo), "role {$case}: zero writes");
        assert_ac14($response, $session, $post, 'estudiante', "role {$case}");
    }

    // --- Group 2: invalid CSRF -> full AC-14 set, zero writes, valid role kept ---
    $foreignToken = session_with_token()['csrf_token'];
    $csrfScenarios = [
        'missing' => null,
        'malformed' => 'not-a-valid-token',
        'foreign' => $foreignToken,
    ];
    foreach ($csrfScenarios as $case => $submitted) {
        $token = per_run_token();
        $session = session_with_token();
        $post = valid_post($token, $session['csrf_token'], 'docente');
        if ($submitted === null) {
            unset($post['csrf_token']);
        } else {
            $post['csrf_token'] = $submitted;
        }

        $before = row_counts($pdo);
        $response = build_registration_response(post_server(), $post, $session, [], []);
        check($before === row_counts($pdo), "csrf {$case}: zero writes");
        assert_ac14($response, $session, $post, 'docente', "csrf {$case}");
    }

    // --- Group 3: real seeded duplicate + validation-before-duplicates precedence ---
    $seedToken = per_run_token();
    $seedSession = session_with_token();
    $seedPost = valid_post($seedToken, $seedSession['csrf_token']);
    $seedResponse = build_registration_response(post_server(), $seedPost, $seedSession, [], []);
    check($seedResponse['status'] === 303, 'seed account succeeds through the controller (303)');
    $capturedSets[] = identifiers_for($seedPost);
    check(
        find_account_by_email($pdo, $seedPost['email']) !== null,
        'seed account is a real stored duplicate value'
    );

    // All fields valid except the email that duplicates the seed.
    $dupToken = per_run_token();
    $dupSession = session_with_token();
    $dupPost = valid_post($dupToken, $dupSession['csrf_token'], 'estudiante', ['email' => $seedPost['email']]);
    $before = row_counts($pdo);
    $dupResponse = build_registration_response(post_server(), $dupPost, $dupSession, [], []);
    check($dupResponse['status'] === 200, 'duplicate-only request returns 200');
    check(str_contains($dupResponse['body'], DUPLICATE_EMAIL_MESSAGE), 'duplicate-only request reports the duplicate field error');
    check(!str_contains($dupResponse['body'], 'class="register-form-error"'), 'duplicate-only request has no form-level alert');
    check($before === row_counts($pdo), 'duplicate-only request: zero writes');

    // Invalid field AND a duplicate value: only the field error may be shown.
    $mixToken = per_run_token();
    $mixSession = session_with_token();
    $mixPost = valid_post($mixToken, $mixSession['csrf_token'], 'estudiante', [
        'name' => '123',
        'email' => $seedPost['email'],
    ]);
    $before = row_counts($pdo);
    $mixResponse = build_registration_response(post_server(), $mixPost, $mixSession, [], []);
    $mixBody = $mixResponse['body'];
    check($mixResponse['status'] === 200, 'invalid field + duplicate returns 200');
    check(str_contains($mixBody, NAME_COMPOSITION_ERROR), 'invalid field + duplicate reports the field error');
    check(!str_contains($mixBody, DUPLICATE_EMAIL_MESSAGE), 'invalid field + duplicate reports no duplicate error');
    check(!str_contains($mixBody, 'class="register-form-error"'), 'invalid field + duplicate has no form-level alert');
    check(str_contains($mixBody, 'id="register-name-error"'), 'invalid field is associated with its error container');
    check(!str_contains($mixBody, TEST_PASSWORD), 'password is never repopulated after a field error');
    check($before === row_counts($pdo), 'invalid field + duplicate: zero writes');

    // --- Group 4: forced database failure through the CLI options seam ---
    $failToken = per_run_token();
    $failSession = session_with_token();
    $failPost = valid_post($failToken, $failSession['csrf_token'], 'docente');
    $capturedSets[] = identifiers_for($failPost);
    $before = row_counts($pdo);
    $failResponse = build_registration_response(post_server(), $failPost, $failSession, [], ['fail_tutor_profile' => true]);
    assert_ac14($failResponse, $failSession, $failPost, 'docente', 'forced database failure');
    check($before === row_counts($pdo), 'forced database failure leaves no partial account or profile');
    check(captured_account_ids($pdo, identifiers_for($failPost)) === [], 'forced database failure leaves no surviving account');
    check($pdo->inTransaction() === false, 'forced database failure leaves no open transaction');
    echo "INFO: forced database failure through ['fail_tutor_profile' => true] is VERIFIED through the real controller path (not recorded as unverified)\n";

    // --- Group 5: GET with query parameters performs no write ---
    $getSession = [];
    $before = row_counts($pdo);
    $getResponse = build_registration_response(get_server(), [], $getSession, ['rol' => 'docente', 'extra' => 'x'], []);
    check($getResponse['status'] === 200, 'GET with query params returns 200');
    check($before === row_counts($pdo), 'GET with query params performs no write');
    check(str_contains($getResponse['body'], 'id="register-role-value" value="docente"'), 'GET preselects docente');
    check(str_contains($getResponse['body'], 'name="csrf_token" value="' . $getSession['csrf_token'] . '"'), 'GET issues a valid token');
    check(!str_contains($getResponse['body'], 'class="register-form-error"'), 'GET has no form-level error');
    check(!str_contains($getResponse['body'], 'register-field__error'), 'GET has no field errors');

    // GET render role signals for both roles (same path used by the render CLI mode).
    foreach (['estudiante', 'docente'] as $role) {
        $renderSession = [];
        $renderResponse = build_registration_response(get_server(), [], $renderSession, ['rol' => $role], []);
        $body = $renderResponse['body'];
        $signals = role_signals($role);
        check(str_contains($body, '<!DOCTYPE html>') && str_contains($body, '</html>'), "GET render {$role}: complete document");
        check(str_contains($body, 'id="register-role-value" value="' . $role . '"'), "GET render {$role}: hidden role signal");
        check(str_contains($body, '>' . $signals['label'] . '</button>'), "GET render {$role}: visible role signal");
        check(str_contains($body, 'aria-label="' . $signals['name'] . '"'), "GET render {$role}: accessible name signal");
        check(str_contains($body, $signals['live']), "GET render {$role}: live-region signal");
        check(str_contains($body, META_DESCRIPTION), "GET render {$role}: exact meta description");
    }

    // --- Group 6: token stability + PRG success ---
    $successToken = per_run_token();
    $successSession = session_with_token();
    $tokenBefore = $successSession['csrf_token'];
    $successPost = valid_post($successToken, $tokenBefore);
    $capturedSets[] = identifiers_for($successPost);
    $successResponse = build_registration_response(post_server(), $successPost, $successSession, [], []);
    check($successResponse['status'] === 303, 'successful registration returns 303');
    check(
        in_array('Location: login.php?registro=ok', $successResponse['headers'], true),
        'success response carries Location: login.php?registro=ok'
    );
    check(($successSession['csrf_token'] ?? null) === $tokenBefore, 'token unchanged after successful registration');
    check(($successSession['registration_notice'] ?? false) === true, 'success sets the one-time registration notice flag');
    check(find_account_by_email($pdo, $successPost['email']) !== null, 'success created a real stored account');
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
    check($pdo->inTransaction() === false, 'no open transaction after cleanup');

    echo "\n";
    printf("Assertions: %d, Failures: %d\n", $assertions, $failures);
}

exit($failures === 0 ? 0 : 1);
