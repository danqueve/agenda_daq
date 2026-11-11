<?php

declare(strict_types=1);

/**
 * Pantalla «Pagos»: totales, vistas Agenda / Tabla / Tarjetas, filtros y resumen por usuario. Lógica en
 * assets/js/pagos.js, pagos_rapido.js y sas.js.
 */
?>
<section class="screen" x-show="activo === 'pagos'">
    <header class="navbar">
        <p class="subhead tabular" x-text="`${resumenPagos.pendientes} pendientes`"></p>
        <div class="navbar__large-row">
            <h1 class="navbar__large-title">Pagos</h1>
            <div class="navbar__acciones">
                <label class="search-field search-field--header">
                    <i data-lucide="search"></i>
                    <input
                        type="search"
                        x-model="busquedaPagos"
                        @input.debounce.300ms="cargarPagos(true)"
                        placeholder="Nombre o concepto"
                        aria-label="Buscar compromisos de pago"
                    >
                </label>
                <button class="workspace-create" type="button" @click="abrirCompromisoNuevo()">
                    <i data-lucide="plus"></i>
                    <span>Nuevo compromiso</span>
                </button>
            </div>
        </div>
    </header>
    <div class="screen__body">
        <p class="error-inline" x-show="errorPagos" x-text="errorPagos"></p>
        <?php $resumen = 'resumenVisiblePagos()'; $cumplimiento = null; require __DIR__ . '/../parciales/cobranza_totales.php'; ?>
        <p class="pagos-nota" x-show="pagosResumenFiltrado" x-cloak>
            <i data-lucide="filter"></i>
            <span>Estos totales cuentan solo lo que cumple el filtro de fechas, de quién lo cargó y de concepto.</span>
            <button class="btn-texto" type="button" @click="limpiarFiltrosPagos()">Quitar filtros</button>
        </p>
        <div class="pagos-barra">
            <div class="segmented pagos-vistas" role="tablist" aria-label="Vista de los pagos">
                <button
                    type="button"
                    class="segmented__option"
                    role="tab"
                    :aria-selected="vistaPagosEfectiva() === 'agenda'"
                    @click="elegirVistaPagos('agenda')"
                >
                    <i data-lucide="list"></i>
                    Agenda
                </button>
                <button
                    type="button"
                    class="segmented__option pagos-vistas__tabla"
                    role="tab"
                    :aria-selected="vistaPagosEfectiva() === 'tabla'"
                    @click="elegirVistaPagos('tabla')"
                >
                    <i data-lucide="table-2"></i>Tabla
                </button>
                <button
                    type="button"
                    class="segmented__option"
                    role="tab"
                    :aria-selected="vistaPagosEfectiva() === 'tarjetas'"
                    @click="elegirVistaPagos('tarjetas')"
                >
                    <i data-lucide="layout-grid"></i>
                    Tarjetas
                </button>
            </div>
            <a class="pagos-csv" :href="urlCsvPagos()" download><i data-lucide="download"></i>Exportar CSV</a>
        </div>
        <?php require __DIR__ . '/../parciales/pagos_filtros.php'; ?>
        <?php require __DIR__ . '/../parciales/pagos_por_usuario.php'; ?>
        <template x-if="vistaPagosEfectiva() === 'agenda'">
            <div class="pagos-agenda">
                <template x-for="grupo in gruposPagos()" :key="grupo.dia">
                    <section class="pagos-dia" :class="`pagos-dia--${grupo.tipo}`">
                        <header class="pagos-dia__cabecera">
                            <h2 class="pagos-dia__titulo" x-text="grupo.titulo"></h2>
                            <p class="pagos-dia__resumen tabular">
                                <span x-text="cantidadPagosTexto(grupo.cantidad)"></span>
                                <strong x-text="formatoMontoCompacto(grupo.monto)" :title="formatoMonto(grupo.monto)"></strong>
                            </p>
                        </header>
                        <div class="pagos-dia__lista">
                            <template x-for="pago in grupo.items" :key="`pf-${pago.id}`">
                                <article class="pago-fila" :class="clasePagoFila(pago)">
                                    <button
                                        type="button"
                                        class="pago-fila__persona"
                                        @click="abrirFicha(pago.contacto_id)"
                                    >
                                        <span class="pago-fila__nombre" x-text="pago.contacto_nombre"></span>
                                        <span class="pago-fila__detalle" x-text="detallePagoFila(pago)"></span>
                                        <span
                                            class="pago-fila__aviso"
                                            x-show="avisoPagoTexto(pago)"
                                            x-text="avisoPagoTexto(pago)"
                                        ></span>
                                    </button>
                                    <div class="pago-fila__sas" x-show="chipSas(pago) || sugerenciaSas(pago)">
                                        <?php require __DIR__ . '/../parciales/pago_sas.php'; ?>
                                    </div>
                                    <strong class="pago-fila__monto tabular" x-text="montoPagoTexto(pago)"></strong>
                                    <span
                                        class="chip-estado chip-estado--estado pago-fila__estado"
                                        x-text="estadoAtrasoPago(pago)"
                                    ></span>
                                    <?php $variante = 'fila'; require __DIR__ . '/../parciales/pago_acciones.php'; ?>
                                </article>
                            </template>
                        </div>
                    </section>
                </template>
            </div>
        </template>
        <?php require __DIR__ . '/../parciales/pagos_tabla.php'; ?>
        <template x-if="vistaPagosEfectiva() === 'tarjetas'">
            <section class="card-pastel-grid pagos-tarjetas" aria-label="Compromisos de pago">
                <template x-for="pago in pagos" :key="`pago-${pago.id}`">
                    <?php $montoExpresion = 'montoPagoTexto(pago)'; require __DIR__ . '/../parciales/tarjeta_pago.php'; ?>
                </template>
            </section>
        </template>
        <div class="empty-state" x-show="!cargandoPagos && !pagos.length && !errorPagos">
            <i data-lucide="hand-coins"></i>
            <p
                class="body-text"
                x-text="chipsPagos().length || filtroPagos !== 'pendientes' ? 'No encontramos compromisos con esos filtros' : 'Todavía no hay compromisos de pago'"
            ></p>
            <button
                class="empty-state__boton"
                type="button"
                @click="limpiarFiltrosPagos()"
                x-show="chipsPagos().length"
            >
                Quitar filtros
            </button>
            <button
                class="empty-state__boton"
                type="button"
                @click="abrirCompromisoNuevo()"
                x-show="!chipsPagos().length && filtroPagos === 'pendientes'"
            >
                Cargar compromiso
            </button>
        </div>
        <p
            class="pagos-conteo tabular"
            x-show="pagos.length && totalesPagos.cantidad > pagos.length"
            x-text="`Mostrando ${pagos.length} de ${totalesPagos.cantidad}`"
        ></p>
        <button
            class="btn-texto contacts-load-more"
            type="button"
            x-show="hayMasPagos"
            @click="cargarPagos()"
            :disabled="cargandoPagos"
            x-text="cargandoPagos ? 'Cargando…' : 'Cargar más compromisos'"
        ></button>
    </div>
</section>
