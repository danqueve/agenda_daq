<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';

iniciar_sesion();
requireLogin();

$usuarioId = usuario_actual_id();

$stmtUsuario = Db::get()->prepare('SELECT usuario FROM usuarios WHERE id = :id');
$stmtUsuario->execute(['id' => $usuarioId]);
$usuarioNombre = $stmtUsuario->fetchColumn() ?: 'Usuario';
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
    <title>Agenda DAQ</title>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/css/tokens.css">
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/pastel.css">
</head>
<body>
    <div class="app-shell" x-data="appShell()" x-init="init()" :class="{'modo-contactos': activo === 'contactos'}">
        <div class="banner" x-show="sinConexion" x-cloak>Sin conexión. Mostrando los últimos datos guardados.</div>
        <nav class="tabbar" aria-label="Navegación principal">
            <button class="tabbar__brand" type="button" @click="activo = 'hoy'" aria-label="Ir al inicio de Agenda DAQ"><span class="tabbar__brand-mark"><i data-lucide="calendar-check-2"></i></span><span>Agenda DAQ</span></button>
            <div class="tabbar__items">
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
            <button class="tabbar__perfil" type="button" :aria-current="activo === 'ajustes' ? 'page' : null" @click="activo = 'ajustes'" aria-label="Ir a Ajustes">
                <span class="avatar" style="background:var(--tint)" x-text="iniciales(<?= json_encode($usuarioNombre, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)"></span>
                <span class="tabbar__perfil-nombre"><?= e($usuarioNombre) ?></span>
            </button>
        </nav>

        <div class="app-shell__content">
            <section class="screen" x-show="activo === 'hoy'">
                <header class="navbar">
                    <p class="subhead" x-text="fechaHoy"></p>
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Hoy</h1>
                        <div class="navbar__acciones">
                            <button class="workspace-search" type="button" @click="activo = 'contactos'; $nextTick(() => $el.closest('.app-shell').querySelector('.search-field input')?.focus())" aria-label="Buscar contacto"><i data-lucide="search"></i><span>Buscar contacto</span></button>
                            <button class="workspace-create" type="button" @click="abrirNuevoContacto()"><i data-lucide="plus"></i><span>Nueva consulta</span></button>
                        </div>
                    </div>
                </header>
                <div class="screen__body">
                    <p class="error-inline" x-show="errorPanel" x-text="errorPanel"></p>
                    <div class="hoy-resumen" x-show="!errorPanel">
                        <div class="anillo-dia anillo-dia--pastel">
                            <div class="anillo-dia__grafico" aria-label="Progreso de recontactos de hoy">
                                <svg viewBox="0 0 110 110" aria-hidden="true"><circle class="anillo-dia__pista" cx="55" cy="55" r="46" fill="none" stroke-width="10"></circle><circle class="anillo-dia__progreso" cx="55" cy="55" r="46" fill="none" stroke-width="10" stroke-dasharray="289.03" :stroke-dashoffset="offsetAnillo()"></circle></svg>
                                <span class="anillo-dia__numero"><strong class="tabular" x-text="`${panel.anillo?.hechos_hoy || 0} / ${panel.anillo?.agendados_hoy || 0}`"></strong><span class="caption">hechos</span></span>
                            </div>
                            <div class="anillo-dia__filas"><p class="anillo-dia__etiqueta">Anillo del día</p><p class="anillo-dia__mensaje" x-text="mensajeAnillo()"></p><p class="anillo-dia__ayuda">Registrá un recontacto y el anillo avanza.</p></div>
                        </div>

                        <div class="semana-card">
                            <div class="semana-card__cabecera">
                                <p class="semana-card__titulo">Esta semana</p>
                                <button type="button" class="semana-card__ver" @click="activo = 'agenda'">Ver calendario</button>
                            </div>
                            <div class="semana-card__dias">
                                <template x-for="dia in semanaConLabel()" :key="dia.iso">
                                    <button type="button" class="semana-dia" :class="dia.estado ? `semana-dia--${dia.estado}` : ''" @click="irACalendarioDia(dia.iso)">
                                        <span class="semana-dia__letra" x-text="dia.letra"></span>
                                        <span class="semana-dia__num tabular" x-text="dia.numero"></span>
                                        <span class="semana-dia__punto"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card-grid" x-show="!errorPanel">
                        <div class="stat-card stat-card--vencido"><div class="stat-card__cabecera"><span class="stat-card__etiqueta">Vencidos</span><span class="stat-card__icono"><i data-lucide="alert-circle"></i></span></div><strong class="stat-card__numero tabular" x-text="panel.vencidos?.length || 0"></strong><p class="stat-card__ayuda">Necesitan atención</p></div>
                        <div class="stat-card stat-card--hoy"><div class="stat-card__cabecera"><span class="stat-card__etiqueta">Para hoy</span><span class="stat-card__icono"><i data-lucide="clock"></i></span></div><strong class="stat-card__numero tabular" x-text="panel.hoy?.length || 0"></strong><p class="stat-card__ayuda">Agendados para hoy</p></div>
                        <div class="stat-card stat-card--proximo"><div class="stat-card__cabecera"><span class="stat-card__etiqueta">Próximos días</span><span class="stat-card__icono"><i data-lucide="calendar-days"></i></span></div><strong class="stat-card__numero tabular" x-text="Object.values(panel.proximos || {}).flat().length"></strong><p class="stat-card__ayuda">Esta semana</p></div>
                        <div class="stat-card stat-card--concreto"><div class="stat-card__cabecera"><span class="stat-card__etiqueta">Concretaron</span><span class="stat-card__icono"><i data-lucide="check"></i></span></div><strong class="stat-card__numero tabular" x-text="panel.contadores?.cerrados_mes?.concreto || 0"></strong><p class="stat-card__ayuda">Este mes</p></div>
                    </div>

                    <div class="seccion-cabecera">
                        <h2>Recontactos</h2>
                        <div class="filtro-pills" role="group" aria-label="Filtrar recontactos">
                            <button class="filtro-pill" type="button" :aria-selected="filtroRecontactos === ''" @click="filtroRecontactos = ''">Todos</button>
                            <button class="filtro-pill" type="button" :aria-selected="filtroRecontactos === 'vencido'" @click="filtroRecontactos = 'vencido'">Vencidos</button>
                            <button class="filtro-pill" type="button" :aria-selected="filtroRecontactos === 'hoy'" @click="filtroRecontactos = 'hoy'">Para hoy</button>
                            <button class="filtro-pill" type="button" :aria-selected="filtroRecontactos === 'proximo'" @click="filtroRecontactos = 'proximo'">Próximos</button>
                        </div>
                    </div>

                    <section class="card-pastel-grid" aria-label="Recontactos">
                        <template x-for="item in tarjetasHoy().filter((i) => !filtroRecontactos || i.estado === filtroRecontactos)" :key="`t-${item.estado}-${item.contacto.id}`">
                            <article class="card-pastel" :class="{'card-pastel--vencido': item.estado === 'vencido', 'card-pastel--hoy': item.estado === 'hoy', 'card-pastel--proximo': item.estado === 'proximo'}">
                                <div class="card-pastel__head">
                                    <button type="button" class="card-pastel__persona" @click="abrirFicha(item.contacto.id)">
                                        <span class="avatar" :style="`background:${colorAvatar(item.contacto.nombre)}`" x-text="iniciales(item.contacto.nombre)"></span>
                                        <span class="card-pastel__cuerpo">
                                            <span class="card-pastel__nombre" x-text="item.contacto.nombre"></span>
                                            <span class="card-pastel__detalle" x-text="[item.contacto.producto_interes, item.contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"></span>
                                        </span>
                                    </button>
                                    <span class="chip-estado" x-text="{vencido:'Vencido', hoy:'Para hoy', proximo:'Próximo', sinfecha:'Sin fecha'}[item.estado]"></span>
                                </div>
                                <p class="card-pastel__fecha tabular" x-show="item.estado !== 'sinfecha'"><i data-lucide="clock"></i><span x-text="fechaCorta(item.contacto.proximo_contacto)"></span></p>
                                <div class="card-pastel__acciones">
                                    <a class="quick-action-card quick-action-card--llamar" :href="`tel:+${item.contacto.celular_norm}`"><i data-lucide="phone"></i>Llamar</a>
                                    <a class="quick-action-card quick-action-card--whatsapp" target="_blank" rel="noopener" :href="`https://wa.me/${item.contacto.celular_norm}`"><i data-lucide="message-circle"></i>WhatsApp</a>
                                    <button type="button" class="quick-action-card quick-action-card--posponer" x-show="item.estado !== 'sinfecha'" @click="posponerContacto(item.contacto, item.estado === 'hoy' ? 'hora' : 'manana')"><i data-lucide="clock-3"></i>Posponer</button>
                                    <button type="button" class="quick-action-card quick-action-card--nota" x-show="item.estado === 'sinfecha'" @click="abrirNota(item.contacto)"><i data-lucide="sticky-note"></i>Nota</button>
                                </div>
                            </article>
                        </template>
                    </section>

                    <div class="empty-state" x-show="!cargandoPanel && !tarjetasHoy().length"><i data-lucide="calendar-check"></i><p class="body-text">No tenés recontactos para hoy</p><button class="empty-state__boton" type="button" @click="abrirNuevoContacto()">Cargar consulta</button></div>
                    <div class="month-counts" x-show="panel.contadores?.abiertos"><span><strong class="tabular" x-text="panel.contadores?.abiertos || 0"></strong> abiertos</span><span><strong class="tabular" x-text="panel.contadores?.cerrados_mes?.concreto || 0"></strong> concretados este mes</span></div>
                </div>
            </section>

            <section class="screen" x-show="activo === 'contactos'">
                <header class="navbar">
                    <p class="subhead tabular" x-text="`${resumenContactos.total} contactos · ${resumenContactos.en_seguimiento} en seguimiento`"></p>
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Contactos</h1>
                        <div class="navbar__acciones">
                            <label class="search-field search-field--header">
                                <i data-lucide="search"></i>
                                <input type="search" x-model="busqueda" @input.debounce.300ms="aplicarFiltros()" placeholder="Nombre o celular" aria-label="Buscar contactos">
                            </label>
                            <button class="workspace-create" type="button" @click="abrirNuevoContacto()"><i data-lucide="plus"></i><span>Nueva consulta</span></button>
                        </div>
                    </div>
                </header>
                <div class="screen__body">
                    <div class="filtro-pills" role="tablist" aria-label="Estado de contactos">
                        <button class="filtro-pill" type="button" role="tab" :aria-selected="filtroEstado === ''" @click="filtroEstado = ''; aplicarFiltros()">Todos</button>
                        <button class="filtro-pill filtro-pill--nuevo" type="button" role="tab" :aria-selected="filtroEstado === 'pendiente'" @click="filtroEstado = 'pendiente'; aplicarFiltros()"><span class="filtro-pill__punto"></span>Nuevos</button>
                        <button class="filtro-pill filtro-pill--seguimiento" type="button" role="tab" :aria-selected="filtroEstado === 'seguimiento'" @click="filtroEstado = 'seguimiento'; aplicarFiltros()"><span class="filtro-pill__punto"></span>En seguimiento</button>
                        <button class="filtro-pill filtro-pill--concreto" type="button" role="tab" :aria-selected="filtroEstado === 'concreto'" @click="filtroEstado = 'concreto'; aplicarFiltros()"><span class="filtro-pill__punto"></span>Concretaron</button>
                        <button class="filtro-pill filtro-pill--cerrado" type="button" role="tab" :aria-selected="filtroEstado === 'cerrados'" @click="filtroEstado = 'cerrados'; aplicarFiltros()"><span class="filtro-pill__punto"></span>Cerrados</button>
                    </div>

                    <p class="error-inline" x-show="errorContactos" x-text="errorContactos"></p>
                    <template x-if="contactos.length">
                        <div class="card-pastel-grid contact-list">
                            <template x-for="contacto in contactos" :key="contacto.id">
                                <button
                                    type="button"
                                    class="card-pastel"
                                    :class="[`card-pastel--${estadoVisual(contacto)}`, {'card-pastel--seleccionada': contactoActual && Number(contactoActual.id) === Number(contacto.id)}]"
                                    @click="abrirFicha(contacto.id)"
                                >
                                    <div class="card-pastel__head">
                                        <span class="avatar" :style="`background:${colorAvatar(contacto.nombre)}`" x-text="iniciales(contacto.nombre)"></span>
                                        <span class="card-pastel__cuerpo">
                                            <span class="card-pastel__nombre" x-text="contacto.nombre"></span>
                                            <span class="card-pastel__detalle" x-text="contacto.celular"></span>
                                        </span>
                                    </div>
                                    <div class="card-pastel__tags">
                                        <span class="chip-estado" x-show="contacto.producto_interes" x-text="contacto.producto_interes"></span>
                                        <span class="chip-estado chip-estado--estado" x-text="etiquetaEstado(contacto)"></span>
                                    </div>
                                    <p class="card-pastel__pie">
                                        <span x-text="[contacto.localidad, etiquetaOrigen(contacto.origen)].filter(Boolean).join(' · ')"></span>
                                        <span class="tabular" x-text="fechaRelativa(contacto.creado_en)"></span>
                                    </p>
                                </button>
                            </template>
                        </div>
                    </template>
                    <div class="empty-state" x-show="!cargandoContactos && !contactos.length && !errorContactos">
                        <i data-lucide="users"></i>
                        <p class="body-text" x-text="busqueda || filtroEstado ? 'No encontramos contactos con esos filtros' : 'Todavía no cargaste ningún contacto'"></p>
                        <button class="empty-state__boton" type="button" @click="abrirNuevoContacto()" x-show="!busqueda && !filtroEstado">Cargar consulta</button>
                    </div>
                    <button class="btn-texto contacts-load-more" type="button" x-show="hayMasContactos" @click="cargarContactos()" :disabled="cargandoContactos" x-text="cargandoContactos ? 'Cargando…' : 'Cargar más contactos'"></button>
                </div>
            </section>

            <section class="screen" x-show="activo === 'agenda'">
                <header class="navbar">
                    <p class="subhead" x-text="`${eventosDelMes()} recontactos agendados este mes`"></p>
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title" x-text="tituloMes()"></h1>
                    </div>
                </header>
                <div class="screen__body">
                    <div class="calendario-toolbar">
                        <div class="segmented" role="tablist" aria-label="Vista del calendario" style="margin-bottom:0; max-width:260px">
                            <button type="button" class="segmented__option" role="tab" :aria-selected="vistaCalendario === 'mes'" @click="vistaCalendario = 'mes'">Mes</button>
                            <button type="button" class="segmented__option" role="tab" :aria-selected="vistaCalendario === 'semana'" @click="vistaCalendario = 'semana'">Semana</button>
                            <button type="button" class="segmented__option" role="tab" :aria-selected="vistaCalendario === 'dia'" @click="vistaCalendario = 'dia'">Día</button>
                        </div>
                        <div class="calendario-nav">
                            <button type="button" class="calendario-nav__flecha" @click="moverMes(-1)" aria-label="Mes anterior"><i data-lucide="chevron-left"></i></button>
                            <button type="button" class="calendario-nav__hoy" @click="volverMesHoy()">Hoy</button>
                            <button type="button" class="calendario-nav__flecha" @click="moverMes(1)" aria-label="Mes siguiente"><i data-lucide="chevron-right"></i></button>
                        </div>
                    </div>

                    <div class="calendario-layout">
                        <div class="calendario-principal">
                            <template x-if="vistaCalendario === 'mes'">
                                <div class="calendario-grid">
                                    <template x-for="letra in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="letra"><div class="calendario-grid__encabezado" x-text="letra"></div></template>
                                    <template x-for="dia in diasDelMes()" :key="dia.iso">
                                        <button type="button" class="calendario-dia" :class="{'calendario-dia--fuera': !dia.esMesActual, 'calendario-dia--hoy': dia.esHoy, 'calendario-dia--seleccionado': diaSeleccionado === dia.iso}" @click="seleccionarDia(dia.iso)">
                                            <span class="calendario-dia__numero" x-text="dia.numero"></span>
                                            <div class="calendario-dia__eventos">
                                                <template x-for="evento in eventosDia(dia.iso).slice(0, 2)" :key="`e-${evento.id}-${evento.fecha_evento}`">
                                                    <span class="calendario-evento" :class="[`calendario-evento--${evento.tipo_evento}`, {'calendario-evento--tachado': resultadoEventoAbierto && eventoAbierto?.id === evento.id}]" @click.stop="abrirEventoCalendario(evento)" x-text="`${fechaCorta(evento.fecha_evento)} ${evento.nombre}`"></span>
                                                </template>
                                                <span class="calendario-mas" x-show="eventosDia(dia.iso).length > 2" x-text="`+${eventosDia(dia.iso).length - 2} más`"></span>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            <template x-if="vistaCalendario === 'semana'">
                                <div>
                                    <div class="week-strip"><div class="week-strip__days"><template x-for="dia in diasSemana()" :key="dia.iso"><button type="button" class="week-strip__day" :class="{'week-strip__day--selected': diaSeleccionado === dia.iso, 'week-strip__day--today': dia.esHoy}" @click="seleccionarDia(dia.iso)"><span class="caption" x-text="dia.letra"></span><strong x-text="dia.numero"></strong><span class="week-strip__dot" x-show="eventosDia(dia.iso).length" :class="{'week-strip__dot--selected': diaSeleccionado === dia.iso}"></span></button></template></div></div>
                                    <p class="group__header footnote" x-text="tituloDiaSeleccionado()"></p>
                                    <div class="cal-item-lista" x-show="contactosDiaSeleccionado().length"><template x-for="contacto in contactosDiaSeleccionado()" :key="`a-${contacto.id}`"><button type="button" class="cal-item" :class="`cal-item--${contacto.tipo_evento}`" @click="abrirEventoCalendario(contacto)"><span class="cal-item__hora tabular" x-text="fechaCorta(contacto.fecha_evento)"></span><span class="cal-item__texto"><span class="cal-item__nombre" x-text="contacto.nombre"></span><span class="cal-item__motivo" x-text="[contacto.producto_interes, contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"></span></span><i data-lucide="chevron-right"></i></button></template></div>
                                    <div class="empty-state agenda-empty" x-show="!cargandoAgenda && !contactosDiaSeleccionado().length"><i data-lucide="calendar-days"></i><p class="body-text">Sin recontactos agendados</p></div>
                                </div>
                            </template>

                            <template x-if="vistaCalendario === 'dia'">
                                <div>
                                    <p class="group__header footnote" x-text="tituloDiaSeleccionado()"></p>
                                    <div class="cal-item-lista" x-show="contactosDiaSeleccionado().length"><template x-for="contacto in contactosDiaSeleccionado()" :key="`a-${contacto.id}`"><button type="button" class="cal-item" :class="`cal-item--${contacto.tipo_evento}`" @click="abrirEventoCalendario(contacto)"><span class="cal-item__hora tabular" x-text="fechaCorta(contacto.fecha_evento)"></span><span class="cal-item__texto"><span class="cal-item__nombre" x-text="contacto.nombre"></span><span class="cal-item__motivo" x-text="[contacto.producto_interes, contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"></span></span><i data-lucide="chevron-right"></i></button></template></div>
                                    <div class="empty-state agenda-empty" x-show="!cargandoAgenda && !contactosDiaSeleccionado().length"><i data-lucide="calendar-days"></i><p class="body-text">Sin recontactos agendados</p></div>
                                </div>
                            </template>
                        </div>

                        <aside class="calendario-panel">
                            <div class="cal-detalle-wrap" x-show="eventoAbierto" x-cloak @keydown.escape.window="cerrarEventoCalendario()">
                                <article class="cal-detalle" :class="claseCalDetalle()">
                                    <div class="cal-detalle__cabecera">
                                        <div class="cal-detalle__fila">
                                            <span class="cal-detalle__chip" x-text="etiquetaEventoCalendario(eventoAbierto)"></span>
                                            <button type="button" class="cal-detalle__cerrar" aria-label="Cerrar" @click="cerrarEventoCalendario()"><i data-lucide="x"></i></button>
                                        </div>
                                        <div class="cal-detalle__persona">
                                            <span class="avatar" :style="`background:${colorAvatar(eventoAbierto?.nombre)}`" x-text="iniciales(eventoAbierto?.nombre)"></span>
                                            <div class="cal-detalle__persona-texto">
                                                <p class="cal-detalle__nombre" x-text="eventoAbierto?.nombre"></p>
                                                <p class="cal-detalle__sub tabular" x-text="[eventoAbierto?.celular, eventoAbierto?.localidad].filter(Boolean).join(' · ')"></p>
                                            </div>
                                        </div>
                                        <p class="cal-detalle__cuando tabular"><i data-lucide="clock"></i><span x-text="fechaEventoCalendario(eventoAbierto)"></span></p>
                                    </div>
                                    <div class="cal-detalle__cuerpo">
                                        <template x-if="!resultadoEventoAbierto">
                                            <div class="cal-detalle__detalle">
                                                <p x-show="eventoAbierto?.consulta" x-text="eventoAbierto?.consulta"></p>
                                                <div class="cal-detalle__contacto">
                                                    <a class="boton-blanco boton-blanco--llamar" :href="`tel:+${eventoAbierto?.celular_norm}`"><i data-lucide="phone"></i>Llamar</a>
                                                    <a class="boton-blanco boton-blanco--whatsapp" target="_blank" rel="noopener" :href="`https://wa.me/${eventoAbierto?.celular_norm}`"><i data-lucide="message-circle"></i>WhatsApp</a>
                                                </div>
                                                <div class="cal-detalle__repro">
                                                    <p class="rotulo">Reprogramar</p>
                                                    <div class="repro-pills">
                                                        <button type="button" class="repro-pill" role="radio" aria-checked="false" @click="reprogramarEventoCalendario('hora')">Hoy +1 h</button>
                                                        <button type="button" class="repro-pill" role="radio" aria-checked="false" @click="reprogramarEventoCalendario('manana')">Mañana 9:00</button>
                                                        <button type="button" class="repro-pill" role="radio" aria-checked="false" @click="reprogramarEventoCalendario('tres_dias')">En 3 días</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <div class="cal-detalle__ok" x-show="resultadoEventoAbierto"><i data-lucide="check"></i><span x-text="resultadoEventoAbierto === 'reprogramado' ? 'Reprogramado' : 'Hecho'"></span></div>
                                        <div class="cal-detalle__pie">
                                            <button type="button" class="boton-blanco" @click="abrirFicha(eventoAbierto.id); cerrarEventoCalendario()">Abrir ficha</button>
                                            <button type="button" class="btn-principal" @click="marcarEventoCalendarioHecho()">Marcar hecho</button>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <div class="calendario-panel__resumen">
                                <p class="calendario-panel__dia" x-text="new Intl.DateTimeFormat('es-AR', {weekday:'long'}).format(desdeIso(diaSeleccionado))"></p>
                                <p class="calendario-panel__titulo" x-text="contactosDiaSeleccionado().length === 1 ? '1 recontacto pendiente' : `${contactosDiaSeleccionado().length} recontactos pendientes`"></p>
                            </div>
                            <div class="calendario-panel__lista">
                                <template x-for="evento in contactosDiaSeleccionado()" :key="`p-${evento.id}-${evento.fecha_evento}`">
                                    <button type="button" class="calendario-panel__evento" :class="`calendario-panel__evento--${evento.tipo_evento}`" @click="abrirEventoCalendario(evento)">
                                        <p class="calendario-panel__hora tabular" x-text="fechaCorta(evento.fecha_evento)"></p>
                                        <p class="calendario-panel__nombre" x-text="evento.nombre"></p>
                                        <p class="calendario-panel__detalle" x-text="[evento.producto_interes, evento.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"></p>
                                    </button>
                                </template>
                            </div>
                            <div class="calendario-leyenda">
                                <span class="calendario-leyenda__item"><span class="calendario-leyenda__punto calendario-leyenda__punto--vencido"></span>Vencido</span>
                                <span class="calendario-leyenda__item"><span class="calendario-leyenda__punto calendario-leyenda__punto--hoy"></span>Para hoy</span>
                                <span class="calendario-leyenda__item"><span class="calendario-leyenda__punto calendario-leyenda__punto--proximo"></span>Próximo</span>
                                <span class="calendario-leyenda__item"><span class="calendario-leyenda__punto calendario-leyenda__punto--hecho"></span>Hecho</span>
                            </div>

                            <div class="sheet-backdrop cal-evento-sheet" x-show="eventoAbierto" x-transition.opacity @keydown.escape.window="cerrarEventoCalendario()" @click.self="cerrarEventoCalendario()" x-cloak>
                                <div class="sheet" role="dialog" aria-label="Recontacto">
                                    <div class="sheet__grabber"></div>
                                    <div class="sheet__cabecera-pastel">
                                        <div class="cal-detalle__persona">
                                            <span class="avatar" :style="`background:${colorAvatar(eventoAbierto?.nombre)}`" x-text="iniciales(eventoAbierto?.nombre)"></span>
                                            <div class="cal-detalle__persona-texto">
                                                <p class="cal-detalle__nombre" x-text="eventoAbierto?.nombre"></p>
                                                <p class="cal-detalle__sub tabular" x-text="`${fechaEventoCalendario(eventoAbierto)} · ${etiquetaEventoCalendario(eventoAbierto)}`"></p>
                                            </div>
                                            <button type="button" aria-label="Cerrar" class="cal-detalle__cerrar" @click="cerrarEventoCalendario()"><i data-lucide="x"></i></button>
                                        </div>
                                    </div>
                                    <div class="sheet__body">
                                        <p x-show="eventoAbierto?.consulta" x-text="eventoAbierto?.consulta"></p>
                                        <div class="cal-evento-sheet__tiles">
                                            <a class="accion-tile accion-tile--llamar" :href="`tel:+${eventoAbierto?.celular_norm}`"><i data-lucide="phone"></i>Llamar</a>
                                            <a class="accion-tile accion-tile--whatsapp" target="_blank" rel="noopener" :href="`https://wa.me/${eventoAbierto?.celular_norm}`"><i data-lucide="message-circle"></i>WhatsApp</a>
                                            <button type="button" class="accion-tile accion-tile--posponer" @click="posponerContacto(eventoAbierto, 'manana'); cerrarEventoCalendario()"><i data-lucide="clock-3"></i>Posponer</button>
                                        </div>
                                        <div class="cal-detalle__pie">
                                            <button type="button" class="boton-blanco" @click="abrirFicha(eventoAbierto.id); cerrarEventoCalendario()">Ver ficha</button>
                                            <button type="button" class="btn-principal" @click="marcarEventoCalendarioHecho()">Marcar hecho</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>

            <section class="screen" x-show="activo === 'notas'">
                <header class="navbar">
                    <p class="subhead tabular" x-text="`${resumenNotas.total} notas · ${resumenNotas.fijadas} fijadas`"></p>
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Notas</h1>
                        <div class="navbar__acciones">
                            <label class="search-field search-field--header">
                                <i data-lucide="search"></i>
                                <input type="search" x-model="busquedaNotas" @input.debounce.300ms="cargarNotas()" placeholder="Buscar en notas" aria-label="Buscar en notas">
                            </label>
                        </div>
                    </div>
                </header>
                <div class="screen__body">
                    <p class="error-inline" x-show="errorNotas" x-text="errorNotas"></p>

                    <form class="nota-composer" @submit.prevent="crearNotaRapida()">
                        <p class="rotulo">Nueva nota</p>
                        <textarea x-model="composerTexto" rows="2" placeholder="Escribí algo para acordarte…"></textarea>
                        <div class="nota-composer__barra">
                            <span class="footnote">Color</span>
                            <div class="color-dots" role="radiogroup" aria-label="Color de la nota">
                                <template x-for="color in ['celeste','durazno','lavanda','menta','rosa']" :key="color">
                                    <button type="button" class="color-dot" :class="`color-dot--${color}`" role="radio" :aria-checked="composerColor === color" :aria-label="color" @click="composerColor = color"></button>
                                </template>
                            </div>
                            <button type="button" class="nota-composer__vincular" @click="buscadorContacto = !buscadorContacto"><i data-lucide="users"></i><span x-text="composerContacto ? composerContacto.nombre : 'Vincular contacto'"></span></button>
                            <button type="submit" class="btn-principal" :disabled="guardandoComposer || !composerTexto.trim()">Guardar</button>
                        </div>
                        <div class="group" x-show="buscadorContacto" x-cloak>
                            <label class="cell form-cell"><span class="cell__label">Buscar</span><input x-model="busquedaContacto" @input.debounce.300ms="buscarContactoParaNota()" placeholder="Nombre o celular"></label>
                            <template x-for="contacto in resultadosContacto" :key="contacto.id">
                                <button type="button" class="cell" @click="elegirContactoComposer(contacto)"><span class="avatar" :style="`background:${colorAvatar(contacto.nombre)}`" x-text="iniciales(contacto.nombre)"></span><span class="cell__content"><span class="cell__title" x-text="contacto.nombre"></span></span></button>
                            </template>
                        </div>
                    </form>

                    <section x-show="notasFijadas().length">
                        <p class="group__header footnote">fijadas</p>
                        <div class="notas-fijadas">
                            <template x-for="nota in notasFijadas()" :key="nota.id">
                                <button type="button" class="nota-card" :class="`nota--${nota.color}`" @click="abrirNota(nota)">
                                    <div class="nota-card__cabecera">
                                        <h3 class="nota-card__titulo" x-text="nota.titulo || 'Sin título'"></h3>
                                        <i class="nota-card__pin" data-lucide="pin"></i>
                                    </div>
                                    <p class="nota-card__texto" x-text="nota.texto"></p>
                                    <div class="nota-card__pie">
                                        <span class="contacto-chip" x-show="nota.contacto_nombre"><span class="avatar" :style="`background:${colorAvatar(nota.contacto_nombre)}`" x-text="iniciales(nota.contacto_nombre)"></span><span x-text="nota.contacto_nombre"></span></span>
                                        <span class="nota-card__fecha tabular" x-text="fechaNotaFijada(nota.actualizado_en)"></span>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </section>

                    <section x-show="notasRecientes().length">
                        <p class="group__header footnote">recientes</p>
                        <div class="notas-recientes">
                            <template x-for="nota in notasRecientes()" :key="nota.id">
                                <button type="button" class="nota-card" :class="`nota--${nota.color}`" @click="abrirNota(nota)">
                                    <h3 class="nota-card__titulo" x-text="nota.titulo || 'Sin título'"></h3>
                                    <p class="nota-card__texto" x-text="nota.texto"></p>
                                    <div class="nota-card__pie">
                                        <span class="contacto-chip" x-show="nota.contacto_nombre"><span class="avatar" :style="`background:${colorAvatar(nota.contacto_nombre)}`" x-text="iniciales(nota.contacto_nombre)"></span><span x-text="nota.contacto_nombre"></span></span>
                                        <span class="nota-card__fecha tabular" x-text="fechaNotaReciente(nota.actualizado_en)"></span>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </section>

                    <div class="empty-state" x-show="!cargandoNotas && !notas.length">
                        <i data-lucide="sticky-note"></i>
                        <p class="body-text" x-text="busquedaNotas ? 'No encontramos notas con esa búsqueda' : 'Todavía no tenés notas'"></p>
                    </div>
                </div>
            </section>

            <div class="sheet-backdrop nota-editor-backdrop" x-show="sheetNota" x-transition.opacity @keydown.escape.window="cerrarNota()" @click.self="cerrarNota()" x-cloak>
                <div class="nota-editor" :class="`nota--${notaActual?.color}`" x-show="notaActual">
                    <div class="nota-editor__barra">
                        <div class="color-dots" role="radiogroup" aria-label="Color de la nota">
                            <template x-for="color in ['celeste','durazno','lavanda','menta','rosa']" :key="color">
                                <button type="button" class="color-dot" :class="`color-dot--${color}`" role="radio" :aria-checked="notaActual?.color === color" :aria-label="color" @click="elegirColorNotaActual(color)"></button>
                            </template>
                        </div>
                        <div class="nota-editor__acciones">
                            <button type="button" class="nota-editor__icono" :aria-pressed="Number(notaActual?.fijada) === 1" aria-label="Fijar nota" @click="alternarFijada(notaActual)"><i data-lucide="pin"></i></button>
                            <button type="button" class="nota-editor__icono" aria-label="Cerrar" @click="cerrarNota()"><i data-lucide="x"></i></button>
                        </div>
                    </div>
                    <div class="nota-editor__cuerpo">
                        <label><span class="sr-only">Título</span><input type="text" class="nota-editor__titulo" x-model="notaActual.titulo" @input="programarAutoguardado()" placeholder="Título"></label>
                        <label><span class="sr-only">Texto de la nota</span><textarea class="nota-editor__texto" rows="5" x-model="notaActual.texto" @input="programarAutoguardado()" placeholder="Escribí tu nota…"></textarea></label>

                        <div class="nota-editor__contacto" x-show="notaActual?.contacto_nombre">
                            <p class="rotulo">Contacto</p>
                            <div class="nota-editor__contacto-fila">
                                <span class="avatar" :style="`background:${colorAvatar(notaActual?.contacto_nombre)}`" x-text="iniciales(notaActual?.contacto_nombre)"></span>
                                <span class="nota-editor__contacto-nombre" x-text="notaActual?.contacto_nombre"></span>
                                <button type="button" class="btn-texto" @click="sheetNota = false; abrirFicha(notaActual.contacto_id)">Abrir ficha</button>
                            </div>
                        </div>

                        <div>
                            <p class="rotulo">Recordarme</p>
                            <div class="repro-pills">
                                <button type="button" class="repro-pill" role="radio" :aria-checked="Boolean(notaActual?.recordar_en)" @click="elegirRecordatorioNota('hoy')">Más tarde hoy</button>
                                <button type="button" class="repro-pill" role="radio" @click="elegirRecordatorioNota('manana')">Mañana 9:00</button>
                                <button type="button" class="repro-pill" role="radio" :aria-checked="!notaActual?.recordar_en" @click="elegirRecordatorioNota('nunca')">Sin recordatorio</button>
                            </div>
                        </div>
                    </div>
                    <div class="nota-editor__pie">
                        <span class="nota-editor__guardado"><i data-lucide="check"></i><span x-text="textoGuardadoNota()"></span></span>
                        <button type="button" class="btn-texto btn-texto--peligro" @click="pedirEliminarNota()">Eliminar</button>
                        <button type="button" class="btn-principal" @click="cerrarNota()">Listo</button>
                    </div>
                </div>
            </div>

            <div class="sheet-backdrop" x-show="eliminandoNota" @keydown.escape.window="eliminandoNota = false" @click.self="eliminandoNota = false" x-cloak>
                <section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true"><div class="sheet__grabber"></div><div class="sheet__body"><i class="alert-sheet__icon alert-sheet__icon--danger" data-lucide="trash-2"></i><h2 class="title2">¿Eliminar nota?</h2><p class="subhead">Esta acción no se puede deshacer.</p><div class="alert-sheet__actions"><button class="btn-principal btn-principal--danger" type="button" @click="eliminarNotaActual()">Eliminar nota</button><button class="btn-texto" type="button" @click="eliminandoNota = false">Cancelar</button></div></div></section>
            </div>

            <section class="screen" x-show="activo === 'ajustes'">
                <header class="navbar">
                    <div class="navbar__large-row">
                        <h1 class="navbar__large-title">Ajustes</h1>
                    </div>
                </header>
                <div class="screen__body">
                    <div class="ajustes">
                        <div class="perfil-card">
                            <span class="avatar" style="background:var(--indigo)" x-text="iniciales(<?= json_encode($usuarioNombre, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)"></span>
                            <div>
                                <p class="perfil-card__nombre"><?= e($usuarioNombre) ?></p>
                                <p class="perfil-card__sub">Agenda personal · Imperio Comercial</p>
                            </div>
                            <span class="perfil-card__chip" x-show="instalada">App instalada</span>
                        </div>

                        <div>
                            <p class="group__header footnote">aplicación</p>
                            <div class="group">
                                <button class="cell" type="button" x-show="!instalada && !esIos" @click="instalarApp()"><span class="cell__icon-box cell__icon-box--tint"><i data-lucide="download"></i></span><span class="cell__content"><span class="cell__title">Instalar en Android</span><span class="cell__subtitle" x-text="eventoInstalacion ? 'Abrir como una app independiente' : 'Disponible en Chrome con conexión segura'"></span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                                <div class="cell" x-show="esIos && !instalada"><span class="cell__icon-box cell__icon-box--indigo"><i data-lucide="share"></i></span><span class="cell__content"><span class="cell__title">Instalar en iPhone o iPad</span><span class="cell__subtitle">Compartir → Agregar a inicio</span></span></div>
                                <div class="cell" x-show="instalada"><span class="cell__icon-box cell__icon-box--green"><i data-lucide="check"></i></span><span class="cell__content"><span class="cell__title">App instalada</span><span class="cell__subtitle">Se abre a pantalla completa</span></span></div>
                                <div class="cell"><span class="cell__icon-box cell__icon-box--orange"><i data-lucide="bell"></i></span><span class="cell__content"><span class="cell__title">Notificaciones</span><span class="cell__subtitle" x-text="push.dispositivos?.length ? `${push.dispositivos.length} dispositivo(s) activo(s)` : 'Recibí recordatorios push'"></span></span><button type="button" class="toggle" role="switch" :aria-checked="push.dispositivos?.length ? 'true' : 'false'" aria-label="Notificaciones" :disabled="activandoPush" @click="activarPush()"><span class="toggle__perilla"></span></button></div>
                                <button class="cell" type="button" @click="sheetPush = true; refrescarIconos()"><span class="cell__icon-box cell__icon-box--green"><i data-lucide="clock"></i></span><span class="cell__content"><span class="cell__title">Resumen diario</span><span class="cell__subtitle">Push con los recontactos del día</span></span><span class="cell__valor-chip tabular" x-text="push.hora_resumen"></span></button>
                            </div>
                        </div>

                        <div>
                            <p class="group__header footnote">organización</p>
                            <div class="group">
                                <button class="cell" type="button" @click="abrirEtiquetas()">
                                    <span class="cell__icon-box cell__icon-box--orange"><i data-lucide="tags"></i></span>
                                    <span class="cell__content"><span class="cell__title">Etiquetas</span><span class="cell__subtitle" x-text="etiquetas.length ? `${etiquetas.length} creadas` : 'Organizá tus contactos'"></span></span>
                                    <span class="ajustes-vista-etiquetas">
                                        <template x-for="etiqueta in etiquetas.slice(0, 2)" :key="etiqueta.id">
                                            <span class="etiqueta-chip"><span class="tag__dot" :style="`background:${etiqueta.color}`"></span><span x-text="etiqueta.nombre"></span></span>
                                        </template>
                                    </span>
                                    <span class="cell__chevron"><i data-lucide="chevron-right"></i></span>
                                </button>
                            </div>
                        </div>

                        <div x-show="esAdmin" x-cloak>
                            <p class="group__header footnote">equipo</p>
                            <form class="usuarios-form" @submit.prevent="crearUsuario()">
                                <div class="group form-group">
                                    <label class="cell form-cell"><span class="cell__label">Usuario</span><input x-model.trim="formularioUsuario.usuario" autocomplete="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" required placeholder="nombre.apellido"></label>
                                    <label class="cell form-cell"><span class="cell__label">Contraseña</span><input type="password" x-model="formularioUsuario.password" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password" minlength="10" required placeholder="Solo números · mínimo 10 dígitos"></label>
                                    <label class="cell form-cell"><span class="cell__label">Repetir</span><input type="password" x-model="formularioUsuario.confirmacion" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password" minlength="10" required placeholder="Repetí los 10 dígitos"></label>
                                    <label class="cell form-cell"><span class="cell__label">Rol</span><select x-model="formularioUsuario.rol"><option value="supervisor">Supervisor</option><option value="admin">Admin</option></select></label>
                                </div>
                                <button class="btn-principal" type="submit" :disabled="guardandoUsuario"><i data-lucide="user-plus"></i><span x-text="guardandoUsuario ? 'Creando…' : 'Agregar usuario'"></span></button>
                            </form>
                            <div class="group usuarios-lista" x-show="usuarios.length">
                                <template x-for="usuario in usuarios" :key="usuario.id">
                                    <div class="cell usuario-fila">
                                        <span class="cell__icon-box cell__icon-box--indigo"><i data-lucide="user-round"></i></span>
                                        <span class="cell__content"><span class="cell__title" x-text="usuario.usuario"></span><span class="cell__subtitle tabular" x-text="`Creado ${fechaLarga(usuario.creado_en)}`"></span></span>
                                        <span class="usuario-rol" :class="`usuario-rol--${usuario.rol}`" x-text="usuario.rol === 'admin' ? 'Admin' : 'Supervisor'"></span>
                                        <button class="btn-texto btn-texto--peligro" type="button" x-show="Number(usuario.id) !== usuarioActual.id" @click="eliminarUsuario(usuario)" :aria-label="`Eliminar a ${usuario.usuario}`"><i data-lucide="trash-2"></i></button>
                                    </div>
                                </template>
                            </div>
                            <p class="empty-inline" x-show="!cargandoUsuarios && !usuarios.length">Todavía no hay usuarios cargados.</p>
                        </div>

                        <div>
                            <p class="group__header footnote">seguridad</p>
                            <div class="group">
                                <button class="cell" type="button" @click="sheetPassword = true; refrescarIconos()"><span class="cell__icon-box cell__icon-box--indigo"><i data-lucide="key-round"></i></span><span class="cell__content"><span class="cell__title">Cambiar contraseña</span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                                <button class="cell" type="button" @click="sheetSesiones = true; refrescarIconos()"><span class="cell__icon-box cell__icon-box--gray"><i data-lucide="monitor-off"></i></span><span class="cell__content"><span class="cell__title">Cerrar sesión en todos los dispositivos</span><span class="cell__subtitle">Revoca sesiones guardadas</span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                            </div>
                        </div>

                        <div>
                            <p class="group__header footnote">cuenta</p>
                            <div class="group">
                                <a class="cell cell--peligro" href="logout.php">
                                    <span class="cell__icon-box cell__icon-box--red"><i data-lucide="log-out"></i></span>
                                    <span class="cell__content"><span class="cell__title">Cerrar sesión</span></span>
                                    <span class="cell__chevron"><i data-lucide="chevron-right"></i></span>
                                </a>
                            </div>
                        </div>

                        <p class="ajustes-footer">Agenda DAQ · versión <?= e(APP_VERSION) ?></p>
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
                    <template x-if="modoFormulario === 'nuevo'"><div><p class="group__header footnote">consulta</p><label class="textarea-celeste"><span class="sr-only">Consulta</span><textarea x-model.trim="formularioContacto.consulta" required maxlength="4000" rows="3" placeholder="¿Qué está buscando?"></textarea></label></div></template>
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
                    <template x-if="seguimientoModo === 'recontacto'"><div><div class="resultado-grid" role="tablist" aria-label="Resultado del recontacto"><template x-for="opcion in [{id: 'atendio', texto: 'Atendió'}, {id: 'no_atendio', texto: 'No atendió'}, {id: 'mensaje_enviado', texto: 'Mensaje'}]" :key="opcion.id"><button type="button" class="resultado-opcion" :class="`resultado-opcion--${opcion.id}`" role="tab" :aria-selected="formularioSeguimiento.resultado === opcion.id" @click="formularioSeguimiento.resultado = opcion.id" x-text="opcion.texto"></button></template></div><label class="note-field"><span class="footnote">nota</span><textarea x-model.trim="formularioSeguimiento.nota" maxlength="4000" rows="2" placeholder="Agregá un detalle opcional"></textarea></label><div class="segmented" role="tablist" aria-label="Próxima acción"><button type="button" class="segmented__option" :aria-selected="formularioSeguimiento.accion === 'reagendar'" @click="formularioSeguimiento.accion = 'reagendar'">Reagendar</button><button type="button" class="segmented__option" :aria-selected="formularioSeguimiento.accion === 'cerrar'" @click="formularioSeguimiento.accion = 'cerrar'">Cerrar</button></div><div x-show="formularioSeguimiento.accion === 'reagendar'"><div class="pills date-pills"><button class="pill" type="button" @click="asignarFechaSeguimiento(1)">Mañana</button><button class="pill" type="button" @click="asignarFechaSeguimiento(3)">En 3 días</button><button class="pill" type="button" @click="asignarFechaSeguimiento(7)">En 1 semana</button><label class="pill pill--input">Elegir… <input type="datetime-local" x-model="formularioSeguimiento.proximo_contacto" aria-label="Próxima fecha"></label></div><p class="group__footer footnote" x-show="formularioSeguimiento.proximo_contacto" x-text="fechaLarga(formularioSeguimiento.proximo_contacto)"></p></div><div x-show="formularioSeguimiento.accion === 'cerrar'" class="close-reasons"><p class="footnote">motivo de cierre</p><div class="pills"><template x-for="opcion in [{id: 'concreto', texto: 'Concretó'}, {id: 'no_interesa', texto: 'No interesa'}, {id: 'sin_respuesta', texto: 'Sin respuesta'}]" :key="opcion.id"><button type="button" class="pill" :aria-selected="formularioSeguimiento.motivo_cierre === opcion.id" @click="formularioSeguimiento.motivo_cierre = opcion.id" x-text="opcion.texto"></button></template></div></div></div></template>
                    <template x-if="seguimientoModo === 'nota'"><label class="note-field"><span class="footnote">nota</span><textarea x-model.trim="formularioSeguimiento.nota" required maxlength="4000" rows="4" placeholder="Escribí una nota"></textarea></label></template>
                    <template x-if="seguimientoModo === 'reabrir'"><div><label class="note-field"><span class="footnote">nota (opcional)</span><textarea x-model.trim="formularioSeguimiento.nota" maxlength="4000" rows="2" placeholder="Motivo para reabrir"></textarea></label><p class="footnote">nueva fecha</p><div class="pills date-pills"><button class="pill" type="button" @click="asignarFechaSeguimiento(1)">Mañana</button><button class="pill" type="button" @click="asignarFechaSeguimiento(3)">En 3 días</button><button class="pill" type="button" @click="asignarFechaSeguimiento(7)">En 1 semana</button><label class="pill pill--input">Elegir… <input type="datetime-local" x-model="formularioSeguimiento.proximo_contacto" aria-label="Fecha para reabrir"></label></div></div></template>
                </div>
            </form>
        </div>

        <div class="sheet-backdrop" x-show="sheetDuplicado" x-transition.opacity @keydown.escape.window="sheetDuplicado = false" @click.self="sheetDuplicado = false" x-cloak>
            <section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true" aria-labelledby="duplicado-titulo"><div class="sheet__grabber"></div><div class="sheet__body"><i class="alert-sheet__icon" data-lucide="copy"></i><h2 id="duplicado-titulo" class="title2">Este número ya está cargado</h2><p class="subhead">Se lo agregará como una nueva consulta a <strong x-text="duplicadoPendiente?.nombre"></strong>.</p><div class="alert-sheet__actions"><button type="button" class="btn-principal" @click="guardarContacto(true)">Agregar consulta</button><button type="button" class="btn-texto" @click="sheetDuplicado = false">Cancelar</button></div></div></section>
        </div>

        <div class="sheet-backdrop" x-show="sheetPush" x-transition.opacity @keydown.escape.window="sheetPush = false" @click.self="sheetPush = false" x-cloak><section class="sheet" aria-labelledby="push-titulo"><div class="sheet__grabber"></div><header class="sheet__header"><button class="btn-texto" type="button" @click="sheetPush = false">Cerrar</button><h2 id="push-titulo" class="sheet__title">Notificaciones</h2><span></span></header><div class="sheet__body"><div class="group"><button class="cell" type="button" @click="activarPush()" :disabled="activandoPush"><span class="cell__content"><span class="cell__title">Activar en este dispositivo</span><span class="cell__subtitle" x-text="activandoPush ? 'Solicitando permiso…' : (esIos && !instalada ? 'Primero agregá la app a Inicio' : 'Recibí recordatorios push')"></span></span><span class="toggle" role="switch" :aria-checked="push.dispositivos?.length ? 'true' : 'false'"><span class="toggle__perilla"></span></span></button></div><p class="group__header footnote">dispositivos activos</p><div class="group"><template x-for="dispositivo in push.dispositivos" :key="dispositivo.id"><div class="cell"><span class="cell__icon-box" style="background:var(--tint)"><i data-lucide="smartphone"></i></span><span class="cell__content"><span class="cell__title" x-text="dispositivo.dispositivo || 'Dispositivo' "></span><span class="cell__subtitle" x-text="dispositivo.ultimo_uso || dispositivo.creado_en"></span></span><button class="btn-texto btn-texto--peligro" type="button" @click="quitarPush(dispositivo)">Quitar</button></div></template><p class="empty-inline" x-show="!push.dispositivos?.length">Todavía no hay dispositivos activos.</p></div><button class="btn-principal" type="button" @click="enviarPruebaPush()" :disabled="!push.dispositivos?.length">Enviar prueba</button><p class="group__header footnote settings-list-header">resumen diario</p><div class="group"><label class="cell form-cell"><span class="cell__label">Hora</span><input type="time" x-model="push.hora_resumen" @change="guardarHoraResumen()" aria-label="Hora del resumen diario"></label></div><p class="group__footer footnote">En iPhone, las notificaciones requieren que Agenda esté instalada desde Compartir → Agregar a inicio.</p></div></section></div>

        <div class="sheet-backdrop" x-show="sheetEtiquetas" x-transition.opacity @keydown.escape.window="sheetEtiquetas = false" @click.self="sheetEtiquetas = false" x-cloak><section class="sheet" aria-labelledby="etiquetas-titulo"><div class="sheet__grabber"></div><header class="sheet__header"><button class="btn-texto" type="button" @click="sheetEtiquetas = false">Cerrar</button><h2 id="etiquetas-titulo" class="sheet__title">Etiquetas</h2><span></span></header><div class="sheet__body"><form @submit.prevent="guardarEtiqueta()"><div class="group form-group"><label class="cell form-cell"><span class="cell__label">Nombre</span><input x-model.trim="formularioEtiqueta.nombre" maxlength="50" required placeholder="Nueva etiqueta"></label><label class="cell form-cell"><span class="cell__label">Color</span><input class="color-input" type="color" x-model="formularioEtiqueta.color" aria-label="Color de etiqueta"></label></div><button class="btn-principal" type="submit" :disabled="guardandoAjuste" x-text="editandoEtiqueta ? 'Guardar cambios' : 'Crear etiqueta'"></button><button class="btn-texto settings-cancel-edit" type="button" x-show="editandoEtiqueta" @click="cancelarEtiqueta()">Cancelar edición</button></form><p class="group__header footnote settings-list-header">tus etiquetas</p><div class="group"><template x-for="etiqueta in etiquetas" :key="etiqueta.id"><div class="cell"><span class="tag__dot" :style="`background:${etiqueta.color}`"></span><span class="cell__content"><span class="cell__title" x-text="etiqueta.nombre"></span></span><button class="btn-texto" type="button" @click="editarEtiqueta(etiqueta)">Editar</button><button class="btn-texto btn-texto--peligro" type="button" @click="eliminarEtiqueta(etiqueta)" aria-label="Eliminar etiqueta"><i data-lucide="trash-2"></i></button></div></template><p class="empty-inline" x-show="!etiquetas.length">Todavía no creaste etiquetas.</p></div></div></section></div>

        <div class="sheet-backdrop" x-show="sheetPassword" x-transition.opacity @keydown.escape.window="sheetPassword = false" @click.self="sheetPassword = false" x-cloak><form class="sheet sheet--media" @submit.prevent="cambiarPassword()"><div class="sheet__grabber"></div><header class="sheet__header"><button class="btn-texto" type="button" @click="sheetPassword = false">Cancelar</button><h2 class="sheet__title">Cambiar contraseña</h2><button class="btn-texto btn-texto--negrita" type="submit" :disabled="guardandoAjuste">Guardar</button></header><div class="sheet__body"><div class="group form-group"><label class="cell form-cell"><span class="cell__label">Actual</span><input type="password" x-model="formularioPassword.actual" autocomplete="current-password" required></label><label class="cell form-cell"><span class="cell__label">Nueva</span><input type="password" x-model="formularioPassword.nueva" autocomplete="new-password" minlength="10" required></label><label class="cell form-cell"><span class="cell__label">Repetir</span><input type="password" x-model="formularioPassword.confirmacion" autocomplete="new-password" minlength="10" required></label></div><p class="group__footer footnote">Usá al menos 10 caracteres. Al cambiarla se cerrarán las sesiones guardadas.</p></div></form></div>

        <div class="sheet-backdrop" x-show="sheetSesiones" x-transition.opacity @keydown.escape.window="sheetSesiones = false" @click.self="sheetSesiones = false" x-cloak><section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true"><div class="sheet__grabber"></div><div class="sheet__body"><i class="alert-sheet__icon" data-lucide="monitor-off"></i><h2 class="title2">¿Cerrar sesiones guardadas?</h2><p class="subhead">Los otros dispositivos tendrán que iniciar sesión nuevamente. Este dispositivo seguirá abierto.</p><div class="alert-sheet__actions"><button class="btn-principal" type="button" :disabled="guardandoAjuste" @click="cerrarSesiones()">Cerrar sesiones</button><button class="btn-texto" type="button" @click="sheetSesiones = false">Cancelar</button></div></div></section></div>

        <div class="sheet-backdrop detail-backdrop" x-show="sheetFicha" x-transition.opacity @keydown.escape.window="sheetFicha = false" @click.self="sheetFicha = false" x-cloak>
            <article class="sheet contact-detail contact-detail--ancho" x-show="contactoActual">
                <div class="sheet__grabber"></div>
                <header class="sheet__header"><button class="btn-texto" type="button" @click="sheetFicha = false">Cerrar</button><span class="sheet__title">Contacto</span><button class="btn-texto btn-texto--negrita" type="button" @click="editarContacto()">Editar</button></header>
                <div class="sheet__body">
                    <div class="contact-detail__identidad" :class="`contact-detail__identidad--${estadoVisual(contactoActual)}`">
                        <span class="avatar avatar--grande" :style="`background:${colorAvatar(contactoActual?.nombre)}`" x-text="iniciales(contactoActual?.nombre)"></span>
                        <div class="contact-detail__texto">
                            <div class="contact-detail__nombre-fila">
                                <h2 class="title2" x-text="contactoActual?.nombre"></h2>
                                <span class="contact-detail__badge" x-text="etiquetaEstado(contactoActual)"></span>
                            </div>
                            <p class="contact-detail__sub tabular" x-text="`${contactoActual?.celular} · Cliente desde el ${fechaSoloDia(contactoActual?.creado_en)}`"></p>
                            <div class="card-pastel__tags" x-show="contactoActual?.producto_interes || contactoActual?.etiquetas?.length">
                                <span class="chip-estado" x-show="contactoActual?.producto_interes" x-text="contactoActual?.producto_interes"></span>
                                <template x-for="etiqueta in contactoActual?.etiquetas" :key="etiqueta.id"><span class="chip-estado" x-text="etiqueta.nombre"></span></template>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a class="action-button action-button--llamar" :href="`tel:+${contactoActual?.celular_norm}`"><span class="action-button__icon"><i data-lucide="phone"></i></span><span class="action-button__label">Llamar</span></a>
                            <a class="action-button action-button--whatsapp" target="_blank" rel="noopener" :href="`https://wa.me/${contactoActual?.celular_norm}`"><span class="action-button__icon"><i data-lucide="message-circle"></i></span><span class="action-button__label">WhatsApp</span></a>
                            <button class="action-button action-button--recontacto" type="button" @click="abrirSeguimiento(contactoActual)"><span class="action-button__icon"><i data-lucide="phone-forwarded"></i></span><span class="action-button__label">Recontacto</span></button>
                            <button class="action-button action-button--nota" type="button" @click="abrirNota(contactoActual)"><span class="action-button__icon"><i data-lucide="sticky-note"></i></span><span class="action-button__label">Nota</span></button>
                        </div>
                    </div>

                    <div class="contact-detail__columnas">
                        <div class="contact-detail__columna">
                            <div class="ficha-proximo" :class="{'ficha-proximo--vencido': fichaProximoVencido(contactoActual)}" x-show="contactoActual?.estado !== 'cerrada'">
                                <span class="ficha-proximo__icono"><i data-lucide="phone-forwarded"></i></span>
                                <div class="ficha-proximo__texto">
                                    <p class="ficha-proximo__rotulo">Próximo recontacto</p>
                                    <p class="ficha-proximo__cuando tabular" x-text="fechaLarga(contactoActual?.proximo_contacto)"></p>
                                    <p class="ficha-proximo__detalle" x-show="ultimaNotaProximo(contactoActual)" x-text="ultimaNotaProximo(contactoActual)"></p>
                                </div>
                                <div class="ficha-proximo__acciones">
                                    <button type="button" class="boton-blanco boton-blanco--posponer" @click="posponerContacto(contactoActual, 'manana')"><i data-lucide="clock-3"></i>Posponer</button>
                                    <button type="button" class="boton-blanco boton-blanco--hecho" @click="abrirSeguimiento(contactoActual)"><i data-lucide="check"></i>Hecho</button>
                                </div>
                            </div>

                            <p class="group__header footnote">historial</p>
                            <div class="historial">
                                <template x-for="registro in contactoActual?.historial" :key="registro.id">
                                    <div class="historial__item">
                                        <div class="historial__riel">
                                            <span class="historial__icono" :class="`historial__icono--${colorTimeline(registro)}`"><i :data-lucide="iconoTimeline(registro)"></i></span>
                                            <span class="historial__linea"></span>
                                        </div>
                                        <div class="historial__cuerpo">
                                            <span class="historial__titulo" x-text="tituloTimeline(registro)"></span><span class="historial__chip historial__chip--orange" x-show="registro.proximo_asignado" x-text="`Para ${fechaCorta(registro.proximo_asignado)}`"></span>
                                            <p class="historial__meta tabular" x-text="fechaLarga(registro.fecha)"></p>
                                            <p class="historial__nota" x-show="registro.nota" x-text="registro.nota"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="contact-detail__columna">
                            <div class="group group--estado">
                                <p class="group__header footnote">estado</p>
                                <div class="estado-selector" role="radiogroup" aria-label="Estado del contacto">
                                    <button type="button" class="estado-opcion estado-opcion--nuevo" role="radio" :aria-checked="estadoVisual(contactoActual) === 'nuevo'" @click="cambiarEstadoSelector(contactoActual, 'nuevo')"><span class="estado-opcion__punto"></span>Nuevo</button>
                                    <button type="button" class="estado-opcion estado-opcion--seguimiento" role="radio" :aria-checked="estadoVisual(contactoActual) === 'seguimiento'" @click="cambiarEstadoSelector(contactoActual, 'seguimiento')"><span class="estado-opcion__punto"></span>En seguimiento</button>
                                    <button type="button" class="estado-opcion estado-opcion--concreto" role="radio" :aria-checked="estadoVisual(contactoActual) === 'concreto'" @click="cambiarEstadoSelector(contactoActual, 'concreto')"><span class="estado-opcion__punto"></span>Concretó</button>
                                    <button type="button" class="estado-opcion estado-opcion--cerrado" role="radio" :aria-checked="estadoVisual(contactoActual) === 'cerrado'" @click="cambiarEstadoSelector(contactoActual, 'cerrado')"><span class="estado-opcion__punto"></span>Cerrado</button>
                                </div>
                            </div>

                            <div class="group">
                                <div class="cell" x-show="contactoActual?.producto_interes"><span class="cell__label">Producto</span><span class="cell__value" x-text="contactoActual?.producto_interes"></span></div>
                                <div class="cell" x-show="contactoActual?.localidad"><span class="cell__label">Localidad</span><span class="cell__value" x-text="contactoActual?.localidad"></span></div>
                                <div class="cell"><span class="cell__label">Origen</span><span class="cell__value" x-text="etiquetaOrigen(contactoActual?.origen)"></span></div>
                            </div>

                            <template x-if="notasDelContacto(contactoActual).length">
                                <div>
                                    <p class="group__header footnote">notas</p>
                                    <div class="notas-mini-lista">
                                        <template x-for="nota in notasDelContacto(contactoActual)" :key="nota.id">
                                            <div class="nota-mini nota--durazno"><p class="nota-mini__titulo tabular" x-text="fechaLarga(nota.fecha)"></p><p class="nota-mini__texto" x-text="nota.nota"></p></div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <button class="btn-texto btn-texto--peligro delete-contact" type="button" @click="pedirEliminar()">Eliminar contacto</button>
                </div>
            </article>
        </div>

        <div class="sheet-backdrop" x-show="sheetEliminar" x-transition.opacity @keydown.escape.window="sheetEliminar = false" @click.self="sheetEliminar = false" x-cloak><section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true"><div class="sheet__grabber"></div><div class="sheet__body"><i class="alert-sheet__icon alert-sheet__icon--danger" data-lucide="trash-2"></i><h2 class="title2">¿Eliminar contacto?</h2><p class="subhead">Se eliminarán también sus consultas y su historial. Esta acción no se puede deshacer.</p><div class="alert-sheet__actions"><button class="btn-principal btn-principal--danger" type="button" @click="eliminarContacto()">Eliminar contacto</button><button class="btn-texto" type="button" @click="sheetEliminar = false">Cancelar</button></div></div></section></div>

        <div class="hud-capa" x-show="hudVisible" x-transition.opacity x-cloak><div class="hud"><i :data-lucide="hudIcono"></i><span x-text="hudTexto"></span></div></div>
    </div>

    <script>window.APP_CONFIG = { csrf: <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, usuario: <?= json_encode(['id' => $usuarioId, 'nombre' => $usuarioNombre, 'rol' => $usuarioRol], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> };</script>
    <script src="assets/vendor/alpine.min.js" defer></script>
    <script src="assets/vendor/lucide/lucide.min.js"></script>
    <script src="assets/js/contactos.js"></script>
    <script src="assets/js/seguimientos.js"></script>
    <script src="assets/js/panel.js"></script>
    <script src="assets/js/ajustes.js"></script>
    <script src="assets/js/notas.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/pwa.js"></script>
</body>
</html>
