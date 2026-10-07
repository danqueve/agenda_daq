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
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="login-dark" aria-labelledby="login-titulo">
        <div class="login-dark__glow" aria-hidden="true"></div>

        <span class="login-dark__brand">
            <span class="login-dark__brand-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="#0b0d14" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <path d="M8 2v4M16 2v4M3 10h18"/>
                    <path d="m9 16 2 2 4-4"/>
                </svg>
            </span>
            <span>Agenda DAQ</span>
        </span>

        <div class="login-dark__frame">
            <div class="login-dark__card">
                <h1 class="login-dark__title" id="login-titulo">Iniciar sesión</h1>
                <p class="login-dark__sub">Tu agenda de consultas y recontactos.</p>

                <?php if ($error): ?>
                    <p class="login-dark__error"><?= e($error) ?></p>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="recordar" value="1">

                    <div class="login-dark__field">
                        <label class="login-dark__label" for="login-usuario">Usuario</label>
                        <input class="login-dark__input" id="login-usuario" name="usuario" type="text" autocomplete="username" required autofocus>
                    </div>

                    <div class="login-dark__field">
                        <label class="login-dark__label" for="login-pw">Contraseña</label>
                        <div class="login-dark__pw">
                            <input class="login-dark__input" id="login-pw" name="password" type="password" autocomplete="current-password" required>
                            <button class="login-dark__eye" type="button" aria-label="Mostrar contraseña" aria-pressed="false" aria-controls="login-pw" data-pw-toggle>
                                <svg class="login-dark__eye-on" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="login-dark__eye-off" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18M10.6 5.1A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.1M6.6 6.6C3.9 8.3 2 12 2 12s3.6 7 10 7a9.8 9.8 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                            </button>
                        </div>
                    </div>

                    <button class="login-dark__submit" type="submit">Ingresar</button>
                </form>
            </div>
        </div>
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
