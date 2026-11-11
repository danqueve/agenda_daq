<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/sas.php';

iniciar_sesion();
requireLogin();

$usuarioId = usuario_actual_id();

$stmtUsuario = Db::get()->prepare('SELECT usuario, nombre FROM usuarios WHERE id = :id');
$stmtUsuario->execute(['id' => $usuarioId]);
$usuarioActual = $stmtUsuario->fetch() ?: [];
$usuarioNombre = trim((string) ($usuarioActual['nombre'] ?? '')) ?: ($usuarioActual['usuario'] ?? 'Usuario');
$usuarioRol = usuario_actual_rol() ?? ROL_SUPERVISOR;
?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Agenda">
    <title>Agenda IC</title>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/pantallas/hoy.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/pantallas/contactos.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/pantallas/calendario.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/pantallas/ficha.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/pantallas/contacto.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/pantallas/notas.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/pantallas/ajustes.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/pantallas/pagos.css')) ?>">
</head>
<body>
    <div class="app-shell" x-data="appShell()" x-init="init()" :class="{'modo-contactos': activo === 'contactos'}">
        <div class="banner" x-show="sinConexion" x-cloak>Sin conexión. Mostrando los últimos datos guardados.</div>
        <nav class="tabbar" aria-label="Navegación principal">
            <button class="tabbar__brand" type="button" @click="activo = 'hoy'" aria-label="Ir al inicio de Agenda IC">
                <span class="tabbar__brand-mark"><i data-lucide="calendar-check-2"></i></span>
                <span>Agenda IC</span>
            </button>
            <div class="tabbar__items">
                <template x-for="tab in tabs" :key="tab.id">
                    <button
                        class="tabbar__item"
                        :class="{'tabbar__item--solo-escritorio': tab.soloEscritorio}"
                        type="button"
                        :aria-current="activo === tab.id ? 'page' : null"
                        @click="activo = tab.id"
                    >
                        <span class="tabbar__icon">
                            <i :data-lucide="tab.icono"></i>
                        </span>
                        <span class="tabbar__label" x-text="tab.etiqueta"></span>
                        <span class="tabbar__badge" x-show="tab.id === 'hoy' && badgeHoy > 0" x-text="badgeHoy"></span>
                        <span class="tabbar__badge" x-show="tab.id === 'pagos' && badgePagos > 0" x-text="badgePagos"></span>
                    </button>
                </template>
            </div>
            <button
                class="tabbar__item tabbar__item--ajustes-movil"
                type="button"
                :aria-current="activo === 'ajustes' ? 'page' : null"
                @click="activo = 'ajustes'"
            >
                <span class="tabbar__icon"><i data-lucide="settings"></i></span>
                <span class="tabbar__label">Ajustes</span>
            </button>
            <button
                class="tabbar__perfil"
                type="button"
                :aria-current="activo === 'ajustes' ? 'page' : null"
                @click="activo = 'ajustes'"
                aria-label="Ir a Ajustes"
            >
                <span
                    class="avatar"
                    style="background:var(--tint)"
                    x-text='iniciales(<?= json_encode($usuarioNombre, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>)'
                ></span>
                <span class="tabbar__perfil-nombre"><?= e($usuarioNombre) ?></span>
            </button>
        </nav>

        <div class="app-shell__content">
            <?php require __DIR__ . '/app/vistas/pantallas/hoy.php'; ?>

            <?php require __DIR__ . '/app/vistas/pantallas/contactos.php'; ?>

            <?php require __DIR__ . '/app/vistas/pantallas/calendario.php'; ?>

            <?php require __DIR__ . '/app/vistas/pantallas/notas.php'; ?>

            <?php require __DIR__ . '/app/vistas/sheets/nota_editor.php'; ?>

            <?php require __DIR__ . '/app/vistas/sheets/eliminar_nota.php'; ?>

            <?php require __DIR__ . '/app/vistas/pantallas/pagos.php'; ?>

            <?php require __DIR__ . '/app/vistas/pantallas/ajustes.php'; ?>

        </div>

        <?php require __DIR__ . '/app/vistas/sheets/contacto.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/seguimiento.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/compromiso.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/pago_resolver.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/pago_rapido.php'; ?>

        <?php require __DIR__ . '/app/vistas/parciales/aviso_deshacer.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/duplicado.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/push.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/etiquetas.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/password.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/sesiones.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/ficha.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/eliminar_contacto.php'; ?>

        <?php require __DIR__ . '/app/vistas/sheets/sas_vincular.php'; ?>

        <datalist id="conceptos-pagos">
            <template x-for="opcion in conceptoOpciones()" :key="opcion">
                <option :value="opcion"></option>
            </template>
        </datalist>
        <div class="hud-capa" x-show="hudVisible" x-transition.opacity x-cloak>
            <div class="hud">
                <i :data-lucide="hudIcono"></i>
                <span x-text="hudTexto"></span>
            </div>
        </div>
    </div>

    <script>
        window.APP_CONFIG = {
            sasHabilitado: <?= SasDb::habilitado() ? 'true' : 'false' ?>,
            csrf: <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            usuario: <?= json_encode(
                ['id' => $usuarioId, 'nombre' => $usuarioNombre, 'rol' => $usuarioRol],
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) ?>,
        };
    </script>
    <script src="<?= e(asset('assets/vendor/alpine.min.js')) ?>" defer></script>
    <script src="<?= e(asset('assets/vendor/lucide/lucide.min.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/helpers.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/contactos.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/seguimientos.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/panel.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/ajustes.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/notas.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/pagos.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/pagos_rapido.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/sas.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/pwa.js')) ?>"></script>
</body>
</html>
