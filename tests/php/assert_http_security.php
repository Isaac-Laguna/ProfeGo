<?php

declare(strict_types=1);

/*
 * Layer 4 real-HTTP security harness for spec 001 (create-account).
 *
 * Drives the running application over real HTTP with ext-curl and a cookie
 * jar, bypassing the interface the way an attacker would. It obtains a valid
 * CSRF token from the rendered form, then submits form-encoded POSTs that
 * probe role/CSRF enforcement, the no-JavaScript preselected path, boundary
 * validation, literal SQL storage, escaped XSS re-rendering, and response
 * confidentiality. It also proves every tests/php/*.php script returns 404
 * over HTTP (the CLI guard).
 *
 * RNF-7 isolation: every writing scenario uses per_run_token()-derived data,
 * captures only its own identifiers, deletes tutor profiles before accounts
 * in a finally block, asserts zero residue by captured identifiers, never
 * truncates, never touches the pre-existing empty prototype row, and never
 * runs writing scripts in parallel. The only concurrent request pair is the
 * single controlled duplicate-submission scenario inside this script.
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

require_once $root . '/includes/database.php';
require_once $root . '/tests/php/test_support.php';

const REGISTER_URL = 'http://localhost/pages/register.php';
const TEST_PASSWORD = 'Clave12345';
const GENERIC_ERROR = 'No se pudo completar el registro. Inténtalo de nuevo.';
const DUPLICATE_EMAIL_MESSAGE = 'Este correo electrónico ya está registrado.';
const TERMS_ERROR = 'Debes aceptar los términos y condiciones.';
const NAME_LENGTH_ERROR = 'El nombre debe tener entre 1 y 30 caracteres.';
const EMAIL_LENGTH_ERROR = 'El correo electrónico debe tener como máximo 150 caracteres.';
const PHONE_LENGTH_ERROR = 'El teléfono debe tener entre 7 y 20 dígitos.';
const ID_LENGTH_ERROR = 'La cédula o ID debe tener entre 5 y 20 caracteres.';
const PASSWORD_LENGTH_ERROR = 'La contraseña debe tener entre 8 y 72 caracteres.';

// ---------------------------------------------------------------------------
// Application data path, request helpers, and response inspection
// ---------------------------------------------------------------------------

/** Count only real test rows; the pre-existing prototype row has correo = ''. */
function row_counts(PDO $pdo): array
{
    $users = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE correo <> ''")->fetchColumn();
    $profiles = (int) $pdo->query('SELECT COUNT(*) FROM perfiles_docente')->fetchColumn();

    return [$users, $profiles];
}

function new_cookie_jar(): string
{
    $path = tempnam(sys_get_temp_dir(), 'profego_http_');
    if ($path === false) {
        throw new RuntimeException('Unable to create a temporary cookie jar.');
    }

    return $path;
}

function curl_handle(string $url, string $jar)
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    return $handle;
}

function get_response(string $url, string $jar): array
{
    $handle = curl_handle($url, $jar);
    $headers = '';
    curl_setopt($handle, CURLOPT_HEADERFUNCTION, static function ($ch, string $line) use (&$headers): int {
        $headers .= $line;

        return strlen($line);
    });
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($error !== '') {
        throw new RuntimeException("HTTP GET {$url} failed: {$error}");
    }

    return ['status' => $status, 'body' => (string) $body, 'headers' => $headers];
}

function post_response(string $url, string $jar, array $fields): array
{
    $handle = curl_handle($url, $jar);
    curl_setopt($handle, CURLOPT_POST, true);
    curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
    $headers = '';
    curl_setopt($handle, CURLOPT_HEADERFUNCTION, static function ($ch, string $line) use (&$headers): int {
        $headers .= $line;

        return strlen($line);
    });
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($error !== '') {
        throw new RuntimeException("HTTP POST {$url} failed: {$error}");
    }

    return ['status' => $status, 'body' => (string) $body, 'headers' => $headers];
}

function extract_token(string $body): string
{
    return preg_match('/name="csrf_token" value="([0-9a-f]{64})"/', $body, $match) === 1 ? $match[1] : '';
}

function extract_hidden_role(string $body): string
{
    return preg_match('/name="rol" id="register-role-value" value="([^"]*)"/', $body, $match) === 1 ? $match[1] : '';
}

/** Mandatory inspection applied to EVERY HTTP response produced by this harness. */
function inspect_response(array $response, string $token, string $label): void
{
    $body = (string) ($response['body'] ?? '');

    check(!str_contains($body, TEST_PASSWORD), "{$label}: inspection - no plaintext password");
    check(
        preg_match('/\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}/', $body) !== 1,
        "{$label}: inspection - no password hash"
    );
    foreach (['SQLSTATE', 'PDO', 'Exception', 'stack trace', 'password_hash', 'C:\\', 'C:/', '/ProfeGo', 'Fatal error', 'Warning:'] as $needle) {
        check(!str_contains($body, $needle), "{$label}: inspection - no technical detail ({$needle})");
    }

    // The session token is allowed only in the rendered hidden form input.
    if ($token !== '' && $body !== '') {
        check(
            substr_count($body, $token) === 1,
            "{$label}: inspection - CSRF token appears exactly once in the form response"
        );
        $withoutHiddenInput = preg_replace(
            '/<input type="hidden" name="csrf_token" value="[0-9a-f]{64}">/',
            '',
            $body
        ) ?? $body;
        check(
            !str_contains($withoutHiddenInput, $token),
            "{$label}: inspection - CSRF token only inside the hidden form input"
        );
    }
}

/** GET the plain form, inspect the response, and return its valid token. */
function fresh_form(string $jar, string $label): string
{
    $response = get_response(REGISTER_URL, $jar);
    $token = extract_token($response['body']);
    inspect_response($response, $token, $label);
    check($token !== '', "{$label}: form response contains a CSRF token");
    check(str_contains($response['body'], '<!DOCTYPE html>'), "{$label}: complete HTML document");

    return $token;
}

function perform_post(string $jar, array $fields, string $token, string $label): array
{
    $response = post_response(REGISTER_URL, $jar, $fields);
    inspect_response($response, $token, $label);

    return $response;
}

function perform_get(string $url, string $jar, string $token, string $label): array
{
    $response = get_response($url, $jar);
    inspect_response($response, $token, $label);

    return $response;
}

// ---------------------------------------------------------------------------
// Fixture builders
// ---------------------------------------------------------------------------

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

function long_email(string $token, int $length): string
{
    $local = 'run.' . $token;
    $domainLength = $length - strlen($local) - 1;
    $labels = [];
    $remaining = $domainLength;
    $letter = ord('a');
    while ($remaining > 60) {
        $labels[] = str_repeat(chr($letter), 60);
        $remaining -= 61;
        $letter++;
    }
    $labels[] = str_repeat(chr($letter), $remaining);

    return $local . '@' . implode('.', $labels);
}

function sized_name(string $token, int $length): string
{
    $suffix = token_name_suffix($token);

    return str_repeat('A', $length - 1 - strlen($suffix)) . ' ' . $suffix;
}

function sized_phone(string $token, int $digits): string
{
    return substr(token_phone($token) . str_repeat('0', $digits), 0, $digits);
}

function sized_document(string $token, int $length): string
{
    return substr(token_document($token) . str_repeat('b', $length), 0, $length);
}

function sized_password(int $bytes): string
{
    return str_repeat('a', $bytes - 1) . '1';
}

/** Assert the full RF-15 / AC-14 presentation set on a real HTTP response. */
function assert_ac14_http(array $response, array $post, string $expectedRole, string $sessionToken, string $label): void
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

    check($sessionToken !== '', "{$label}: session holds a valid token");
    check(
        str_contains($body, 'name="csrf_token" value="' . $sessionToken . '"'),
        "{$label}: valid session token in response"
    );
    foreach (['SQLSTATE', 'PDO', 'Exception', 'stack trace', 'forced tutor', 'password_hash', 'C:\\', '/ProfeGo'] as $needle) {
        check(!str_contains($body, $needle), "{$label}: no technical detail ({$needle})");
    }
}

/** Read the PHPSESSID value from a Netscape-format cookie jar. */
function session_cookie_from_jar(string $jar): string
{
    $lines = file($jar, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return '';
    }
    foreach ($lines as $line) {
        $fields = explode("\t", $line);
        if (count($fields) >= 7 && $fields[5] === 'PHPSESSID') {
            return 'PHPSESSID=' . $fields[6];
        }
    }

    return '';
}

// ---------------------------------------------------------------------------
// Harness
// ---------------------------------------------------------------------------

echo "Layer 4 - Real HTTP security harness (ext-curl + cookie jar; single controlled concurrency pair)\n";
check(extension_loaded('curl'), 'ext-curl is available');

$pdo = profego_pdo();
$baseline = row_counts($pdo);
printf(
    "Baseline counted rows (empty prototype row excluded): %d accounts, %d tutor profiles\n\n",
    $baseline[0],
    $baseline[1]
);

$jars = [];
$mainJar = new_cookie_jar();
$jars[] = $mainJar;
$foreignJar = new_cookie_jar();
$jars[] = $foreignJar;
$capturedSets = [];

try {
    // --- Initial valid session: GET issues a 64-hex token ---------------------
    echo "--- Session bootstrap: real GET issues the CSRF token ---\n";
    $sessionToken = fresh_form($mainJar, 'bootstrap GET');
    check(
        preg_match('/^[0-9a-f]{64}$/', $sessionToken) === 1,
        'bootstrap token is 64 hex characters'
    );

    // --- A. Direct POST with empty and invalid values; zero writes ------------
    echo "\n--- Scenario A: direct POST with empty and invalid values ---\n";
    $token = fresh_form($mainJar, 'empty POST: form GET');
    $emptyPost = [
        'name' => '', 'lastname' => '', 'email' => '', 'phone' => '', 'id' => '',
        'password' => '', 'password_confirm' => '', 'terms' => 'on',
        'rol' => 'estudiante', 'csrf_token' => $token,
    ];
    $before = row_counts($pdo);
    $response = perform_post($mainJar, $emptyPost, $token, 'empty POST');
    check($response['status'] === 200, 'empty POST: HTTP 200');
    check(str_contains($response['body'], 'register-field__error'), 'empty POST: field errors are rendered');
    check(str_contains($response['body'], 'Ingresa tu nombre.'), 'empty POST: name required error');
    check(str_contains($response['body'], 'Ingresa tu correo electrónico.'), 'empty POST: email required error');
    check(!str_contains($response['body'], TERMS_ERROR), 'empty POST: no terms error when terms accepted');
    check(!str_contains($response['body'], 'class="register-form-error"'), 'empty POST: no form-level alert');
    check($before === row_counts($pdo), 'empty POST: zero writes');

    // Actually submit the empty form WITH terms to isolate field errors only.
    $token = fresh_form($mainJar, 'empty-with-terms POST: form GET');
    $emptyWithTerms = $emptyPost;
    $emptyWithTerms['csrf_token'] = $token;
    $before = row_counts($pdo);
    $response = perform_post($mainJar, $emptyWithTerms, $token, 'empty-with-terms POST');
    check($response['status'] === 200, 'empty-with-terms POST: HTTP 200');
    check(str_contains($response['body'], 'Ingresa tu nombre.'), 'empty-with-terms POST: required name error');
    check(!str_contains($response['body'], 'class="register-form-error"'), 'empty-with-terms POST: no form-level alert');
    check($before === row_counts($pdo), 'empty-with-terms POST: zero writes');

    $token = fresh_form($mainJar, 'invalid POST: form GET');
    $invalidToken = per_run_token();
    $invalidPost = valid_post($invalidToken, $token, 'estudiante', [
        'name' => '123',
        'email' => 'not-an-email',
        'phone' => 'abc',
        'id' => '***',
        'password' => 'abc',
        'password_confirm' => 'xyz',
    ]);
    $before = row_counts($pdo);
    $response = perform_post($mainJar, $invalidPost, $token, 'invalid POST');
    check($response['status'] === 200, 'invalid POST: HTTP 200');
    check(str_contains($response['body'], 'El nombre solo puede contener letras, espacios, guiones y apóstrofes.'), 'invalid POST: name composition error');
    check(str_contains($response['body'], 'El correo electrónico no es válido.'), 'invalid POST: email format error');
    check(str_contains($response['body'], PHONE_LENGTH_ERROR), 'invalid POST: phone length error');
    check(str_contains($response['body'], 'La cédula o ID solo puede contener letras, números y guiones.'), 'invalid POST: cedula composition error');
    check(str_contains($response['body'], PASSWORD_LENGTH_ERROR), 'invalid POST: password length error');
    check(!str_contains($response['body'], 'class="register-form-error"'), 'invalid POST: no form-level alert');
    check($before === row_counts($pdo), 'invalid POST: zero writes');

    // --- B. Role violations: full AC-14 set, zero writes ----------------------
    echo "\n--- Scenario B: admin / unknown / missing role on direct POST ---\n";
    $roleScenarios = ['admin' => 'admin', 'unknown' => 'desconocido', 'missing' => null];
    foreach ($roleScenarios as $case => $submittedRole) {
        $token = fresh_form($mainJar, "role {$case}: form GET");
        $runToken = per_run_token();
        $post = valid_post($runToken, $token, 'estudiante');
        if ($submittedRole === null) {
            unset($post['rol']);
        } else {
            $post['rol'] = $submittedRole;
        }
        $before = row_counts($pdo);
        $response = perform_post($mainJar, $post, $token, "role {$case}");
        check($before === row_counts($pdo), "role {$case}: zero writes");
        assert_ac14_http($response, $post, 'estudiante', $token, "role {$case}");
    }

    // --- C. CSRF violations: full AC-14 set, zero writes ----------------------
    echo "\n--- Scenario C: missing / malformed / foreign CSRF token ---\n";
    $foreignToken = fresh_form($foreignJar, 'foreign session: form GET');
    $csrfScenarios = ['missing' => null, 'malformed' => 'not-a-valid-token', 'foreign' => $foreignToken];
    foreach ($csrfScenarios as $case => $submitted) {
        $token = fresh_form($mainJar, "csrf {$case}: form GET");
        $runToken = per_run_token();
        $post = valid_post($runToken, $token, 'docente');
        if ($submitted === null) {
            unset($post['csrf_token']);
        } else {
            $post['csrf_token'] = $submitted;
        }
        $before = row_counts($pdo);
        $response = perform_post($mainJar, $post, $token, "csrf {$case}");
        check($before === row_counts($pdo), "csrf {$case}: zero writes");
        assert_ac14_http($response, $post, 'docente', $token, "csrf {$case}");
    }

    // --- D. Look-alike fail_tutor_profile field is not the CLI seam -----------
    echo "\n--- Scenario D: look-alike fail_tutor_profile=1 over HTTP ---\n";
    $token = fresh_form($mainJar, 'seam POST: form GET');
    $runToken = per_run_token();
    $seamPost = valid_post($runToken, $token, 'docente', ['fail_tutor_profile' => '1']);
    $capturedSets[] = identifiers_for($seamPost);
    $before = row_counts($pdo);
    $response = perform_post($mainJar, $seamPost, $token, 'seam POST');
    check($response['status'] === 303, 'seam POST: normal registration returns 303 (seam not reachable)');
    check(str_contains($response['headers'], 'Location: login.php?registro=ok'), 'seam POST: 303 Location header');
    check(row_counts($pdo) === [$before[0] + 1, $before[1] + 1], 'seam POST: one account plus one tutor profile');
    $row = find_account_by_email($pdo, $seamPost['email']);
    check($row !== null && $row['rol'] === 'docente', 'seam POST: stored role is docente');
    check($row !== null && $row['estado_verificacion'] === 'pendiente', 'seam POST: pending tutor profile');

    // --- E. One session token reused for two different valid registrations ---
    echo "\n--- Scenario E: same-session token reused for two registrations ---\n";
    $token = fresh_form($mainJar, 'token reuse: form GET');
    $postA = valid_post(per_run_token(), $token);
    $postB = valid_post(per_run_token(), $token);
    $capturedSets[] = identifiers_for($postA);
    $capturedSets[] = identifiers_for($postB);
    $first = perform_post($mainJar, $postA, $token, 'token reuse: first POST');
    $second = perform_post($mainJar, $postB, $token, 'token reuse: second POST');
    check($first['status'] === 303, 'token reuse: first registration accepted (303)');
    check($second['status'] === 303, 'token reuse: second registration accepted (303) with the same token');
    check(find_account_by_email($pdo, $postA['email']) !== null, 'token reuse: first account stored');
    check(find_account_by_email($pdo, $postB['email']) !== null, 'token reuse: second account stored');

    // --- F. Concurrent identical submissions: one 303, one duplicate 200 -----
    echo "\n--- Scenario F: concurrent identical submissions (only concurrent pair) ---\n";
    $token = fresh_form($mainJar, 'concurrency: form GET');
    $concurrentPost = valid_post(per_run_token(), $token);
    $capturedSets[] = identifiers_for($concurrentPost);
    $cookie = session_cookie_from_jar($mainJar);
    check($cookie !== '', 'concurrency: PHPSESSID read from cookie jar');

    $multiHandle = curl_multi_init();
    $handles = [];
    $responses = [];
    for ($i = 0; $i < 2; $i++) {
        $handle = curl_init(REGISTER_URL);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE => $cookie,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($concurrentPost),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $responses[$i] = ['headers' => ''];
        curl_setopt($handle, CURLOPT_HEADERFUNCTION, static function ($ch, string $line) use (&$responses, $i): int {
            $responses[$i]['headers'] .= $line;

            return strlen($line);
        });
        curl_multi_add_handle($multiHandle, $handle);
        $handles[] = $handle;
    }
    do {
        $multiStatus = curl_multi_exec($multiHandle, $active);
        if ($active > 0) {
            curl_multi_select($multiHandle, 1.0);
        }
    } while ($active > 0 && $multiStatus === CURLM_OK);
    foreach ($handles as $i => $handle) {
        $responses[$i]['body'] = (string) curl_multi_getcontent($handle);
        $responses[$i]['status'] = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_multi_remove_handle($multiHandle, $handle);
        curl_close($handle);
    }
    curl_multi_close($multiHandle);

    foreach ($responses as $i => $response) {
        inspect_response($response, $token, "concurrent request {$i}");
    }
    $statuses = [$responses[0]['status'], $responses[1]['status']];
    sort($statuses);
    check($statuses === [200, 303], 'concurrency: exactly one 303 success and one 200 rejection');
    $duplicateBody = $responses[0]['status'] === 200 ? $responses[0]['body'] : $responses[1]['body'];
    check(str_contains($duplicateBody, DUPLICATE_EMAIL_MESSAGE), 'concurrency: losing request reports the duplicate field error');
    check(str_contains($duplicateBody, 'register-field__error'), 'concurrency: losing request has field errors');
    $statement = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE correo = :email');
    $statement->execute([':email' => $concurrentPost['email']]);
    check((int) $statement->fetchColumn() === 1, 'concurrency: exactly one stored row');

    // --- G. No-JavaScript preselected submission (Option A) -------------------
    echo "\n--- Scenario G: GET ?rol=docente then native-form-equivalent POST ---\n";
    $preselectGet = get_response(REGISTER_URL . '?rol=docente', $mainJar);
    $preselectToken = extract_token($preselectGet['body']);
    inspect_response($preselectGet, $preselectToken, 'no-JS preselection GET');
    $hiddenRole = extract_hidden_role($preselectGet['body']);
    check($hiddenRole === 'docente', 'no-JS preselection GET: rendered hidden rol=docente');
    check(str_contains($preselectGet['body'], '>Docente</button>'), 'no-JS preselection GET: visible Docente text');
    $runToken = per_run_token();
    $docentePost = valid_post($runToken, $preselectToken, $hiddenRole);
    $capturedSets[] = identifiers_for($docentePost);
    $before = row_counts($pdo);
    $response = perform_post($mainJar, $docentePost, $preselectToken, 'no-JS preselection POST');
    check($response['status'] === 303, 'no-JS preselection POST: 303 success');
    check(row_counts($pdo) === [$before[0] + 1, $before[1] + 1], 'no-JS preselection POST: one account plus one profile');
    $row = find_account_by_email($pdo, $docentePost['email']);
    check($row !== null && $row['rol'] === 'docente', 'no-JS preselection POST: docente account stored');
    check($row !== null && $row['estado_verificacion'] === 'pendiente', 'no-JS preselection POST: pending tutor profile');

    // --- H. Oversized boundary values ----------------------------------------
    echo "\n--- Scenario H: boundary values (150/151, 30/31, 20/21, 72/73) ---\n";
    $boundaries = [
        [
            'field' => 'email',
            'at' => 150,
            'over' => 151,
            'message' => EMAIL_LENGTH_ERROR,
            'builder' => static fn (string $t, int $n): string => long_email($t, $n),
        ],
        [
            'field' => 'name',
            'at' => 30,
            'over' => 31,
            'message' => NAME_LENGTH_ERROR,
            'builder' => static fn (string $t, int $n): string => sized_name($t, $n),
        ],
        [
            'field' => 'phone',
            'at' => 20,
            'over' => 21,
            'message' => PHONE_LENGTH_ERROR,
            'builder' => static fn (string $t, int $n): string => sized_phone($t, $n),
        ],
        [
            'field' => 'id',
            'at' => 20,
            'over' => 21,
            'message' => ID_LENGTH_ERROR,
            'builder' => static fn (string $t, int $n): string => sized_document($t, $n),
        ],
        [
            'field' => 'password',
            'at' => 72,
            'over' => 73,
            'message' => PASSWORD_LENGTH_ERROR,
            'builder' => static fn (string $t, int $n): string => sized_password($n),
        ],
    ];
    foreach ($boundaries as $boundary) {
        $field = $boundary['field'];

        // At-limit value is accepted by validation (real write, tokenized).
        $token = fresh_form($mainJar, "boundary {$field}={$boundary['at']}: form GET");
        $runToken = per_run_token();
        $atPost = valid_post($runToken, $token, 'estudiante', [
            $field => $boundary['builder']($runToken, $boundary['at']),
        ]);
        if ($field === 'password') {
            $atPost['password_confirm'] = $atPost['password'];
        }
        $capturedSets[] = identifiers_for($atPost);
        $atResponse = perform_post($mainJar, $atPost, $token, "boundary {$field}={$boundary['at']}");
        check($atResponse['status'] === 303, "boundary {$field}={$boundary['at']}: at-limit value accepted");
        check(find_account_by_email($pdo, $atPost['email']) !== null, "boundary {$field}={$boundary['at']}: account stored");

        // Over-limit value is rejected by the field rule, not truncated, no DB detail.
        $token = fresh_form($mainJar, "boundary {$field}={$boundary['over']}: form GET");
        $runToken = per_run_token();
        $overValue = $boundary['builder']($runToken, $boundary['over']);
        $overPost = valid_post($runToken, $token, 'estudiante', [$field => $overValue]);
        if ($field === 'password') {
            $overPost['password_confirm'] = $overPost['password'];
        }
        $before = row_counts($pdo);
        $overResponse = perform_post($mainJar, $overPost, $token, "boundary {$field}={$boundary['over']}");
        check($overResponse['status'] === 200, "boundary {$field}={$boundary['over']}: over-limit value rejected");
        check(str_contains($overResponse['body'], $boundary['message']), "boundary {$field}={$boundary['over']}: specific length error");
        check(!str_contains($overResponse['body'], 'class="register-form-error"'), "boundary {$field}={$boundary['over']}: no form-level alert");
        if ($field !== 'password') {
            check(
                str_contains($overResponse['body'], 'value="' . escape_value($overValue) . '"'),
                "boundary {$field}={$boundary['over']}: full value preserved (no truncation)"
            );
        }
        check($before === row_counts($pdo), "boundary {$field}={$boundary['over']}: zero writes");
    }

    // --- I. POST without terms ------------------------------------------------
    echo "\n--- Scenario I: POST without terms ---\n";
    $token = fresh_form($mainJar, 'no-terms POST: form GET');
    $noTermsPost = valid_post(per_run_token(), $token);
    unset($noTermsPost['terms']);
    $before = row_counts($pdo);
    $response = perform_post($mainJar, $noTermsPost, $token, 'no-terms POST');
    check($response['status'] === 200, 'no-terms POST: HTTP 200');
    check(str_contains($response['body'], TERMS_ERROR), 'no-terms POST: field-specific terms error');
    check(str_contains($response['body'], 'id="register-terms-error"'), 'no-terms POST: terms error container associated');
    check(!str_contains($response['body'], 'class="register-form-error"'), 'no-terms POST: no form-level alert');
    check($before === row_counts($pdo), 'no-terms POST: zero writes');

    // --- J. Allowed-character SQLi payload stored literally -------------------
    echo "\n--- Scenario J: SQLi payload stored literally and read back ---\n";
    $token = fresh_form($mainJar, 'SQLi POST: form GET');
    $sqliPayload = "O'Brien' OR 'a' LIKE 'a'--";
    $sqliPost = valid_post(per_run_token(), $token, 'estudiante', ['name' => $sqliPayload]);
    $capturedSets[] = identifiers_for($sqliPost);
    $before = row_counts($pdo);
    $response = perform_post($mainJar, $sqliPost, $token, 'SQLi POST');
    check($response['status'] === 303, 'SQLi POST: accepted (allowed characters)');
    check(row_counts($pdo) === [$before[0] + 1, $before[1]], 'SQLi POST: exactly one new row');
    $row = find_account_by_email($pdo, $sqliPost['email']);
    check($row !== null && $row['nombre'] === $sqliPayload, 'SQLi POST: literal value read back through the application data path');
    $followUp = fresh_form($mainJar, 'SQLi follow-up GET');
    check($followUp !== '', 'SQLi follow-up GET: application still serves a valid form (queries unchanged)');
    $prototype = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE correo = ''")->fetchColumn();
    check($prototype === 1, 'SQLi POST: pre-existing empty prototype row untouched');

    // --- K. XSS payload rejected and escaped ----------------------------------
    echo "\n--- Scenario K: XSS payload rejected and escaped ---\n";
    $token = fresh_form($mainJar, 'XSS POST: form GET');
    $xssPayload = '"><script>alert(1)</script>';
    $xssPost = valid_post(per_run_token(), $token, 'estudiante', ['name' => $xssPayload]);
    $before = row_counts($pdo);
    $response = perform_post($mainJar, $xssPost, $token, 'XSS POST');
    $xssBody = (string) $response['body'];
    check($response['status'] === 200, 'XSS POST: validation rejects with 200');
    check(str_contains($xssBody, 'El nombre solo puede contener letras, espacios, guiones y apóstrofes.'), 'XSS POST: name composition error');
    check(
        str_contains($xssBody, htmlspecialchars($xssPayload, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
        'XSS POST: payload repopulated as escaped inert text'
    );
    check(!str_contains($xssBody, '<script>alert(1)</script>'), 'XSS POST: no raw script payload');
    check(!str_contains($xssBody, '"><script>'), 'XSS POST: no raw angle-bracket injection');
    check(!str_contains($xssBody, '<script>alert'), 'XSS POST: no injected script element');
    check(!str_contains($xssBody, 'class="register-form-error"'), 'XSS POST: no form-level alert');
    check($before === row_counts($pdo), 'XSS POST: zero writes');

    // --- L. CLI guard: every tests/php/*.php script returns 404 over HTTP -----
    echo "\n--- Scenario L: CLI guard proof over real HTTP ---\n";
    $scripts = glob($root . '/tests/php/*.php');
    sort($scripts);
    check(count($scripts) > 0, 'found tests/php scripts to probe');
    foreach ($scripts as $path) {
        $name = basename($path);
        $url = 'http://localhost/tests/php/' . rawurlencode($name);
        $response = perform_get($url, $mainJar, '', "404 {$name}");
        check($response['status'] === 404, "HTTP 404 for tests/php/{$name}");
    }
} catch (Throwable $exception) {
    check(false, 'unexpected harness exception: ' . $exception->getMessage());
} finally {
    echo "\nCleanup (captured identifiers only; tutor profiles before accounts):\n";
    foreach ($capturedSets as $index => $identifiers) {
        $cleanup = delete_captured_rows($pdo, $identifiers);
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
    foreach ($capturedSets as $identifiers) {
        $totalResidue += count_captured_rows($pdo, $identifiers);
    }
    check($totalResidue === 0, 'final zero-residue assertion by captured identifiers');
    check(row_counts($pdo) === $baseline, 'row counts restored to baseline after cleanup');
    check($pdo->inTransaction() === false, 'no open transaction after cleanup');
    $prototype = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE correo = ''")->fetchColumn();
    check($prototype === 1, 'pre-existing empty prototype row still present and untouched');

    foreach ($jars as $jar) {
        if (is_file($jar)) {
            @unlink($jar);
        }
    }

    echo "\n";
    printf("Assertions: %d, Failures: %d\n", $assertions, $failures);
}

exit($failures === 0 ? 0 : 1);
