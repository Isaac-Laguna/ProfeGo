<?php

declare(strict_types=1);

/*
 * Pure registration-field validation for spec 001 (create-account).
 *
 * No database access, no superglobals, no framework. Every untrusted value is
 * coerced with scalar_string() before any string/mb function, so non-scalar
 * form submissions (arrays/objects) cannot raise a TypeError under
 * strict_types. Rule order within a field is fixed: required/empty,
 * length/bytes, character composition/content, format, confirmation
 * (plan.md, "Validation and messages"). One message per failing rule is
 * returned, grouped by field.
 */

/**
 * Coerce a submitted value to a string; arrays, objects and null become ''.
 */
function scalar_string(mixed $value): string
{
    return is_scalar($value) ? (string) $value : '';
}

/**
 * Remove an optional single leading '+', then spaces, hyphens and parentheses.
 * The 7-20 ASCII-digit check is a separate rule in validate_registration().
 */
function normalize_phone(mixed $raw): string
{
    $value = trim(scalar_string($raw));
    if ($value !== '' && $value[0] === '+') {
        $value = substr($value, 1);
    }

    return str_replace([' ', '-', '(', ')'], '', $value);
}

/**
 * Submitted-role allow-list for RF-2.4. Only the two registration roles pass;
 * admin, unknown values, wrong case, missing/null and non-scalars are rejected
 * so the controller can never create an account outside estudiante/docente.
 */
function is_allowed_registration_role(mixed $role): bool
{
    return is_string($role) && ($role === 'estudiante' || $role === 'docente');
}

/**
 * A checked terms box arrives from the form as a scalar value (e.g. 'on'); an
 * unchecked box is absent. Arrays/objects and false-like strings are not
 * accepted.
 */
function terms_is_accepted(mixed $raw): bool
{
    if (!is_scalar($raw)) {
        return false;
    }
    if (is_bool($raw)) {
        return $raw;
    }

    $value = strtolower(trim((string) $raw));

    return $value !== '' && !in_array($value, ['0', 'false', 'off', 'no'], true);
}

/**
 * Name/lastname messages in required -> length (code points) -> composition ->
 * content order.
 *
 * @return list<string>
 */
function validate_name_like(string $value, string $field): array
{
    if ($value === '') {
        return [$field === 'name' ? 'Ingresa tu nombre.' : 'Ingresa tu apellido.'];
    }

    $messages = [];
    $subject = $field === 'name' ? 'El nombre' : 'El apellido';
    $length = mb_strlen($value, 'UTF-8');
    if ($length < 1 || $length > 30) {
        $messages[] = "{$subject} debe tener entre 1 y 30 caracteres.";
    }
    if (preg_match('/^[\p{L} \'-]+$/u', $value) !== 1) {
        $messages[] = "{$subject} solo puede contener letras, espacios, guiones y apóstrofes.";
    }
    if (preg_match('/\p{L}/u', $value) !== 1) {
        $messages[] = "{$subject} debe incluir al menos una letra.";
    }

    return $messages;
}

/**
 * Email messages in required -> length -> format order. ASCII-only is part of
 * the format rule; FILTER_VALIDATE_EMAIL is the authoritative structural check.
 *
 * @return list<string>
 */
function validate_email(string $value): array
{
    if ($value === '') {
        return ['Ingresa tu correo electrónico.'];
    }

    $messages = [];
    if (strlen($value) > 150) {
        $messages[] = 'El correo electrónico debe tener como máximo 150 caracteres.';
    }

    $isAscii = preg_match('/^[\x00-\x7F]*$/', $value) === 1;
    if (!$isAscii || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        $messages[] = 'El correo electrónico no es válido.';
    }

    return $messages;
}

/**
 * @return list<string>
 */
function validate_phone(string $normalized, bool $empty): array
{
    if ($empty) {
        return ['Ingresa tu teléfono.'];
    }

    return preg_match('/\A[0-9]{7,20}\z/', $normalized) === 1
        ? []
        : ['El teléfono debe tener entre 7 y 20 dígitos.'];
}

/**
 * Cédula/ID messages in required -> length -> composition order. The single
 * composition rule covers both the allowed characters and the "at least one
 * alphanumeric character" requirement.
 *
 * @return list<string>
 */
function validate_id(string $value): array
{
    if ($value === '') {
        return ['Ingresa tu cédula o ID.'];
    }

    $messages = [];
    $length = strlen($value);
    if ($length < 5 || $length > 20) {
        $messages[] = 'La cédula o ID debe tener entre 5 y 20 caracteres.';
    }

    $allowed = preg_match('/\A[A-Za-z0-9-]+\z/', $value) === 1;
    $hasAlphanumeric = preg_match('/[A-Za-z0-9]/', $value) === 1;
    if (!$allowed || !$hasAlphanumeric) {
        $messages[] = 'La cédula o ID solo puede contener letras, números y guiones.';
    }

    return $messages;
}

/**
 * Password messages in required -> bytes -> composition order. The bound is
 * UTF-8 bytes (the user-facing message intentionally says "caracteres").
 * Applied to both password and confirmation; only the required message differs.
 *
 * @return list<string>
 */
function validate_password(string $value, string $requiredMessage): array
{
    if ($value === '') {
        return [$requiredMessage];
    }

    $messages = [];
    $bytes = strlen($value);
    if ($bytes < 8 || $bytes > 72) {
        $messages[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
    }

    $hasLetter = preg_match('/[A-Za-z]/', $value) === 1;
    $hasDigit = preg_match('/[0-9]/', $value) === 1;
    if (!$hasLetter || !$hasDigit) {
        $messages[] = 'La contraseña debe incluir al menos una letra y un número.';
    }

    return $messages;
}

/**
 * Validate one registration submission and return normalized values plus
 * field-grouped messages in the fixed rule order. Never trims passwords.
 *
 * @param array $input untrusted request values, keyed by form field name
 * @return array{values: array, errors: array<string, list<string>>}
 */
function validate_registration(array $input): array
{
    $name = trim(scalar_string($input['name'] ?? null));
    $lastname = trim(scalar_string($input['lastname'] ?? null));
    $email = strtolower(trim(scalar_string($input['email'] ?? null)));
    $phoneRaw = scalar_string($input['phone'] ?? null);
    $phone = normalize_phone($phoneRaw);
    $id = trim(scalar_string($input['id'] ?? null));
    $password = scalar_string($input['password'] ?? null);
    $passwordConfirm = scalar_string($input['password_confirm'] ?? null);
    $terms = terms_is_accepted($input['terms'] ?? null);

    $values = [
        'name' => $name,
        'lastname' => $lastname,
        'email' => $email,
        'phone' => $phone,
        'id' => $id,
        'password' => $password,
        'password_confirm' => $passwordConfirm,
        'terms' => $terms,
    ];

    $errors = [];

    $nameMessages = validate_name_like($name, 'name');
    if ($nameMessages !== []) {
        $errors['name'] = $nameMessages;
    }

    $lastnameMessages = validate_name_like($lastname, 'lastname');
    if ($lastnameMessages !== []) {
        $errors['lastname'] = $lastnameMessages;
    }

    $emailMessages = validate_email($email);
    if ($emailMessages !== []) {
        $errors['email'] = $emailMessages;
    }

    $phoneMessages = validate_phone($phone, trim($phoneRaw) === '');
    if ($phoneMessages !== []) {
        $errors['phone'] = $phoneMessages;
    }

    $idMessages = validate_id($id);
    if ($idMessages !== []) {
        $errors['id'] = $idMessages;
    }

    $passwordMessages = validate_password($password, 'Ingresa una contraseña.');
    if ($passwordMessages !== []) {
        $errors['password'] = $passwordMessages;
    }

    $confirmMessages = validate_password($passwordConfirm, 'Confirma tu contraseña.');
    if ($passwordConfirm !== '' && $passwordConfirm !== $password) {
        $confirmMessages[] = 'Las contraseñas no coinciden.';
    }
    if ($confirmMessages !== []) {
        $errors['password_confirm'] = $confirmMessages;
    }

    if (!$terms) {
        $errors['terms'] = ['Debes aceptar los términos y condiciones.'];
    }

    return ['values' => $values, 'errors' => $errors];
}
