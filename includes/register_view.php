<?php

declare(strict_types=1);

/*
 * Server-rendered registration view for spec 001 (create-account).
 *
 * render_register_page(array $model) returns the full HTML document.
 * Model contract (missing keys use safe defaults):
 *   values                assoc array of raw/normalized field values
 *   role                  'estudiante' | 'docente' (default 'estudiante')
 *   errors                field => list of Spanish messages
 *   form_error            string|null; when set, only the form-level alert is
 *                         rendered and field errors are ignored
 *   csrf_token            string; per-session token for the hidden field
 *   terms                 bool; checked state of the terms checkbox
 *   live_region           string; text for the polite status region
 *   password_values_empty bool; the controller guarantees passwords are omitted
 *
 * Every dynamic value/attribute passes through e(). Passwords are never read
 * from the model. This module emits markup only; the enhancement script is
 * added by a later task.
 */

if (!function_exists('e')) {
    /** Escape one dynamic value for safe inclusion in HTML text or attributes. */
    function e($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/** Render the complete registration HTML document from a model array. */
function render_register_page(array $model): string
{
    $values = (isset($model['values']) && is_array($model['values'])) ? $model['values'] : [];
    $role = (($model['role'] ?? 'estudiante') === 'docente') ? 'docente' : 'estudiante';
    $errors = (isset($model['errors']) && is_array($model['errors'])) ? $model['errors'] : [];
    $formError = $model['form_error'] ?? null;
    $formError = is_string($formError) ? $formError : null;
    $csrfToken = $model['csrf_token'] ?? '';
    $termsChecked = !empty($model['terms']);

    $roleLabel = $role === 'docente' ? 'Docente' : 'Estudiante';
    $roleName = $role === 'docente'
        ? 'Tipo de cuenta: Docente, cambiar a Estudiante'
        : 'Tipo de cuenta: Estudiante, cambiar a Docente';
    $defaultLive = $role === 'docente'
        ? 'Tipo de cuenta seleccionado: Docente.'
        : 'Tipo de cuenta seleccionado: Estudiante.';
    $liveRegion = $model['live_region'] ?? $defaultLive;
    $liveRegion = is_string($liveRegion) ? $liveRegion : $defaultLive;

    // A form-level failure suppresses every field error in the presentation.
    $fieldErrors = $formError === null ? $errors : [];

    $fieldOrder = ['name', 'lastname', 'email', 'phone', 'id', 'password', 'password_confirm', 'terms'];
    $firstInvalid = null;
    foreach ($fieldOrder as $key) {
        if (!empty($fieldErrors[$key]) && is_array($fieldErrors[$key])) {
            $firstInvalid = $key;
            break;
        }
    }

    $fieldDefinitions = [
        'name' => ['id' => 'register-name', 'label' => 'Nombre', 'type' => 'text', 'autocomplete' => 'given-name'],
        'lastname' => ['id' => 'register-lastname', 'label' => 'Apellido', 'type' => 'text', 'autocomplete' => 'family-name'],
        'email' => ['id' => 'register-email', 'label' => 'Correo electrónico', 'type' => 'text', 'autocomplete' => 'username'],
        'phone' => ['id' => 'register-phone', 'label' => 'Teléfono', 'type' => 'text', 'autocomplete' => 'tel'],
        'id' => ['id' => 'register-id', 'label' => 'Cédula/ID', 'type' => 'text', 'autocomplete' => 'off'],
        'password' => ['id' => 'register-password', 'label' => 'Crear contraseña', 'type' => 'password', 'autocomplete' => 'new-password'],
        'password_confirm' => ['id' => 'register-password-confirm', 'label' => 'Confirmar contraseña', 'type' => 'password', 'autocomplete' => 'new-password'],
    ];
    $passwordFields = ['password' => true, 'password_confirm' => true];

    $fieldsHtml = '';
    foreach ($fieldDefinitions as $key => $definition) {
        $id = $definition['id'];
        $hasError = !empty($fieldErrors[$key]) && is_array($fieldErrors[$key]);
        $value = isset($passwordFields[$key]) ? '' : ($values[$key] ?? '');

        $attributes = 'id="' . $id . '"'
            . ' name="' . $key . '"'
            . ' type="' . $definition['type'] . '"'
            . ' autocomplete="' . $definition['autocomplete'] . '"'
            . ' value="' . e($value) . '"';
        if (isset($passwordFields[$key])) {
            $attributes .= ' placeholder="************"';
        }
        if ($hasError) {
            $attributes .= ' aria-invalid="true" aria-describedby="' . e($id . '-error') . '"';
        }
        if ($firstInvalid === $key) {
            $attributes .= ' autofocus';
        }
        $attributes .= ' required aria-required="true"';

        $errorHtml = '';
        if ($hasError) {
            $errorHtml = "\n" . '                        <div class="register-field__error" id="' . e($id . '-error') . '">';
            foreach ($fieldErrors[$key] as $message) {
                $errorHtml .= "\n" . '                            <p>' . e($message) . '</p>';
            }
            $errorHtml .= "\n" . '                        </div>';
        }

        $fieldsHtml .= "\n" . '                    <div class="register-field">'
            . "\n" . '                        <label for="' . $id . '">' . e($definition['label'] . '*') . '</label>'
            . "\n" . '                        <input ' . $attributes . '>'
            . $errorHtml
            . "\n" . '                    </div>';
    }

    $termsAttributes = 'class="register-terms__checkbox" id="register-terms" name="terms" type="checkbox"';
    if ($termsChecked) {
        $termsAttributes .= ' checked';
    }
    $termsHasError = !empty($fieldErrors['terms']) && is_array($fieldErrors['terms']);
    if ($termsHasError) {
        $termsAttributes .= ' aria-invalid="true" aria-describedby="register-terms-error"';
    }
    if ($firstInvalid === 'terms') {
        $termsAttributes .= ' autofocus';
    }
    $termsAttributes .= ' required aria-required="true"';

    $termsErrorHtml = '';
    if ($termsHasError) {
        $termsErrorHtml = "\n" . '                    <div class="register-field__error" id="register-terms-error">';
        foreach ($fieldErrors['terms'] as $message) {
            $termsErrorHtml .= "\n" . '                        <p>' . e($message) . '</p>';
        }
        $termsErrorHtml .= "\n" . '                    </div>';
    }

    $formAlertHtml = '';
    if ($formError !== null) {
        $formAlertHtml = "\n" . '                <div class="register-form-error" role="alert">'
            . e($formError)
            . '</div>';
    }

    $html = '<!DOCTYPE html>' . "\n"
        . '<html lang="es">' . "\n"
        . '<head>' . "\n"
        . '    <meta charset="UTF-8">' . "\n"
        . '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n"
        . '    <title>Crear cuenta | ProfeGo</title>' . "\n"
        . '    <meta name="description" content="Regístrate en ProfeGo como estudiante o docente.">' . "\n"
        . '    <link rel="stylesheet" href="../css/main.css">' . "\n"
        . '    <link rel="stylesheet" href="../css/register.css">' . "\n"
        . '</head>' . "\n"
        . '<body class="register-page">' . "\n"
        . '    <header class="register-header">' . "\n"
        . '        <a class="brand register-logo" href="../index.php" aria-label="ProfeGo, volver al inicio">' . "\n"
        . '            <span class="brand__crop">' . "\n"
        . '                <img src="../assets/images/logo.png" alt="ProfeGo" width="1254" height="1254">' . "\n"
        . '            </span>' . "\n"
        . '        </a>' . "\n"
        . '    </header>' . "\n"
        . "\n"
        . '    <main class="register-main">' . "\n"
        . '        <section class="register-panel" aria-labelledby="register-title">' . "\n"
        . '            <h1 class="register-panel__title" id="register-title">Crear cuenta</h1>' . "\n"
        . "\n"
        . '            <form class="register-form" method="post" action="register.php" novalidate>'
        . $formAlertHtml . "\n"
        . '                <button type="button" class="register-role" id="register-role" aria-label="' . e($roleName) . '">' . e($roleLabel) . '</button>' . "\n"
        . '                <input type="hidden" name="rol" id="register-role-value" value="' . e($role) . '">' . "\n"
        . '                <input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">' . "\n"
        . '                <span class="register-visually-hidden" role="status" aria-live="polite">' . e($liveRegion) . '</span>' . "\n"
        . "\n"
        . '                <div class="register-card">' . $fieldsHtml . "\n"
        . '                </div>' . "\n"
        . "\n"
        . '                <div class="register-terms">' . "\n"
        . '                    <input ' . $termsAttributes . '>' . "\n"
        . '                    <label for="register-terms">Acepto los <a href="#">términos</a> y <a href="#">condiciones</a> y la <a href="#">Política de Privacidad.</a>*</label>'
        . $termsErrorHtml . "\n"
        . '                </div>' . "\n"
        . "\n"
        . '                <p class="register-login">' . "\n"
        . '                    <span>¿Ya tienes una cuenta?</span>' . "\n"
        . '                    <a href="login.php">Inicia sesión aquí.</a>' . "\n"
        . '                </p>' . "\n"
        . "\n"
        . '                <button type="submit" class="register-submit">Crear Cuenta</button>' . "\n"
        . '            </form>' . "\n"
        . '        </section>' . "\n"
        . '    </main>' . "\n"
        . '</body>' . "\n"
        . '</html>' . "\n";

    return $html;
}
