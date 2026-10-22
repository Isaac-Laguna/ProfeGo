<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | ProfeGo</title>
    <meta name="description" content="Inicia sesión en ProfeGo para acceder a tu cuenta de estudiante o docente.">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body class="login-page">
    <header class="login-header">
        <a class="brand login-logo" href="../index.php" aria-label="ProfeGo, volver al inicio">
            <span class="brand__crop">
                <img src="../assets/images/logo.png" alt="ProfeGo" width="1254" height="1254">
            </span>
        </a>
    </header>

    <main class="login-main">
        <section class="login-panel" aria-labelledby="login-title">
            <h1 class="login-panel__title" id="login-title">Iniciar sesión</h1>

            <form class="login-card" novalidate>
                <div class="login-field">
                    <label for="login-email">Correo electrónico o ID</label>
                    <input
                        id="login-email"
                        name="email"
                        type="email"
                        autocomplete="username"
                    >
                </div>

                <div class="login-field">
                    <label for="login-password">Contraseña</label>
                    <input
                        id="login-password"
                        name="password"
                        type="password"
                        placeholder="************"
                        autocomplete="current-password"
                    >
                </div>
            </form>

            <a class="login-link" href="#">¿Olvidaste tu contraseña?</a>

            <p class="login-register">
                <span>¿No tienes cuenta?</span>
                <a href="#">Regístrate aquí</a>
            </p>

            <button class="login-submit" type="button">Iniciar sesión</button>
        </section>
    </main>
</body>
</html>
