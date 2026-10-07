<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';

iniciar_sesion();
requireLogin();

$usuarioId = usuario_actual_id();
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
    <title>Agenda DAQ</title>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
    <div class="app-shell" x-data="appShell()" x-init="init()">
        <div class="banner" x-show="sinConexion" x-cloak>Sin conexión. Mostrando los últimos datos guardados.</div>
        <header class="workspace-header">
            <button class="workspace-brand" type="button" @click="activo = 'hoy'" aria-label="Ir al inicio de Agenda DAQ"><span class="workspace-brand__mark"><i data-lucide="calendar-check-2"></i></span><span>Agenda DAQ</span></button>
            <nav class="workspace-header__nav" aria-label="Secciones de trabajo"><button type="button" :class="{'is-active': activo === 'hoy'}" @click="activo = 'hoy'">Resumen</button><button type="button" :class="{'is-active': activo === 'contactos'}" @click="activo = 'contactos'">Contactos</button><button type="button" :class="{'is-active': activo === 'agenda'}" @click="activo = 'agenda'">Calendario</button></nav>
            <div class="workspace-header__actions"><button class="workspace-search" type="button" @click="activo = 'contactos'; $nextTick(() => $el.closest('.app-shell').querySelector('.search-field input')?.focus())" aria-label="Buscar contactos"><i data-lucide="search"></i><span>Buscar</span></button><button class="workspace-create" type="button" @click="abrirNuevoContacto()"><i data-lucide="plus"></i><span>Nueva consulta</span></button></div>
        </header>
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
                        <button class="navbar__button navbar__button--large" type="button" aria-label="Cargar consulta" @click="abrirNuevoContacto()"><i data-lucide="plus"></i></button>
                    </div>
                </header>
                <div class="screen__body">
                    <p class="subhead" x-text="fechaHoy"></p>
                    <p class="error-inline" x-show="errorPanel" x-text="errorPanel"></p>
                    <div class="anillo-dia" x-show="!errorPanel">
                        <div class="anillo-dia__grafico" aria-label="Progreso de recontactos de hoy">
                            <svg viewBox="0 0 110 110" aria-hidden="true"><circle class="anillo-dia__pista" cx="55" cy="55" r="46" fill="none" stroke-width="10"></circle><circle class="anillo-dia__progreso" cx="55" cy="55" r="46" fill="none" stroke-width="10" stroke-dasharray="289.03" :stroke-dashoffset="offsetAnillo()"></circle></svg>
                            <span class="anillo-dia__numero"><strong class="tabular" x-text="`${panel.anillo?.hechos_hoy || 0} / ${panel.anillo?.agendados_hoy || 0}`"></strong><span class="caption">hechos</span></span>
                        </div>
                        <div class="anillo-dia__filas"><div class="anillo-dia__fila"><span class="anillo-dia__punto" style="background:var(--red)"></span><span>Vencidos</span><strong class="tabular" x-text="panel.vencidos?.length || 0"></strong></div><div class="anillo-dia__fila"><span class="anillo-dia__punto" style="background:var(--orange)"></span><span>Para hoy</span><strong class="tabular" x-text="panel.hoy?.length || 0"></strong></div><div class="anillo-dia__fila"><span class="anillo-dia__punto" style="background:var(--indigo)"></span><span>Esta semana</span><strong class="tabular" x-text="Object.values(panel.proximos || {}).flat().length"></strong></div></div>
                    </div>

                    <template x-if="panel.vencidos?.length"><section class="panel-group"><p class="group__header footnote panel-group__header panel-group__header--danger">vencidos</p><div class="group"><template x-for="contacto in panel.vencidos" :key="`v-${contacto.id}`"><div class="contact-row"><button class="cell cell--avatar-offset" type="button" @click="abrirFicha(contacto.id)"><span class="avatar" :style="`background:${colorAvatar(contacto.nombre)}`" x-text="iniciales(contacto.nombre)"></span><span class="cell__content"><span class="cell__title" x-text="contacto.nombre"></span><span class="cell__subtitle" x-text="[contacto.producto_interes, contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"></span></span><span class="cell__trailing panel-date panel-date--danger" x-text="fechaCorta(contacto.proximo_contacto)"></span></button><div class="contact-row__actions"><button type="button" class="quick-action quick-action--orange" @click="posponerContacto(contacto, 'manana')" title="Posponer a mañana"><i data-lucide="clock-3"></i></button><button type="button" class="quick-action quick-action--tint" @click="abrirSeguimiento(contacto)" title="Registrar recontacto"><i data-lucide="phone-call"></i></button></div></div></template></div></section></template>
                    <template x-if="panel.hoy?.length"><section class="panel-group"><p class="group__header footnote">para hoy</p><div class="group"><template x-for="contacto in panel.hoy" :key="`h-${contacto.id}`"><div class="contact-row"><button class="cell cell--avatar-offset" type="button" @click="abrirFicha(contacto.id)"><span class="avatar" :style="`background:${colorAvatar(contacto.nombre)}`" x-text="iniciales(contacto.nombre)"></span><span class="cell__content"><span class="cell__title" x-text="contacto.nombre"></span><span class="cell__subtitle" x-text="[contacto.producto_interes, contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"></span></span><span class="cell__trailing panel-date" x-text="fechaCorta(contacto.proximo_contacto)"></span></button><div class="contact-row__actions"><button type="button" class="quick-action quick-action--orange" @click="posponerContacto(contacto, 'hora')" title="Posponer una hora"><i data-lucide="clock-3"></i></button><button type="button" class="quick-action quick-action--tint" @click="abrirSeguimiento(contacto)" title="Registrar recontacto"><i data-lucide="phone-call"></i></button></div></div></template></div></section></template>
                    <template x-for="[dia, contactos] in proximosOrdenados()" :key="dia"><section class="panel-group"><p class="group__header footnote" x-text="tituloDia(dia)"></p><div class="group"><template x-for="contacto in contactos" :key="`p-${contacto.id}`"><button class="cell cell--avatar-offset" type="button" @click="abrirFicha(contacto.id)"><span class="avatar" :style="`background:${colorAvatar(contacto.nombre)}`" x-text="iniciales(contacto.nombre)"></span><span class="cell__content"><span class="cell__title" x-text="contacto.nombre"></span><span class="cell__subtitle" x-text="[contacto.producto_interes, contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"></span></span><span class="cell__trailing" x-text="fechaCorta(contacto.proximo_contacto)"></span></button></template></div></section></template>
                    <template x-if="panel.sin_fecha?.length"><section class="panel-group"><p class="group__header footnote">sin fecha</p><div class="group"><template x-for="contacto in panel.sin_fecha" :key="`s-${contacto.id}`"><button class="cell cell--avatar-offset" type="button" @click="abrirFicha(contacto.id)"><span class="avatar" :style="`background:${colorAvatar(contacto.nombre)}`" x-text="iniciales(contacto.nombre)"></span><span class="cell__content"><span class="cell__title" x-text="contacto.nombre"></span><span class="cell__subtitle" x-text="[contacto.producto_interes, contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"></span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button></template></div></section></template>
                    <div class="empty-state" x-show="!cargandoPanel && !panel.vencidos?.length && !panel.hoy?.length && !Object.keys(panel.proximos || {}).length && !panel.sin_fecha?.length"><i data-lucide="calendar-check"></i><p class="body-text">No tenés recontactos para hoy</p><button class="empty-state__boton" type="button" @click="abrirNuevoContacto()">Cargar consulta</button></div>
                    <div class="month-counts" x-show="panel.contadores?.abiertos"><span><strong class="tabular" x-text="panel.contadores?.abiertos || 0"></strong> abiertos</span><span><strong class="tabular" x-text="panel.contadores?.cerrados_mes?.concreto || 0"></strong> concretados este mes</span></div>
                </div>
            </section>

            <section class="screen" x-show="activo === 'contactos'">
                <header class="navbar">
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Contactos</h1>
                        <button class="navbar__button navbar__button--large" type="button" aria-label="Nuevo contacto" @click="abrirNuevoContacto()">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                </header>
                <div class="screen__body">
                    <label class="search-field">
                        <i data-lucide="search"></i>
                        <input type="search" x-model="busqueda" @input.debounce.300ms="aplicarFiltros()" placeholder="Buscar contactos" aria-label="Buscar contactos">
                    </label>

                    <div class="segmented" role="tablist" aria-label="Estado de contactos">
                        <template x-for="estado in [{id: 'abiertos', texto: 'Abiertos'}, {id: 'seguimiento', texto: 'En seguimiento'}, {id: 'cerrada', texto: 'Cerrados'}]" :key="estado.id">
                            <button class="segmented__option" type="button" role="tab" :aria-selected="filtroEstado === estado.id" @click="filtroEstado = estado.id; aplicarFiltros()" x-text="estado.texto"></button>
                        </template>
                    </div>

                    <div class="filter-row" aria-label="Filtros adicionales">
                        <select class="filter-chip" x-model="filtroOrigen" @change="aplicarFiltros()" aria-label="Filtrar por origen">
                            <option value="">Origen</option><option value="whatsapp">WhatsApp</option><option value="instagram">Instagram</option><option value="facebook">Facebook</option><option value="llamada">Llamada</option><option value="local">Local</option><option value="referido">Referido</option><option value="otro">Otro</option>
                        </select>
                        <select class="filter-chip" x-model="filtroProvincia" @change="aplicarFiltros()" aria-label="Filtrar por provincia">
                            <option value="">Provincia</option><option>Tucumán</option><option>Santiago del Estero</option><option>Catamarca</option><option>Otra</option>
                        </select>
                        <select class="filter-chip" x-model="filtroEtiqueta" @change="aplicarFiltros()" aria-label="Filtrar por etiqueta">
                            <option value="">Etiqueta</option><template x-for="etiqueta in etiquetas" :key="etiqueta.id"><option :value="etiqueta.id" x-text="etiqueta.nombre"></option></template>
                        </select>
                        <button class="filter-chip filter-chip--clear" type="button" x-show="busqueda || filtroOrigen || filtroProvincia || filtroEtiqueta || filtroEstado !== 'abiertos'" @click="limpiarFiltros()">Limpiar</button>
                    </div>

                    <p class="error-inline" x-show="errorContactos" x-text="errorContactos"></p>
                    <template x-if="contactos.length">
                        <div class="group contact-list">
                            <template x-for="contacto in contactos" :key="contacto.id">
                                <button class="cell cell--avatar-offset" type="button" @click="abrirFicha(contacto.id)">
                                    <span class="avatar" :style="`background:${colorAvatar(contacto.nombre)}`" x-text="iniciales(contacto.nombre)"></span>
                                    <span class="cell__content">
                                        <span class="cell__title" x-text="contacto.nombre"></span>
                                        <span class="cell__subtitle" x-text="[contacto.producto_interes, contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle' "></span>
                                    </span>
                                    <span class="cell__trailing"><span class="tabular" x-text="fechaCorta(contacto.proximo_contacto)"></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></span>
                                </button>
                            </template>
                        </div>
                    </template>
                    <div class="empty-state" x-show="!cargandoContactos && !contactos.length && !errorContactos">
                        <i data-lucide="users"></i>
                        <p class="body-text" x-text="busqueda || filtroOrigen || filtroProvincia || filtroEtiqueta ? 'No encontramos contactos con esos filtros' : 'Todavía no cargaste ningún contacto'"></p>
                        <button class="empty-state__boton" type="button" @click="abrirNuevoContacto()" x-show="!busqueda && !filtroOrigen && !filtroProvincia && !filtroEtiqueta">Cargar consulta</button>
                    </div>
                    <button class="btn-texto contacts-load-more" type="button" x-show="hayMasContactos" @click="cargarContactos()" :disabled="cargandoContactos" x-text="cargandoContactos ? 'Cargando…' : 'Cargar más contactos'"></button>
                </div>
            </section>

            <section class="screen" x-show="activo === 'agenda'">
                <header class="navbar">
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Agenda</h1>
                    </div>
                </header>
                <div class="screen__body">
                    <div class="week-strip"><button type="button" class="week-strip__nav" @click="moverSemana(-1)" aria-label="Semana anterior"><i data-lucide="chevron-left"></i></button><div class="week-strip__days"><template x-for="dia in diasSemana()" :key="dia.iso"><button type="button" class="week-strip__day" :class="{'week-strip__day--selected': diaSeleccionado === dia.iso, 'week-strip__day--today': dia.esHoy}" @click="seleccionarDia(dia.iso)"><span class="caption" x-text="dia.letra"></span><strong x-text="dia.numero"></strong><span class="week-strip__dot" x-show="agenda.puntos?.[dia.iso]" :class="{'week-strip__dot--selected': diaSeleccionado === dia.iso}"></span></button></template></div><button type="button" class="week-strip__nav" @click="moverSemana(1)" aria-label="Semana siguiente"><i data-lucide="chevron-right"></i></button></div>
                    <button type="button" class="btn-texto agenda-today" @click="volverHoy()">Hoy</button>
                    <p class="group__header footnote" x-text="tituloDiaSeleccionado()"></p>
                    <div class="group" x-show="contactosDiaSeleccionado().length"><template x-for="contacto in contactosDiaSeleccionado()" :key="`a-${contacto.id}`"><div class="contact-row"><button class="cell cell--avatar-offset" type="button" @click="abrirFicha(contacto.id)"><span class="avatar" :style="`background:${colorAvatar(contacto.nombre)}`" x-text="iniciales(contacto.nombre)"></span><span class="cell__content"><span class="cell__title" x-text="contacto.nombre"></span><span class="cell__subtitle" x-text="[contacto.producto_interes, contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"></span></span><span class="cell__trailing tabular" x-text="fechaCorta(contacto.proximo_contacto)"></span></button><button type="button" class="quick-action quick-action--tint" @click="abrirSeguimiento(contacto)" aria-label="Registrar recontacto"><i data-lucide="phone-call"></i></button></div></template></div>
                    <div class="empty-state agenda-empty" x-show="!cargandoAgenda && !contactosDiaSeleccionado().length"><i data-lucide="calendar-days"></i><p class="body-text">Sin recontactos agendados</p></div>
                </div>
            </section>

            <section class="screen" x-show="activo === 'ajustes'">
                <header class="navbar">
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Ajustes</h1>
                    </div>
                </header>
                <div class="screen__body">
                    <p class="group__header footnote">aplicación</p>
                    <div class="group">
                        <button class="cell" type="button" x-show="eventoInstalacion && !instalada" @click="instalarApp()"><span class="cell__icon-box" style="background:var(--tint)"><i data-lucide="download"></i></span><span class="cell__content"><span class="cell__title">Instalar app</span><span class="cell__subtitle">Abrir como una app independiente</span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                        <div class="cell" x-show="esIos && !instalada"><span class="cell__icon-box" style="background:var(--indigo)"><i data-lucide="share"></i></span><span class="cell__content"><span class="cell__title">Instalar en iPhone o iPad</span><span class="cell__subtitle">Compartir → Agregar a inicio</span></span></div>
                        <div class="cell" x-show="instalada"><span class="cell__icon-box" style="background:var(--green)"><i data-lucide="check"></i></span><span class="cell__content"><span class="cell__title">App instalada</span><span class="cell__subtitle">Se abre a pantalla completa</span></span></div>
                        <button class="cell" type="button" @click="sheetPush = true; refrescarIconos()"><span class="cell__icon-box" style="background:var(--orange)"><i data-lucide="bell"></i></span><span class="cell__content"><span class="cell__title">Notificaciones</span><span class="cell__subtitle" x-text="push.dispositivos?.length ? `${push.dispositivos.length} dispositivo(s) activo(s)` : 'Configurar recordatorios'"></span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                    </div>
                    <p class="group__header footnote">organización</p>
                    <div class="group">
                        <button class="cell" type="button" @click="abrirEtiquetas()"><span class="cell__icon-box" style="background:var(--orange)"><i data-lucide="tags"></i></span><span class="cell__content"><span class="cell__title">Etiquetas</span><span class="cell__subtitle" x-text="etiquetas.length ? `${etiquetas.length} creadas` : 'Organizá tus contactos'"></span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                    </div>
                    <p class="group__header footnote">seguridad</p>
                    <div class="group">
                        <button class="cell" type="button" @click="sheetPassword = true; refrescarIconos()"><span class="cell__icon-box" style="background:var(--indigo)"><i data-lucide="key-round"></i></span><span class="cell__content"><span class="cell__title">Cambiar contraseña</span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                        <button class="cell" type="button" @click="sheetSesiones = true; refrescarIconos()"><span class="cell__icon-box" style="background:var(--gray)"><i data-lucide="monitor-off"></i></span><span class="cell__content"><span class="cell__title">Cerrar sesión en todos los dispositivos</span><span class="cell__subtitle">Revoca sesiones guardadas</span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                    </div>
                    <p class="group__header footnote">cuenta</p>
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

        <div class="sheet-backdrop" x-show="sheetContacto" x-transition.opacity @keydown.escape.window="sheetContacto = false" @click.self="sheetContacto = false" x-cloak>
            <form class="sheet" @submit.prevent="guardarContacto()">
                <div class="sheet__grabber"></div>
                <header class="sheet__header"><button class="btn-texto" type="button" @click="sheetContacto = false">Cancelar</button><h2 class="sheet__title" x-text="modoFormulario === 'nuevo' ? 'Nuevo contacto' : 'Editar contacto'"></h2><button class="btn-texto btn-texto--negrita" type="submit" :disabled="guardandoContacto" x-text="guardandoContacto ? 'Guardando…' : 'Guardar'"></button></header>
                <div class="sheet__body">
                    <p class="group__header footnote">datos principales</p>
                    <div class="group form-group">
                        <label class="cell form-cell"><span class="cell__label">Nombre</span><input x-model.trim="formularioContacto.nombre" required maxlength="120" autocomplete="name" placeholder="Nombre y apellido"></label>
                        <label class="cell form-cell"><span class="cell__label">Celular</span><input x-model.trim="formularioContacto.celular" required inputmode="tel" autocomplete="tel" maxlength="30" placeholder="381 456 7890"></label>
                    </div>
                    <template x-if="modoFormulario === 'nuevo'"><div><p class="group__header footnote">consulta</p><div class="group form-group"><label class="cell form-cell form-cell--stack"><span class="cell__label">Consulta</span><textarea x-model.trim="formularioContacto.consulta" required maxlength="4000" rows="3" placeholder="¿Qué está buscando?"></textarea></label></div></div></template>
                    <template x-if="modoFormulario === 'nuevo'"><div><p class="group__header footnote">recontactar</p><div class="pills date-pills"><button class="pill" type="button" :aria-selected="Boolean(formularioContacto.proximo_contacto)" @click="asignarFechaRapida(1)">Mañana</button><button class="pill" type="button" @click="asignarFechaRapida(3)">En 3 días</button><button class="pill" type="button" @click="asignarFechaRapida(7)">En 1 semana</button><label class="pill pill--input">Elegir… <input type="datetime-local" x-model="formularioContacto.proximo_contacto" aria-label="Elegir fecha de recontacto"></label></div><p class="group__footer footnote" x-show="formularioContacto.proximo_contacto" x-text="fechaLarga(formularioContacto.proximo_contacto)"></p></div></template>
                    <button class="disclosure" type="button" @click="formularioContacto.masDatos = !formularioContacto.masDatos"><span>Más datos</span><i :data-lucide="formularioContacto.masDatos ? 'chevron-up' : 'chevron-down'"></i></button>
                    <div x-show="formularioContacto.masDatos" x-transition.opacity>
                        <div class="group form-group"><label class="cell form-cell"><span class="cell__label">Producto</span><input x-model.trim="formularioContacto.producto_interes" maxlength="120" placeholder="Opcional"></label><label class="cell form-cell"><span class="cell__label">Origen</span><select x-model="formularioContacto.origen"><option value="otro">Otro</option><option value="whatsapp">WhatsApp</option><option value="instagram">Instagram</option><option value="facebook">Facebook</option><option value="llamada">Llamada</option><option value="local">Local</option><option value="referido">Referido</option></select></label><label class="cell form-cell"><span class="cell__label">Localidad</span><input x-model.trim="formularioContacto.localidad" maxlength="100" placeholder="Opcional"></label><label class="cell form-cell"><span class="cell__label">Provincia</span><select x-model="formularioContacto.provincia"><option value="">Sin especificar</option><option>Tucumán</option><option>Santiago del Estero</option><option>Catamarca</option><option>Otra</option></select></label></div>
                        <p class="group__header footnote">etiquetas</p><div class="tag-picker"><template x-for="etiqueta in etiquetas" :key="etiqueta.id"><button type="button" class="tag" :class="{'tag--selected': etiquetaSeleccionada(etiqueta.id)}" @click="alternarEtiqueta(etiqueta.id)"><span class="tag__dot" :style="`background:${etiqueta.color}`"></span><span x-text="etiqueta.nombre"></span></button></template></div>
                        <div class="tag-create"><input x-model="etiquetaNueva" @keydown.enter.prevent="crearEtiqueta()" maxlength="50" placeholder="Nueva etiqueta"><button type="button" class="btn-texto" @click="crearEtiqueta()">Agregar</button></div>
                    </div>
                </div>
            </form>
        </div>

        <div class="sheet-backdrop" x-show="sheetSeguimiento" x-transition.opacity @keydown.escape.window="sheetSeguimiento = false" @click.self="sheetSeguimiento = false" x-cloak>
            <form class="sheet sheet--media" @submit.prevent="guardarSeguimiento()">
                <div class="sheet__grabber"></div>
                <header class="sheet__header"><button class="btn-texto" type="button" @click="sheetSeguimiento = false">Cancelar</button><h2 class="sheet__title" x-text="seguimientoModo === 'nota' ? 'Nueva nota' : (seguimientoModo === 'reabrir' ? 'Reabrir contacto' : 'Registrar recontacto')"></h2><button class="btn-texto btn-texto--negrita" type="submit" :disabled="guardandoSeguimiento" x-text="guardandoSeguimiento ? 'Guardando…' : 'Guardar'"></button></header>
                <div class="sheet__body">
                    <p class="sheet-contact-name" x-text="seguimientoContacto?.nombre"></p>
                    <template x-if="seguimientoModo === 'recontacto'"><div><div class="segmented" role="tablist" aria-label="Resultado del recontacto"><template x-for="opcion in [{id: 'atendio', texto: 'Atendió'}, {id: 'no_atendio', texto: 'No atendió'}, {id: 'mensaje_enviado', texto: 'Mensaje'}]" :key="opcion.id"><button type="button" class="segmented__option" :aria-selected="formularioSeguimiento.resultado === opcion.id" @click="formularioSeguimiento.resultado = opcion.id" x-text="opcion.texto"></button></template></div><label class="note-field"><span class="footnote">nota</span><textarea x-model.trim="formularioSeguimiento.nota" maxlength="4000" rows="2" placeholder="Agregá un detalle opcional"></textarea></label><div class="segmented" role="tablist" aria-label="Próxima acción"><button type="button" class="segmented__option" :aria-selected="formularioSeguimiento.accion === 'reagendar'" @click="formularioSeguimiento.accion = 'reagendar'">Reagendar</button><button type="button" class="segmented__option" :aria-selected="formularioSeguimiento.accion === 'cerrar'" @click="formularioSeguimiento.accion = 'cerrar'">Cerrar</button></div><div x-show="formularioSeguimiento.accion === 'reagendar'"><div class="pills date-pills"><button class="pill" type="button" @click="asignarFechaSeguimiento(1)">Mañana</button><button class="pill" type="button" @click="asignarFechaSeguimiento(3)">En 3 días</button><button class="pill" type="button" @click="asignarFechaSeguimiento(7)">En 1 semana</button><label class="pill pill--input">Elegir… <input type="datetime-local" x-model="formularioSeguimiento.proximo_contacto" aria-label="Próxima fecha"></label></div><p class="group__footer footnote" x-show="formularioSeguimiento.proximo_contacto" x-text="fechaLarga(formularioSeguimiento.proximo_contacto)"></p></div><div x-show="formularioSeguimiento.accion === 'cerrar'" class="close-reasons"><p class="footnote">motivo de cierre</p><div class="pills"><template x-for="opcion in [{id: 'concreto', texto: 'Concretó'}, {id: 'no_interesa', texto: 'No interesa'}, {id: 'sin_respuesta', texto: 'Sin respuesta'}]" :key="opcion.id"><button type="button" class="pill" :aria-selected="formularioSeguimiento.motivo_cierre === opcion.id" @click="formularioSeguimiento.motivo_cierre = opcion.id" x-text="opcion.texto"></button></template></div></div></div></template>
                    <template x-if="seguimientoModo === 'nota'"><label class="note-field"><span class="footnote">nota</span><textarea x-model.trim="formularioSeguimiento.nota" required maxlength="4000" rows="4" placeholder="Escribí una nota"></textarea></label></template>
                    <template x-if="seguimientoModo === 'reabrir'"><div><label class="note-field"><span class="footnote">nota (opcional)</span><textarea x-model.trim="formularioSeguimiento.nota" maxlength="4000" rows="2" placeholder="Motivo para reabrir"></textarea></label><p class="footnote">nueva fecha</p><div class="pills date-pills"><button class="pill" type="button" @click="asignarFechaSeguimiento(1)">Mañana</button><button class="pill" type="button" @click="asignarFechaSeguimiento(3)">En 3 días</button><button class="pill" type="button" @click="asignarFechaSeguimiento(7)">En 1 semana</button><label class="pill pill--input">Elegir… <input type="datetime-local" x-model="formularioSeguimiento.proximo_contacto" aria-label="Fecha para reabrir"></label></div></div></template>
                </div>
            </form>
        </div>

        <div class="sheet-backdrop" x-show="sheetDuplicado" x-transition.opacity @click.self="sheetDuplicado = false" x-cloak>
            <section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true" aria-labelledby="duplicado-titulo"><div class="sheet__grabber"></div><div class="sheet__body"><i class="alert-sheet__icon" data-lucide="copy"></i><h2 id="duplicado-titulo" class="title2">Este número ya está cargado</h2><p class="subhead">Se lo agregará como una nueva consulta a <strong x-text="duplicadoPendiente?.nombre"></strong>.</p><div class="alert-sheet__actions"><button type="button" class="btn-principal" @click="guardarContacto(true)">Agregar consulta</button><button type="button" class="btn-texto" @click="sheetDuplicado = false">Cancelar</button></div></div></section>
        </div>

        <div class="sheet-backdrop" x-show="sheetPush" x-transition.opacity @click.self="sheetPush = false" x-cloak><section class="sheet" aria-labelledby="push-titulo"><div class="sheet__grabber"></div><header class="sheet__header"><button class="btn-texto" type="button" @click="sheetPush = false">Cerrar</button><h2 id="push-titulo" class="sheet__title">Notificaciones</h2><span></span></header><div class="sheet__body"><div class="group"><button class="cell" type="button" @click="activarPush()" :disabled="activandoPush"><span class="cell__content"><span class="cell__title">Activar en este dispositivo</span><span class="cell__subtitle" x-text="activandoPush ? 'Solicitando permiso…' : (esIos && !instalada ? 'Primero agregá la app a Inicio' : 'Recibí recordatorios push')"></span></span><span class="toggle" role="switch" :aria-checked="push.dispositivos?.length ? 'true' : 'false'"><span class="toggle__perilla"></span></span></button></div><p class="group__header footnote">dispositivos activos</p><div class="group"><template x-for="dispositivo in push.dispositivos" :key="dispositivo.id"><div class="cell"><span class="cell__icon-box" style="background:var(--tint)"><i data-lucide="smartphone"></i></span><span class="cell__content"><span class="cell__title" x-text="dispositivo.dispositivo || 'Dispositivo' "></span><span class="cell__subtitle" x-text="dispositivo.ultimo_uso || dispositivo.creado_en"></span></span><button class="btn-texto btn-texto--peligro" type="button" @click="quitarPush(dispositivo)">Quitar</button></div></template><p class="empty-inline" x-show="!push.dispositivos?.length">Todavía no hay dispositivos activos.</p></div><button class="btn-principal" type="button" @click="enviarPruebaPush()" :disabled="!push.dispositivos?.length">Enviar prueba</button><p class="group__header footnote settings-list-header">resumen diario</p><div class="group"><label class="cell form-cell"><span class="cell__label">Hora</span><input type="time" x-model="push.hora_resumen" @change="guardarHoraResumen()" aria-label="Hora del resumen diario"></label></div><p class="group__footer footnote">En iPhone, las notificaciones requieren que Agenda esté instalada desde Compartir → Agregar a inicio.</p></div></section></div>

        <div class="sheet-backdrop" x-show="sheetEtiquetas" x-transition.opacity @click.self="sheetEtiquetas = false" x-cloak><section class="sheet" aria-labelledby="etiquetas-titulo"><div class="sheet__grabber"></div><header class="sheet__header"><button class="btn-texto" type="button" @click="sheetEtiquetas = false">Cerrar</button><h2 id="etiquetas-titulo" class="sheet__title">Etiquetas</h2><span></span></header><div class="sheet__body"><form @submit.prevent="guardarEtiqueta()"><div class="group form-group"><label class="cell form-cell"><span class="cell__label">Nombre</span><input x-model.trim="formularioEtiqueta.nombre" maxlength="50" required placeholder="Nueva etiqueta"></label><label class="cell form-cell"><span class="cell__label">Color</span><input class="color-input" type="color" x-model="formularioEtiqueta.color" aria-label="Color de etiqueta"></label></div><button class="btn-principal" type="submit" :disabled="guardandoAjuste" x-text="editandoEtiqueta ? 'Guardar cambios' : 'Crear etiqueta'"></button><button class="btn-texto settings-cancel-edit" type="button" x-show="editandoEtiqueta" @click="cancelarEtiqueta()">Cancelar edición</button></form><p class="group__header footnote settings-list-header">tus etiquetas</p><div class="group"><template x-for="etiqueta in etiquetas" :key="etiqueta.id"><div class="cell"><span class="tag__dot" :style="`background:${etiqueta.color}`"></span><span class="cell__content"><span class="cell__title" x-text="etiqueta.nombre"></span></span><button class="btn-texto" type="button" @click="editarEtiqueta(etiqueta)">Editar</button><button class="btn-texto btn-texto--peligro" type="button" @click="eliminarEtiqueta(etiqueta)" aria-label="Eliminar etiqueta"><i data-lucide="trash-2"></i></button></div></template><p class="empty-inline" x-show="!etiquetas.length">Todavía no creaste etiquetas.</p></div></div></section></div>

        <div class="sheet-backdrop" x-show="sheetPassword" x-transition.opacity @click.self="sheetPassword = false" x-cloak><form class="sheet sheet--media" @submit.prevent="cambiarPassword()"><div class="sheet__grabber"></div><header class="sheet__header"><button class="btn-texto" type="button" @click="sheetPassword = false">Cancelar</button><h2 class="sheet__title">Cambiar contraseña</h2><button class="btn-texto btn-texto--negrita" type="submit" :disabled="guardandoAjuste">Guardar</button></header><div class="sheet__body"><div class="group form-group"><label class="cell form-cell"><span class="cell__label">Actual</span><input type="password" x-model="formularioPassword.actual" autocomplete="current-password" required></label><label class="cell form-cell"><span class="cell__label">Nueva</span><input type="password" x-model="formularioPassword.nueva" autocomplete="new-password" minlength="10" required></label><label class="cell form-cell"><span class="cell__label">Repetir</span><input type="password" x-model="formularioPassword.confirmacion" autocomplete="new-password" minlength="10" required></label></div><p class="group__footer footnote">Usá al menos 10 caracteres. Al cambiarla se cerrarán las sesiones guardadas.</p></div></form></div>

        <div class="sheet-backdrop" x-show="sheetSesiones" x-transition.opacity @click.self="sheetSesiones = false" x-cloak><section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true"><div class="sheet__grabber"></div><div class="sheet__body"><i class="alert-sheet__icon" data-lucide="monitor-off"></i><h2 class="title2">¿Cerrar sesiones guardadas?</h2><p class="subhead">Los otros dispositivos tendrán que iniciar sesión nuevamente. Este dispositivo seguirá abierto.</p><div class="alert-sheet__actions"><button class="btn-principal" type="button" :disabled="guardandoAjuste" @click="cerrarSesiones()">Cerrar sesiones</button><button class="btn-texto" type="button" @click="sheetSesiones = false">Cancelar</button></div></div></section></div>

        <div class="sheet-backdrop detail-backdrop" x-show="sheetFicha" x-transition.opacity @keydown.escape.window="sheetFicha = false" @click.self="sheetFicha = false" x-cloak>
            <article class="sheet contact-detail" x-show="contactoActual"><div class="sheet__grabber"></div><header class="sheet__header"><button class="btn-texto" type="button" @click="sheetFicha = false">Cerrar</button><span class="sheet__title">Contacto</span><button class="btn-texto btn-texto--negrita" type="button" @click="editarContacto()">Editar</button></header><div class="sheet__body"><div class="contact-detail__identity"><span class="avatar avatar--grande" :style="`background:${colorAvatar(contactoActual?.nombre)}`" x-text="iniciales(contactoActual?.nombre)"></span><h2 class="title2" x-text="contactoActual?.nombre"></h2><p class="subhead" x-text="contactoActual?.producto_interes || 'Consulta' "></p></div><div class="action-buttons"><a class="action-button" :href="`tel:+${contactoActual?.celular_norm}`"><span class="action-button__icon"><i data-lucide="phone"></i></span><span class="action-button__label">Llamar</span></a><a class="action-button" target="_blank" rel="noopener" :href="`https://wa.me/${contactoActual?.celular_norm}`"><span class="action-button__icon"><i data-lucide="message-circle"></i></span><span class="action-button__label">WhatsApp</span></a><button class="action-button" type="button" @click="mostrarHud('Disponible en la Fase 3', 'clock-3')"><span class="action-button__icon"><i data-lucide="phone-call"></i></span><span class="action-button__label">Recontacto</span></button><button class="action-button" type="button" @click="mostrarHud('Disponible en la Fase 3', 'sticky-note')"><span class="action-button__icon"><i data-lucide="sticky-note"></i></span><span class="action-button__label">Nota</span></button></div><p class="group__header footnote">datos</p><div class="group"><div class="cell"><span class="cell__label">Celular</span><a class="cell__value" :href="`tel:+${contactoActual?.celular_norm}`" x-text="contactoActual?.celular"></a></div><div class="cell"><span class="cell__label">Origen</span><span class="cell__value" x-text="etiquetaOrigen(contactoActual?.origen)"></span></div><div class="cell" x-show="contactoActual?.localidad || contactoActual?.provincia"><span class="cell__label">Ubicación</span><span class="cell__value" x-text="[contactoActual?.localidad, contactoActual?.provincia].filter(Boolean).join(', ')"></span></div><div class="cell" x-show="contactoActual?.etiquetas?.length"><span class="cell__label">Etiquetas</span><span class="contact-tags"><template x-for="etiqueta in contactoActual?.etiquetas" :key="etiqueta.id"><span class="tag tag--readonly"><span class="tag__dot" :style="`background:${etiqueta.color}`"></span><span x-text="etiqueta.nombre"></span></span></template></span></div></div><p class="group__header footnote">estado</p><div class="group"><div class="cell"><span class="cell__label">Estado</span><span class="cell__value" x-text="contactoActual?.estado === 'cerrada' ? 'Cerrada' : (contactoActual?.estado === 'seguimiento' ? 'En seguimiento' : 'Pendiente')"></span></div><div class="cell"><span class="cell__label">Próximo contacto</span><span class="cell__value tabular" x-text="fechaLarga(contactoActual?.proximo_contacto)"></span></div></div><p class="group__header footnote">historial</p><div class="group timeline"><template x-for="registro in contactoActual?.historial" :key="registro.id"><div class="cell timeline__item"><span class="timeline__icon"><i :data-lucide="registro.tipo === 'consulta' ? 'message-square' : 'clock-3'"></i></span><span class="cell__content"><span class="cell__title" x-text="registro.tipo === 'consulta' ? 'Consulta' : registro.tipo"></span><span class="cell__subtitle" x-text="registro.nota || 'Sin nota'"></span></span><time class="cell__trailing tabular" x-text="fechaCorta(registro.fecha)"></time></div></template></div><button class="btn-texto btn-texto--peligro delete-contact" type="button" @click="pedirEliminar()">Eliminar contacto</button></div></article>
        </div>

        <div class="sheet-backdrop" x-show="sheetEliminar" x-transition.opacity @click.self="sheetEliminar = false" x-cloak><section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true"><div class="sheet__grabber"></div><div class="sheet__body"><i class="alert-sheet__icon alert-sheet__icon--danger" data-lucide="trash-2"></i><h2 class="title2">¿Eliminar contacto?</h2><p class="subhead">Se eliminarán también sus consultas y su historial. Esta acción no se puede deshacer.</p><div class="alert-sheet__actions"><button class="btn-principal btn-principal--danger" type="button" @click="eliminarContacto()">Eliminar contacto</button><button class="btn-texto" type="button" @click="sheetEliminar = false">Cancelar</button></div></div></section></div>

        <div class="hud-capa" x-show="hudVisible" x-transition.opacity x-cloak><div class="hud"><i :data-lucide="hudIcono"></i><span x-text="hudTexto"></span></div></div>
    </div>

    <script>window.APP_CONFIG = { csrf: <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> };</script>
    <script src="assets/vendor/alpine.min.js" defer></script>
    <script src="assets/vendor/lucide/lucide.min.js"></script>
    <script src="assets/js/contactos.js"></script>
    <script src="assets/js/seguimientos.js"></script>
    <script src="assets/js/panel.js"></script>
    <script src="assets/js/ajustes.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/pwa.js"></script>
</body>
</html>
