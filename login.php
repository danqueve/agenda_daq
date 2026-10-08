<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';

iniciar_sesion();

if (usuario_actual_id() !== null) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión expirada. Probá de nuevo.';
    } else {
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $recordar = !empty($_POST['recordar']);

        $resultado = login($usuario, $password, $recordar, cliente_ip());

        if ($resultado === 'ok') {
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }

        $error = $resultado === 'bloqueado'
            ? 'Demasiados intentos. Esperá 15 minutos y probá de nuevo.'
            : 'Usuario o contraseña incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#06070B">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Agenda">
    <title>Agenda DAQ — Ingresar</title>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/pastel.css">
</head>
<body>
    <main class="login" aria-labelledby="login-titulo">
        <span class="login__deco login__deco--1" aria-hidden="true"></span>
        <span class="login__deco login__deco--2" aria-hidden="true"></span>
        <span class="login__deco login__deco--3" aria-hidden="true"></span>
        <span class="login__deco login__deco--4" aria-hidden="true"></span>

        <div class="login__marca">
            <span class="login__marca-icono" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg>
            </span>
            <span>Agenda DAQ</span>
        </div>

        <form class="login__card" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="recordar" value="1">

            <div>
                <h1 class="login__titulo" id="login-titulo">Iniciar sesión</h1>
                <p class="login__sub">Tu agenda de consultas y recontactos.</p>
            </div>

            <?php if ($error): ?>
                <p class="login__error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <label class="login__campo" for="login-usuario">
                <span class="login__label">Usuario</span>
                <input class="login__input" id="login-usuario" name="usuario" type="text" autocomplete="username" required autofocus>
            </label>

            <label class="login__campo" for="login-pw">
                <span class="login__label">Contraseña</span>
                <span class="login__pw">
                    <input class="login__input" id="login-pw" name="password" type="password" autocomplete="current-password" required>
                    <button class="login__ojo" type="button" aria-label="Mostrar contraseña" aria-pressed="false" aria-controls="login-pw" data-pw-toggle>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </span>
            </label>

            <button class="login__boton" type="submit">Ingresar</button>
            <p class="login__nota">La sesión queda guardada en este dispositivo.</p>
        </form>
    </main>

    <script src="assets/js/pwa.js"></script>
    <script>
        document.querySelectorAll('[data-pw-toggle]').forEach((btn) => {
            const input = document.getElementById(btn.getAttribute('aria-controls'));
            btn.addEventListener('click', () => {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', String(show));
            });
        });
    </script>
</body>
</html>
