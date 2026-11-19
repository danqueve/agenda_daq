<?php

declare(strict_types=1);

/**
 * Pantalla «Hoy» con el diseño 1b «Cola y detalle»: la cola de cobro (con el filtro Todos | Míos) y el compromiso
 * elegido o el siguiente a cobrar (app/vistas/parciales/hoy_cola.php); después, plegados como segundo plano, el anillo
 * del día y los recontactos. Lógica en assets/js/cobranza.js, panel.js, pagos*.js, sas.js y seguimientos.js.
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
                <button class="workspace-search workspace-search--accion" type="button" @click="abrirNuevoContacto()" aria-label="Nueva consulta">
                    <i data-lucide="user-plus"></i>
                    <span>Nueva consulta</span>
                </button>
                <button class="workspace-create" type="button" @click="abrirCompromisoNuevo()" aria-label="Nuevo compromiso">
                    <i data-lucide="plus"></i>
                    <span>Nuevo compromiso</span>
                </button>
            </div>
        </div>
    </header>
    <div class="screen__body">
        <p class="error-inline" x-show="errorPanel" x-text="errorPanel"></p>
        <div class="hoy-cobranza" x-show="!errorPanel">
            <?php require __DIR__ . '/../parciales/hoy_cola.php'; ?>
        </div>
        <section class="hoy-recontactos" x-data="{ recontactosAbiertos: false }" aria-labelledby="hoy-recontactos-titulo">
            <button
                type="button"
                class="hoy-recontactos__toggle"
                :aria-expanded="recontactosAbiertos"
                aria-controls="hoy-recontactos-cuerpo"
                @click="recontactosAbiertos = !recontactosAbiertos; refrescarIconos()"
            >
                <span class="hoy-recontactos__titulo" id="hoy-recontactos-titulo">Recontactos</span>
                <span class="hoy-recontactos__conteo tabular" x-text="conteoRecontactos('')"></span>
                <span class="hoy-recontactos__ayuda">Consultas para volver a llamar</span>
                <i data-lucide="chevron-down" aria-hidden="true"></i>
            </button>
            <div id="hoy-recontactos-cuerpo" x-show="recontactosAbiertos" x-cloak>
                <div class="seccion-cabecera">
                    <div class="filtro-pills" role="group" aria-label="Filtrar recontactos">
                        <template
                            x-for="opcion in [{id: '', texto: 'Todos'}, {id: 'vencido', texto: 'Vencidos'}, {id: 'hoy', texto: 'Para hoy'}, {id: 'proximo', texto: 'Próximos'}]"
                            :key="opcion.id"
                        >
                            <button
                                class="filtro-pill"
                                type="button"
                                :aria-selected="filtroRecontactos === opcion.id"
                                @click="filtroRecontactos = opcion.id"
                                x-text="`${opcion.texto} ${conteoRecontactos(opcion.id)}`"
                            ></button>
                        </template>
                    </div>
                </div>
                <div class="hoy-anillo" x-show="!errorPanel">
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
                <div class="empty-state" x-show="!cargandoPanel && !tarjetasHoy().length">
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
    </div>
</section>
