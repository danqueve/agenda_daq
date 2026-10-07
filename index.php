<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

iniciar_sesion();
requireLogin();

$usuarioId = usuario_actual_id();
?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F2F2F7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
    <title>Agenda DAQ</title>
    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
    <div class="app-shell" x-data="appShell()" x-init="init()">
        <nav class="tabbar" aria-label="Navegación principal">
            <template x-for="tab in tabs" :key="tab.id">
                <button
                    class="tabbar__item"
                    type="button"
                    :aria-current="activo === tab.id ? 'page' : null"
                    @click="activo = tab.id"
                >
                    <span class="tabbar__icon">
                        <i :data-lucide="tab.icono"></i>
                    </span>
                    <span class="tabbar__label" x-text="tab.etiqueta"></span>
                    <span class="tabbar__badge" x-show="tab.id === 'hoy' && badgeHoy > 0" x-text="badgeHoy"></span>
                </button>
            </template>
        </nav>

        <div class="app-shell__content">
            <section class="screen" x-show="activo === 'hoy'">
                <header class="navbar">
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Hoy</h1>
                    </div>
                </header>
                <div class="screen__body">
                    <p class="subhead" x-text="fechaHoy"></p>
                    <div class="empty-state">
                        <i data-lucide="calendar-check"></i>
                        <p class="body-text">No tenés recontactos para hoy</p>
                    </div>
                </div>
            </section>

            <section class="screen" x-show="activo === 'contactos'">
                <header class="navbar">
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Contactos</h1>
                        <button class="navbar__button navbar__button--large" type="button" aria-label="Nuevo contacto">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                </header>
                <div class="screen__body">
                    <div class="empty-state">
                        <i data-lucide="users"></i>
                        <p class="body-text">Todavía no cargaste ningún contacto</p>
                    </div>
                </div>
            </section>

            <section class="screen" x-show="activo === 'agenda'">
                <header class="navbar">
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Agenda</h1>
                    </div>
                </header>
                <div class="screen__body">
                    <div class="empty-state">
                        <i data-lucide="calendar-days"></i>
                        <p class="body-text">Sin recontactos agendados</p>
                    </div>
                </div>
            </section>

            <section class="screen" x-show="activo === 'ajustes'">
                <header class="navbar">
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Ajustes</h1>
                    </div>
                </header>
                <div class="screen__body">
                    <div class="group">
                        <a class="cell" href="logout.php">
                            <span class="cell__icon-box" style="background:var(--red)"><i data-lucide="log-out"></i></span>
                            <span class="cell__content"><span class="cell__title">Cerrar sesión</span></span>
                            <span class="cell__chevron"><i data-lucide="chevron-right"></i></span>
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script src="assets/vendor/alpine.min.js" defer></script>
    <script src="assets/vendor/lucide/lucide.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
