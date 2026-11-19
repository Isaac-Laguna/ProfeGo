<?php

declare(strict_types=1);

/*
 * Request controller for spec 001 (create-account).
 *
 * build_registration_response(server, post, session, query, options) is the
 * single pipeline shared by the web entry point and the CLI verification
 * harnesses. It reads no superglobals: every request input is an argument,
 * and the session array is passed by reference for the CSRF token and the
 * one-time success flag.
 *
 * Precedence follows the approved plan (plan.md, "Request lifecycle and
 * controller algorithm"): on POST, non-field failures (CSRF, then the role
 * allow-list) are evaluated before field validation, which is evaluated
 * before duplicate pre-checks and persistence. Only the generic form-level
 * alert is used for non-field/database failures; field and duplicate errors
 * are field-associated and never accompanied by that alert. The options
 * argument is a CLI/test seam only and is never populated from request data.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/registration_service.php';
require_once __DIR__ . '/register_view.php';

const REGISTRATION_FORM_ERROR = 'No se pudo completar el registro. Inténtalo de nuevo.';

/** Coerce one untrusted field to a string; arrays/objects become ''. */
function registration_raw_value(array $post, string $key): string
{
    $value = $post[$key] ?? null;

    return is_scalar($value) ? (string) $value : '';
}

/** Raw, as-typed non-password values preserved in error re-renders (RF-11/RF-15). */
function registration_raw_values(array $post): array
{
    $raw = [];
    foreach (['name', 'lastname', 'email', 'phone', 'id'] as $field) {
        $raw[$field] = registration_raw_value($post, $field);
    }

    return $raw;
}

/** Fixed server-rendered polite-announcement copy for one role. */
function registration_live_region(string $role): string
{
    return $role === 'docente'
        ? 'Tipo de cuenta seleccionado: Docente.'
        : 'Tipo de cuenta seleccionado: Estudiante.';
}

/**
 * Resolve the GET role preselection for RF-2.3.
 *
 * PHP's parsed query ($_GET) is last-value-wins, so a repeated `rol` key or an
 * injected `rol[]` cannot be detected from $query alone. The raw QUERY_STRING
 * is the authority for key shape: exactly one literal `rol` key, with no
 * bracket syntax, must be present before its value is trusted. The value must
 * then match the allow-list exactly. Repeated, bracketed, unknown, empty,
 * case-variant or non-string values all resolve to the default student role.
 * Preselection only seeds initial rendering: it never writes, never submits,
 * and is always overridden by the POST role (RF-2.4).
 */
function preselected_role_from_request(array $server, array $query): string
{
    $raw = (isset($server['QUERY_STRING']) && is_string($server['QUERY_STRING']))
        ? $server['QUERY_STRING']
        : '';
    if ($raw !== '') {
        $rolKeys = 0;
        foreach (explode('&', $raw) as $pair) {
            $key = urldecode(explode('=', $pair, 2)[0]);
            if ($key === 'rol') {
                $rolKeys++;
            } elseif (preg_match('/\Arol\[/', $key) === 1) {
                // Array-style injection such as rol[]=docente.
                return 'estudiante';
            }
        }
        if ($rolKeys !== 1) {
            return 'estudiante';
        }
    }

    $value = $query['rol'] ?? null;

    return is_allowed_registration_role($value) ? (string) $value : 'estudiante';
}

/**
 * Build one registration response from simulated request data.
 *
 * @param array $server  server-like array containing REQUEST_METHOD
 * @param array $post    untrusted POST fields
 * @param array $session session state by reference (CSRF token, notice flag)
 * @param array $query   query-string values (GET preselection)
 * @param array $options CLI/test seam only; never populated from a request
 * @return array{status: int, headers: array, body: string}
 */
function build_registration_response(
    array $server,
    array $post,
    array &$session,
    array $query = [],
    array $options = []
): array {
    $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));

    if ($method !== 'POST') {
        $token = ensure_csrf_token($session);
        $role = preselected_role_from_request($server, $query);

        return [
            'status' => 200,
            'headers' => [],
            'body' => render_register_page([
                'values' => [],
                'role' => $role,
                'errors' => [],
                'form_error' => null,
                'csrf_token' => $token,
                'terms' => false,
                'live_region' => registration_live_region($role),
                'password_values_empty' => true,
            ]),
        ];
    }

    $raw = registration_raw_values($post);
    $terms = terms_is_accepted($post['terms'] ?? null);
    $submittedRole = $post['rol'] ?? null;

    // 1. Non-field failures first: CSRF, then the submitted-role allow-list.
    if (!submitted_csrf_is_valid($session, $post['csrf_token'] ?? null)) {
        return registration_form_error($raw, $submittedRole, $terms, $session);
    }
    if (!is_allowed_registration_role($submittedRole)) {
        return registration_form_error($raw, $submittedRole, $terms, $session);
    }

    // 2. Field validation: re-render with raw values and empty passwords.
    $validated = validate_registration($post);
    if ($validated['errors'] !== []) {
        return registration_field_errors($raw, $submittedRole, $terms, $session, $validated['errors']);
    }

    // Only the controller turns the allow-listed POST role into a stored role.
    $values = $validated['values'];
    $values['role'] = $submittedRole;

    // 3. Duplicate pre-check, then atomic persistence; any failure is non-field.
    try {
        $pdo = profego_pdo();
        $duplicates = find_duplicate_fields($pdo, $values);
        if ($duplicates !== []) {
            return registration_field_errors($raw, $submittedRole, $terms, $session, $duplicates);
        }

        $result = register_account($pdo, $values, $options);
        if (($result['ok'] ?? false) === true) {
            $session['registration_notice'] = true;

            return [
                'status' => 303,
                'headers' => ['Location: login.php?registro=ok'],
                'body' => '',
            ];
        }
        if (!empty($result['duplicates'])) {
            return registration_field_errors($raw, $submittedRole, $terms, $session, $result['duplicates']);
        }

        return registration_form_error($raw, $submittedRole, $terms, $session);
    } catch (Throwable $exception) {
        // Never expose the exception message, SQLSTATE, stack trace, or paths.
        return registration_form_error($raw, $submittedRole, $terms, $session);
    }
}

/** Field-error re-render (validation or duplicate): 200, raw values, empty passwords. */
function registration_field_errors(
    array $raw,
    $submittedRole,
    bool $terms,
    array &$session,
    array $errors
): array {
    return [
        'status' => 200,
        'headers' => [],
        'body' => render_register_page([
            'values' => $raw,
            'role' => $submittedRole,
            'errors' => registration_normalize_errors($errors),
            'form_error' => null,
            'csrf_token' => ensure_csrf_token($session),
            'terms' => $terms,
            'live_region' => registration_live_region($submittedRole),
            'password_values_empty' => true,
        ]),
    ];
}

/**
 * The view's model contract is field => list of messages. Validation already
 * returns lists, while the domain service returns field => single message;
 * normalize both shapes here so every field error is rendered.
 */
function registration_normalize_errors(array $errors): array
{
    $normalized = [];
    foreach ($errors as $field => $message) {
        $normalized[$field] = is_array($message) ? array_values($message) : [(string) $message];
    }

    return $normalized;
}

/** Non-field failure: 200, only the generic form-level alert, no field errors. */
function registration_form_error(array $raw, $submittedRole, bool $terms, array &$session): array
{
    $role = is_allowed_registration_role($submittedRole) ? $submittedRole : 'estudiante';

    return [
        'status' => 200,
        'headers' => [],
        'body' => render_register_page([
            'values' => $raw,
            'role' => $role,
            'errors' => [],
            'form_error' => REGISTRATION_FORM_ERROR,
            'csrf_token' => ensure_csrf_token($session),
            'terms' => $terms,
            'live_region' => registration_live_region($role),
            'password_values_empty' => true,
        ]),
    ];
}
