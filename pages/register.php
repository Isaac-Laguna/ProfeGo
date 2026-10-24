<?php
    declare(strict_types=1);

    try {
        session_start();

        require_once __DIR__ . '/../database/database.php';

        $name = $_POST['name'] ?? '';
        $lastname = $_POST['lastname'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $cellphone = $_POST['phone'] ?? '';
        $id = $_POST['id'] ?? '';

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nombre, apellido, correo, telefono, documento, password_hash, rol) 
            VALUES (:name, :lastname, :email, :cellphone, :id, :passwordHash, "estudiante")'
        );

        $stmt->execute([
            ':name' => $name,
            ':lastname' => $lastname,
            ':email' => $email,
            ':cellphone' => $cellphone,
            ':id' => $id,
            ':passwordHash' => $passwordHash,
        ]);

        $_SESSION['registration_success'] = true;

        header('Location: login.php?registro=ok', true, 303);
        exit;
    }
    catch (PDOException $e) {
        error_log("Error al registrar al usuario: " . $e->getMessage());
        $_SESSION['registration_error'] = true;
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta | ProfeGo</title>
    <meta name="description" content="Regístrate en ProfeGo como estudiante.">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/register.css">
</head>
<body class="register-page">
    <header class="register-header">
        <a class="brand register-logo" href="../index.php" aria-label="ProfeGo, volver al inicio">
            <span class="brand__crop">
                <img src="../assets/images/logo.png" alt="ProfeGo" width="1254" height="1254">
            </span>
        </a>
    </header>

    <main class="register-main">
        <section class="register-panel" aria-labelledby="register-title">
            <h1 class="register-panel__title" id="register-title">Crear cuenta</h1>

            <form class="register-form" novalidate>
                <p class="register-role">Estudiante</p>

                <div class="register-card">
                    <div class="register-field">
                        <label for="register-name">Nombre</label>
                        <input
                            id="register-name"
                            name="name"
                            type="text"
                            autocomplete="given-name"
                            required
                        >
                    </div>

                    <div class="register-field">
                        <label for="register-lastname">Apellido</label>
                        <input
                            id="register-lastname"
                            name="lastname"
                            type="text"
                            autocomplete="family-name"
                            required
                        >
                    </div>

                    <div class="register-field">
                        <label for="register-email">Correo electrónico o cédula/ID*</label>
                        <input
                            id="register-email"
                            name="email"
                            type="text"
                            autocomplete="username"
                            required
                        >
                    </div>

                    <div class="register-field">
                        <label for="register-password">Crear contraseña*</label>
                        <input
                            id="register-password"
                            name="password"
                            type="password"
                            placeholder="************"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="register-field">
                        <label for="register-password-confirm">Confirmar contraseña*</label>
                        <input
                            id="register-password-confirm"
                            name="password_confirm"
                            type="password"
                            placeholder="************"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                </div>

                <div class="register-terms">
                    <input
                        class="register-terms__checkbox"
                        id="register-terms"
                        name="terms"
                        type="checkbox"
                        required
                    >
                    <label for="register-terms">
                        Acepto los <a href="#">términos</a> y <a href="#">condiciones</a> y la
                        <a href="#">Política de Privacidad.</a>
                    </label>
                </div>

                <p class="register-login">
                    <span>¿Ya tienes una cuenta?</span>
                    <a href="login.php">Inicia sesión aquí.</a>
                </p>

                <button class="register-submit" type="button">Crear Cuenta</button>
            </form>
        </section>
    </main>
</body>
</html>
