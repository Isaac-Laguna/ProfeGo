<?php

declare(strict_types=1);

/*
 * CLI-only Layer 1 assertions for the shared registration validator.
 * Spec 001 (create-account), task T3.2.
 *
 * The corpus is the single source of truth for rule outcomes and Spanish
 * message strings (spec RF-6; plan.md "Validation and messages"). This script
 * asserts EVERY case, including serverOnly ones: the client-side harness
 * (T3.3) may skip those, the server never does.
 *
 * Adapter note: the corpus models the terms checkbox as a JSON boolean,
 * while an HTTP form sends a value only when the checkbox is checked and
 * omits the field when it is unchecked. to_validator_input() maps
 * true -> 'on' and false -> absent before calling validate_registration().
 */

// CLI-only guard: over HTTP this script must not be reachable.
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

/**
 * Merge a case's input over the base record and adapt the boolean terms field
 * to the checkbox shape the validator receives from an HTTP POST.
 */
function to_validator_input(array $caseInput, array $baseRecord): array
{
    $input = array_merge($baseRecord, $caseInput);
    $accepted = (bool) ($input['terms'] ?? false);
    unset($input['terms']);
    if ($accepted) {
        $input['terms'] = 'on';
    }

    return $input;
}

$root = dirname(__DIR__, 2);
$validatorPath = $root . '/includes/validation.php';

if (!is_file($validatorPath)) {
    fwrite(
        STDERR,
        "FAIL: includes/validation.php does not exist yet " .
        "(validator rules and role allow-list cannot be asserted).\n"
    );
    printf("Assertions: %d, Failures: %d\n", $assertions, 1);
    exit(1);
}

require_once $validatorPath;

check(function_exists('validate_registration'), 'validate_registration() is defined');
check(function_exists('normalize_phone'), 'normalize_phone() is defined');
check(function_exists('is_allowed_registration_role'), 'is_allowed_registration_role() is defined');

$corpusPath = $root . '/tests/fixtures/validation-cases.json';
$corpus = json_decode((string) file_get_contents($corpusPath), true, 512, JSON_THROW_ON_ERROR);
$base = $corpus['_meta']['baseValidRecord'];
$cases = $corpus['cases'];

check(count($cases) === 74, 'corpus contains the 74 expected cases');

$serverOnlyTotal = 0;
foreach ($cases as $case) {
    if (!empty($case['serverOnly'])) {
        $serverOnlyTotal++;
    }
}
$serverOnlyAsserted = 0;

foreach ($cases as $case) {
    $id = (string) $case['id'];
    $input = to_validator_input($case['input'], $base);
    $result = validate_registration($input);

    check(
        is_array($result) && array_key_exists('values', $result) && array_key_exists('errors', $result),
        "[{$id}] validate_registration() returns values and errors"
    );

    $errorsMatch = ($result['errors'] ?? null) === $case['expectedErrors'];
    check($errorsMatch, "[{$id}] errors match expectedErrors exactly and in order");
    if (!$errorsMatch) {
        echo '    expected: ' . json_encode($case['expectedErrors'], JSON_UNESCAPED_UNICODE) . "\n";
        echo '    actual:   ' . json_encode($result['errors'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
    }

    foreach ($case['normalized'] ?? [] as $field => $expected) {
        check(
            array_key_exists($field, $result['values']) && $result['values'][$field] === $expected,
            "[{$id}] normalized {$field} is the expected value"
        );
    }

    // Proves each boundary case really carries the recorded UTF-8 byte length.
    foreach ($case['expectedBytes'] ?? [] as $field => $bytes) {
        check(
            strlen((string) ($input[$field] ?? '')) === $bytes,
            "[{$id}] {$field} uses the recorded UTF-8 byte length"
        );
    }

    if (!empty($case['serverOnly'])) {
        $serverOnlyAsserted++;
    }
}

check(
    $serverOnlyTotal > 0 && $serverOnlyAsserted === $serverOnlyTotal,
    "all {$serverOnlyTotal} serverOnly cases were asserted on the server"
);

// Submitted-role allow-list (RF-2.4). Only the two supported roles pass;
// admin, unknown values, wrong case, missing/null, and non-scalars are rejected.
check(is_allowed_registration_role('estudiante') === true, 'allow-list accepts estudiante');
check(is_allowed_registration_role('docente') === true, 'allow-list accepts docente');
check(is_allowed_registration_role('admin') === false, 'allow-list rejects admin');
check(is_allowed_registration_role('visitante') === false, 'allow-list rejects an unknown role');
check(is_allowed_registration_role('ESTUDIANTE') === false, 'allow-list rejects a wrong-case role');
check(is_allowed_registration_role('') === false, 'allow-list rejects an empty role');
check(is_allowed_registration_role(null) === false, 'allow-list rejects a null/missing role');
$rolelessInput = [];
check(
    is_allowed_registration_role($rolelessInput['rol'] ?? null) === false,
    'allow-list rejects a missing rol key'
);
check(is_allowed_registration_role(['estudiante']) === false, 'allow-list rejects a non-scalar role');

// Non-scalar submissions must be coerced to empty values without type errors
// (strict_types is on: handle before any string/mb function).
$nonScalar = validate_registration([
    'name' => ['Ana'],
    'lastname' => ['Pérez'],
    'email' => ['ana@example.com'],
    'phone' => ['88887777'],
    'id' => ['8-1234'],
    'password' => ['secreto1'],
    'password_confirm' => ['secreto1'],
    'terms' => ['on'],
]);
check(
    $nonScalar['errors'] === [
        'name' => ['Ingresa tu nombre.'],
        'lastname' => ['Ingresa tu apellido.'],
        'email' => ['Ingresa tu correo electrónico.'],
        'phone' => ['Ingresa tu teléfono.'],
        'id' => ['Ingresa tu cédula o ID.'],
        'password' => ['Ingresa una contraseña.'],
        'password_confirm' => ['Confirma tu contraseña.'],
        'terms' => ['Debes aceptar los términos y condiciones.'],
    ],
    'non-scalar field submissions are coerced to empty values without type errors'
);
check(normalize_phone(['88887777']) === '', 'normalize_phone() coerces a non-scalar to empty');
check(normalize_phone(null) === '', 'normalize_phone() coerces null to empty');

echo "\n";
printf("Assertions: %d, Failures: %d\n", $assertions, $failures);
exit($failures === 0 ? 0 : 1);
