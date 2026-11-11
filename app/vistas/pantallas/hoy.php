<?php

declare(strict_types=1);

/**
 * Pantalla «Hoy»: anillo del día, tira de la semana, pagos a cobrar y recontactos. Lógica en assets/js/panel.js,
 * pagos*.js, sas.js y seguimientos.js.
 */
?>
<section class="screen" x-show="activo === 'hoy'">
    <header class="navbar">
        <p class="subhead" x-text="fechaHoy"></p>
        <div class="navbar__large-row">
            <h1 class="navbar__large-title">Hoy</h1>
            <div class="navbar__acciones">
                <button
                    class="workspace-search"
                    type="button"
                    @click="activo = 'contactos'; $nextTick(() => $el.closest('.app-shell').querySelector('.search-field input')?.focus())"
                    aria-label="Buscar contacto"
                >
                    <i data-lucide="search"></i>
                    <span>Buscar contacto</span>
                </button>
                <button class="workspace-create" type="button" @click="abrirNuevoContacto()">
                    <i data-lucide="plus"></i>
                    <span>Nueva consulta</span>
                </button>
            </div>
        </div>
    </header>
    <div class="screen__body">
        <p class="error-inline" x-show="errorPanel" x-text="errorPanel"></p>
        <div class="hoy-resumen" x-show="!errorPanel">
            <div class="anillo-dia anillo-dia--pastel">
                <div class="anillo-dia__grafico" aria-label="Progreso de recontactos de hoy">
                    <svg viewBox="0 0 110 110" aria-hidden="true">
                        <circle class="anillo-dia__pista" cx="55" cy="55" r="46" fill="none" stroke-width="10"></circle>
                        <circle
                            class="anillo-dia__progreso"
                            cx="55"
                            cy="55"
                            r="46"
                            fill="none"
                            stroke-width="10"
                            stroke-dasharray="289.03"
                            :stroke-dashoffset="offsetAnillo()"
                        ></circle>
                    </svg>
                    <span class="anillo-dia__numero">
                        <strong
                            class="tabular"
                            x-text="`${panel.anillo?.hechos_hoy || 0} / ${panel.anillo?.agendados_hoy || 0}`"
                        ></strong>
                        <span class="caption">hechos</span>
                    </span>
                </div>
                <div class="anillo-dia__filas">
                    <p class="anillo-dia__etiqueta">Anillo del día</p>
                    <p class="anillo-dia__mensaje" x-text="mensajeAnillo()"></p>
                    <p class="anillo-dia__ayuda">Registrá un recontacto y el anillo avanza.</p>
                </div>
            </div>
            <div class="semana-card">
                <div class="semana-card__cabecera">
                    <p class="semana-card__titulo">Esta semana</p>
                    <button type="button" class="semana-card__ver" @click="activo = 'agenda'">Ver calendario</button>
                </div>
                <div class="semana-card__dias">
                    <template x-for="dia in semanaConLabel()" :key="dia.iso">
                        <button
                            type="button"
                            class="semana-dia"
                            :class="dia.estado ? `semana-dia--${dia.estado}` : ''"
                            @click="irACalendarioDia(dia.iso)"
                        >
                            <span class="semana-dia__letra" x-text="dia.letra"></span>
                            <span class="semana-dia__num tabular" x-text="dia.numero"></span>
                            <span class="semana-dia__punto"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
        <div class="stat-card-grid" x-show="!errorPanel">
            <div class="stat-card stat-card--vencido">
                <div class="stat-card__cabecera">
                    <span class="stat-card__etiqueta">Vencidos</span>
                    <span class="stat-card__icono"><i data-lucide="alert-circle"></i></span>
                </div>
                <strong class="stat-card__numero tabular" x-text="panel.vencidos?.length || 0"></strong>
                <p class="stat-card__ayuda">Necesitan atención</p>
            </div>
            <div class="stat-card stat-card--hoy">
                <div class="stat-card__cabecera">
                    <span class="stat-card__etiqueta">Para hoy</span>
                    <span class="stat-card__icono"><i data-lucide="clock"></i></span>
                </div>
                <strong class="stat-card__numero tabular" x-text="panel.hoy?.length || 0"></strong>
                <p class="stat-card__ayuda">Agendados para hoy</p>
            </div>
            <div class="stat-card stat-card--proximo">
                <div class="stat-card__cabecera">
                    <span class="stat-card__etiqueta">Próximos días</span>
                    <span class="stat-card__icono"><i data-lucide="calendar-days"></i></span>
                </div>
                <strong class="stat-card__numero tabular" x-text="Object.values(panel.proximos || {}).flat().length"></strong>
                <p class="stat-card__ayuda">Esta semana</p>
            </div>
            <div class="stat-card stat-card--concreto">
                <div class="stat-card__cabecera">
                    <span class="stat-card__etiqueta">Concretaron</span>
                    <span class="stat-card__icono"><i data-lucide="check"></i></span>
                </div>
                <strong class="stat-card__numero tabular" x-text="panel.contadores?.cerrados_mes?.concreto || 0"></strong>
                <p class="stat-card__ayuda">Este mes</p>
            </div>
        </div>
        <section class="hoy-pagos" x-show="!errorPanel && panel.compromisos?.length">
            <div class="seccion-cabecera">
                <h2>Pagos a cobrar</h2>
                <span
                    class="hoy-pagos__total tabular"
                    x-text="`${formatoMonto(totalPagosHoy())} · ${cantidadPagosTexto(panel.compromisos.length)}`"
                ></span>
            </div>
            <div class="card-pastel-grid">
                <template x-for="pago in panel.compromisos" :key="`hp-${pago.id}`">
                    <?php $montoExpresion = 'formatoMonto(pago.monto)'; require __DIR__ . '/../parciales/tarjeta_pago.php'; ?>
                </template>
            </div>
        </section>
        <div class="seccion-cabecera">
            <h2>Recontactos</h2>
            <div class="filtro-pills" role="group" aria-label="Filtrar recontactos">
                <button
                    class="filtro-pill"
                    type="button"
                    :aria-selected="filtroRecontactos === ''"
                    @click="filtroRecontactos = ''"
                >
                    Todos
                </button>
                <button
                    class="filtro-pill"
                    type="button"
                    :aria-selected="filtroRecontactos === 'vencido'"
                    @click="filtroRecontactos = 'vencido'"
                >
                    Vencidos
                </button>
                <button
                    class="filtro-pill"
                    type="button"
                    :aria-selected="filtroRecontactos === 'hoy'"
                    @click="filtroRecontactos = 'hoy'"
                >
                    Para hoy
                </button>
                <button
                    class="filtro-pill"
                    type="button"
                    :aria-selected="filtroRecontactos === 'proximo'"
                    @click="filtroRecontactos = 'proximo'"
                >
                    Próximos
                </button>
            </div>
        </div>
        <section class="card-pastel-grid" aria-label="Recontactos">
            <template
                x-for="item in tarjetasHoy().filter((i) => !filtroRecontactos || i.estado === filtroRecontactos)"
                :key="`t-${item.estado}-${item.contacto.id}`"
            >
                <article
                    class="card-pastel"
                    :class="{'card-pastel--vencido': item.estado === 'vencido', 'card-pastel--hoy': item.estado === 'hoy', 'card-pastel--proximo': item.estado === 'proximo'}"
                >
                    <div class="card-pastel__head">
                        <button type="button" class="card-pastel__persona" @click="abrirFicha(item.contacto.id)">
                            <span
                                class="avatar"
                                :style="`background:${colorAvatar(item.contacto.nombre)}`"
                                x-text="iniciales(item.contacto.nombre)"
                            ></span>
                            <span class="card-pastel__cuerpo">
                                <span class="card-pastel__nombre" x-text="item.contacto.nombre"></span>
                                <span
                                    class="card-pastel__detalle"
                                    x-text="[item.contacto.producto_interes, item.contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"
                                ></span>
                            </span>
                        </button>
                        <span
                            class="chip-estado"
                            x-text="{vencido:'Vencido', hoy:'Para hoy', proximo:'Próximo', sinfecha:'Sin fecha'}[item.estado]"
                        ></span>
                    </div>
                    <p class="card-pastel__fecha tabular" x-show="item.estado !== 'sinfecha'">
                        <i data-lucide="clock"></i>
                        <span x-text="fechaCorta(item.contacto.proximo_contacto)"></span>
                    </p>
                    <p
                        class="card-pastel__autor"
                        x-text="`Cargó: ${item.contacto.creado_por_nombre || 'Sin registrar'}`"
                    ></p>
                    <div class="card-pastel__acciones">
                        <a
                            class="quick-action-card quick-action-card--llamar"
                            :href="`tel:+${item.contacto.celular_norm}`"
                        >
                            <i data-lucide="phone"></i>
                            Llamar
                        </a>
                        <a
                            class="quick-action-card quick-action-card--whatsapp"
                            target="_blank"
                            rel="noopener"
                            :href="`https://wa.me/${item.contacto.celular_norm}`"
                        >
                            <i data-lucide="message-circle"></i>
                            WhatsApp
                        </a>
                        <button
                            type="button"
                            class="quick-action-card quick-action-card--posponer"
                            x-show="item.estado !== 'sinfecha'"
                            @click="posponerContacto(item.contacto, item.estado === 'hoy' ? 'hora' : 'manana')"
                        >
                            <i data-lucide="clock-3"></i>
                            Posponer
                        </button>
                        <button
                            type="button"
                            class="quick-action-card quick-action-card--nota"
                            x-show="item.estado === 'sinfecha'"
                            @click="abrirNotaSeguimiento(item.contacto)"
                        >
                            <i data-lucide="sticky-note"></i>
                            Nota
                        </button>
                    </div>
                </article>
            </template>
        </section>
        <div class="empty-state" x-show="!cargandoPanel && !tarjetasHoy().length && !panel.compromisos?.length">
            <i data-lucide="calendar-check"></i>
            <p class="body-text">No tenés recontactos para hoy</p>
            <button class="empty-state__boton" type="button" @click="abrirNuevoContacto()">Cargar consulta</button>
        </div>
        <div class="month-counts" x-show="panel.contadores?.abiertos">
            <span><strong class="tabular" x-text="panel.contadores?.abiertos || 0"></strong> abiertos</span>
            <span>
                <strong class="tabular" x-text="panel.contadores?.cerrados_mes?.concreto || 0"></strong>
                concretados este mes
            </span>
        </div>
    </div>
</section>
