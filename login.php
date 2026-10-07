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
    <meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#101214" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Agenda">
    <title>Agenda DAQ — Ingresar</title>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <style>
        .login-pantalla {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px var(--margen-lateral);
        }
        .login-icono {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            background: var(--tint);
            color: var(--tint-contraste);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .login-icono svg { width: 36px; height: 36px; }
        .login-titulo { margin-bottom: 28px; }
        .login-form { width: 100%; max-width: 360px; }
        .login-error {
            color: var(--red);
            font-size: var(--fs-subhead);
            text-align: center;
            margin-bottom: 12px;
        }
        .cell input[type="text"],
        .cell input[type="password"] {
            flex: 1;
            text-align: right;
            border: none;
            background: none;
            font-size: var(--fs-body);
        }
    </style>
</head>
<body>
    <div class="login-pantalla">
        <div class="login-icono"><i data-lucide="calendar-clock"></i></div>
        <h1 class="large-title login-titulo">Agenda DAQ</h1>

        <form class="login-form" method="post" x-data="{ recordar: true }">
            <?= csrf_field() ?>

            <?php if ($error): ?>
                <p class="login-error"><?= e($error) ?></p>
            <?php endif; ?>

            <div class="group">
                <label class="cell">
                    <span class="cell__label">Usuario</span>
                    <input type="text" name="usuario" autocomplete="username" required autofocus>
                </label>
                <label class="cell">
                    <span class="cell__label">Contraseña</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
            </div>

            <div class="group">
                <label class="cell">
                    <span class="cell__content"><span class="cell__title">Mantener sesión iniciada</span></span>
                    <input type="hidden" name="recordar" :value="recordar ? '1' : ''">
                    <button
                        type="button"
                        class="toggle"
                        role="switch"
                        :aria-checked="recordar ? 'true' : 'false'"
                        @click="recordar = !recordar"
                    >
                        <span class="toggle__perilla"></span>
                    </button>
                </label>
            </div>

            <button type="submit" class="btn-principal">Ingresar</button>
        </form>
    </div>

    <script src="assets/vendor/alpine.min.js" defer></script>
    <script src="assets/vendor/lucide/lucide.min.js"></script>
    <script src="assets/js/pwa.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
</body>
</html>
