<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

iniciar_sesion();
requireLogin();
?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Agenda DAQ — Componentes</title>
    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <style>
        .ui-seccion { margin-bottom: 36px; }
        .ui-seccion__titulo { margin: 0 0 12px var(--margen-lateral); }
        .ui-barra-tema {
            position: sticky;
            top: 0;
            z-index: 40;
            display: flex;
            gap: 8px;
            padding: 10px var(--margen-lateral);
            background: var(--bg-card);
            border-bottom: .5px solid var(--separator);
        }
        .ui-barra-tema button {
            padding: 6px 12px;
            border-radius: 8px;
            background: var(--bg-card-2);
            font-size: var(--fs-subhead);
        }
        .ui-barra-tema button[aria-pressed="true"] { background: var(--tint); color: var(--tint-contraste); }
    </style>
</head>
<body x-data="{ tema: 'auto' }" :data-theme="tema === 'auto' ? null : tema">
    <div class="ui-barra-tema">
        <button type="button" :aria-pressed="tema === 'light'" @click="tema = 'light'">Claro</button>
        <button type="button" :aria-pressed="tema === 'dark'" @click="tema = 'dark'">Oscuro</button>
        <button type="button" :aria-pressed="tema === 'auto'" @click="tema = 'auto'">Auto</button>
    </div>

    <div class="screen">
        <div class="screen__body" style="padding-top:20px">

            <section class="ui-seccion">
                <h2 class="footnote ui-seccion__titulo">Tipografía</h2>
                <div class="group" style="padding:12px 16px">
                    <p class="large-title">Large Title</p>
                    <p class="title2">Title 2</p>
                    <p class="headline">Headline</p>
                    <p class="body-text">Body — texto general e inputs</p>
                    <p class="subhead">Subhead — subtítulo de celda</p>
                    <p class="footnote">Footnote — encabezado de grupo</p>
                    <p class="caption">Caption 12:34</p>
                </div>
            </section>

            <section class="ui-seccion">
                <h2 class="footnote ui-seccion__titulo">Anillo del día</h2>
                <div class="anillo-dia">
                    <div class="anillo-dia__grafico">
                        <svg viewBox="0 0 110 110">
                            <circle class="anillo-dia__pista" cx="55" cy="55" r="48" fill="none" stroke-width="10"/>
                            <circle class="anillo-dia__progreso" cx="55" cy="55" r="48" fill="none" stroke-width="10"
                                stroke-dasharray="301.6" stroke-dashoffset="90.5"/>
                        </svg>
                        <div class="anillo-dia__numero">
                            <strong class="tabular">3/5</strong>
                        </div>
                    </div>
                    <div class="anillo-dia__filas">
                        <div class="anillo-dia__fila"><span class="anillo-dia__punto" style="background:var(--red)"></span> Vencidos: 1</div>
                        <div class="anillo-dia__fila"><span class="anillo-dia__punto" style="background:var(--orange)"></span> Para hoy: 2</div>
                        <div class="anillo-dia__fila"><span class="anillo-dia__punto" style="background:var(--indigo)"></span> Esta semana: 4</div>
                    </div>
                </div>
            </section>

            <section class="ui-seccion">
                <h2 class="footnote ui-seccion__titulo">Lista inset grouped</h2>
                <div class="group">
                    <a class="cell cell--avatar-offset" href="#">
                        <span class="avatar" style="background:var(--avatar-1)">MG</span>
                        <span class="cell__content">
                            <span class="cell__title">Mariana Gómez</span>
                            <span class="cell__subtitle">Crédito personal — necesita refinanciar...</span>
                        </span>
                        <span class="cell__trailing tabular">14:30</span>
                        <span class="cell__chevron"><i data-lucide="chevron-right"></i></span>
                    </a>
                    <a class="cell cell--avatar-offset" href="#">
                        <span class="avatar" style="background:var(--avatar-5)">JP</span>
                        <span class="cell__content">
                            <span class="cell__title">Juan Pérez</span>
                            <span class="cell__subtitle">Consulta por tarjeta — sin respuesta aún</span>
                        </span>
                        <span class="cell__trailing tabular">09:00</span>
                        <span class="cell__chevron"><i data-lucide="chevron-right"></i></span>
                    </a>
                </div>
            </section>

            <section class="ui-seccion">
                <h2 class="footnote ui-seccion__titulo">Segmented control</h2>
                <div class="segmented" x-data="{ s: 'abiertos' }">
                    <button class="segmented__option" type="button" :aria-selected="s==='abiertos'" @click="s='abiertos'">Abiertos</button>
                    <button class="segmented__option" type="button" :aria-selected="s==='seguimiento'" @click="s='seguimiento'">En seguimiento</button>
                    <button class="segmented__option" type="button" :aria-selected="s==='cerrados'" @click="s='cerrados'">Cerrados</button>
                </div>
            </section>

            <section class="ui-seccion">
                <h2 class="footnote ui-seccion__titulo">Pills de fecha</h2>
                <div class="pills" x-data="{ p: 'manana' }">
                    <button class="pill" type="button" :aria-selected="p==='manana'" @click="p='manana'">Mañana</button>
                    <button class="pill" type="button" :aria-selected="p==='3dias'" @click="p='3dias'">En 3 días</button>
                    <button class="pill" type="button" :aria-selected="p==='1sem'" @click="p='1sem'">En 1 semana</button>
                    <button class="pill" type="button" :aria-selected="p==='elegir'" @click="p='elegir'">Elegir…</button>
                </div>
            </section>

            <section class="ui-seccion">
                <h2 class="footnote ui-seccion__titulo">Botones</h2>
                <div class="group" style="padding:16px; display:flex; flex-direction:column; gap:12px;">
                    <button class="btn-principal" type="button">Guardar</button>
                    <button class="btn-texto btn-texto--negrita" type="button">Guardar (texto)</button>
                    <button class="btn-texto btn-texto--peligro" type="button">Eliminar contacto</button>
                </div>

                <div class="action-buttons" style="margin-top:20px">
                    <div class="action-button">
                        <span class="action-button__icon"><i data-lucide="phone"></i></span>
                        <span class="action-button__label">Llamar</span>
                    </div>
                    <div class="action-button">
                        <span class="action-button__icon"><i data-lucide="message-circle"></i></span>
                        <span class="action-button__label">WhatsApp</span>
                    </div>
                    <div class="action-button">
                        <span class="action-button__icon"><i data-lucide="clock"></i></span>
                        <span class="action-button__label">Recontacto</span>
                    </div>
                    <div class="action-button">
                        <span class="action-button__icon"><i data-lucide="pencil"></i></span>
                        <span class="action-button__label">Nota</span>
                    </div>
                </div>
            </section>

            <section class="ui-seccion">
                <h2 class="footnote ui-seccion__titulo">Interruptor</h2>
                <div class="group">
                    <div class="cell" x-data="{ on: true }">
                        <span class="cell__content"><span class="cell__title">Notificaciones</span></span>
                        <button type="button" class="toggle" role="switch" :aria-checked="on ? 'true':'false'" @click="on = !on">
                            <span class="toggle__perilla"></span>
                        </button>
                    </div>
                </div>
            </section>

            <section class="ui-seccion">
                <h2 class="footnote ui-seccion__titulo">Campo de búsqueda</h2>
                <div class="search-field">
                    <i data-lucide="search"></i>
                    <input type="text" placeholder="Buscar">
                </div>
            </section>

            <section class="ui-seccion">
                <h2 class="footnote ui-seccion__titulo">Estado vacío</h2>
                <div class="empty-state">
                    <i data-lucide="calendar-check"></i>
                    <p class="body-text">No tenés recontactos para hoy</p>
                    <span class="empty-state__boton">Cargar consulta</span>
                </div>
            </section>

            <section class="ui-seccion" x-data="{ abierta: false }">
                <h2 class="footnote ui-seccion__titulo">Sheet</h2>
                <button class="btn-principal" type="button" @click="abierta = true">Abrir sheet de ejemplo</button>
                <div class="sheet-backdrop" x-show="abierta" @click.self="abierta = false" style="display:none">
                    <div class="sheet sheet--media">
                        <div class="sheet__grabber"></div>
                        <div class="sheet__header">
                            <button class="btn-texto" type="button" @click="abierta = false">Cancelar</button>
                            <span class="sheet__title">Registrar recontacto</span>
                            <button class="btn-texto btn-texto--negrita" type="button" @click="abierta = false">Guardar</button>
                        </div>
                        <div class="sheet__body">
                            <p class="body-text">Contenido de ejemplo del sheet.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="ui-seccion" x-data="{ mostrar: false }">
                <h2 class="footnote ui-seccion__titulo">HUD</h2>
                <button class="btn-principal" type="button" @click="mostrar = true; setTimeout(() => mostrar = false, 1500)">
                    Mostrar HUD
                </button>
                <div class="hud-capa" x-show="mostrar" style="display:none">
                    <div class="hud">
                        <i data-lucide="check-circle"></i>
                        <span>Recontacto guardado</span>
                    </div>
                </div>
            </section>

        </div>
    </div>

    <script src="assets/vendor/alpine.min.js" defer></script>
    <script src="assets/vendor/lucide/lucide.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
</body>
</html>
