<?php

declare(strict_types=1);

/*
 * Registration domain service for spec 001 (create-account).
 *
 * Owns uniqueness pre-checks, credential hashing, and the atomic creation of
 * an account plus its optional tutor profile. The role is supplied by the
 * caller (the controller injects its allow-listed POST role); this service
 * never reads request data, superglobals, or the GET preselection.
 *
 * The fail_tutor_profile option is a test-only seam for proving RF-3
 * atomicity. It is gated by PHP_SAPI === 'cli' and is never populated from a
 * request, so it cannot be triggered over HTTP.
 */

/**
 * Probe the three uniqueness columns and return one Spanish message per
 * duplicated field. Column identifiers are fixed internal literals; only
 * values are bound as parameters (RNF-3).
 *
 * @param array $values normalized values keyed by name, email, phone and id
 * @return array<string, string> field => duplicate message
 */
function find_duplicate_fields(PDO $pdo, array $values): array
{
    $probes = [
        'email' => ['correo', (string) ($values['email'] ?? ''), 'Este correo electrónico ya está registrado.'],
        'phone' => ['telefono', (string) ($values['phone'] ?? ''), 'Este teléfono ya está registrado.'],
        'id' => ['documento', (string) ($values['id'] ?? ''), 'Esta cédula o ID ya está registrada.'],
    ];

    $duplicates = [];
    foreach ($probes as $field => [$column, $value, $message]) {
        // $column comes only from the literals above, never from user input.
        $statement = $pdo->prepare("SELECT 1 FROM usuarios WHERE {$column} = :value LIMIT 1");
        $statement->execute([':value' => $value]);
        if ($statement->fetchColumn() !== false) {
            $duplicates[$field] = $message;
        }
    }

    return $duplicates;
}

/**
 * Create one account and, for a tutor, its pending profile in the same
 * transaction. A unique collision is mapped to the same duplicate errors as
 * the pre-check; any other failure rolls back and is rethrown.
 *
 * @param array $values normalized values including name, lastname, email,
 *                      phone, id, password and the caller-supplied role
 * @param array $options test-only options; fail_tutor_profile is CLI-only
 * @return array{ok: true, id: int}|array{ok: false, duplicates: array<string, string>}
 */
function register_account(PDO $pdo, array $values, array $options = []): array
{
    $hash = password_hash((string) ($values['password'] ?? ''), PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Unable to hash the password.');
    }

    $pdo->beginTransaction();
    try {
        $account = $pdo->prepare(
            'INSERT INTO usuarios (nombre, apellido, correo, telefono, documento, password_hash, rol) '
            . 'VALUES (:nombre, :apellido, :correo, :telefono, :documento, :password_hash, :rol)'
        );
        $account->execute([
            ':nombre' => (string) ($values['name'] ?? ''),
            ':apellido' => (string) ($values['lastname'] ?? ''),
            ':correo' => (string) ($values['email'] ?? ''),
            ':telefono' => (string) ($values['phone'] ?? ''),
            ':documento' => (string) ($values['id'] ?? ''),
            ':password_hash' => $hash,
            ':rol' => (string) ($values['role'] ?? ''),
        ]);
        $id = (int) $pdo->lastInsertId();

        if ((string) ($values['role'] ?? '') === 'docente') {
            // Test-only atomicity seam: never request data, CLI only.
            if (($options['fail_tutor_profile'] ?? false) === true && PHP_SAPI === 'cli') {
                throw new RuntimeException('forced tutor profile failure');
            }

            $profile = $pdo->prepare(
                'INSERT INTO perfiles_docente '
                . '(id_docente, estado_verificacion, calificacion_prom, total_tutoria) '
                . 'VALUES (:id_docente, :estado_verificacion, :calificacion_prom, :total_tutoria)'
            );
            $profile->execute([
                ':id_docente' => $id,
                ':estado_verificacion' => 'pendiente',
                ':calificacion_prom' => 0.00,
                ':total_tutoria' => 0,
            ]);
        }

        $pdo->commit();

        return ['ok' => true, 'id' => $id];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception->getCode() === '23000') {
            $duplicates = find_duplicate_fields($pdo, $values);
            if ($duplicates !== []) {
                return ['ok' => false, 'duplicates' => $duplicates];
            }
        }
        throw $exception;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
