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
        <div class="stat-card-grid stat-card-grid--montos">
            <div class="stat-card stat-card--vencido">
                <div class="stat-card__cabecera">
                    <span class="stat-card__etiqueta">Vencidos</span>
                    <span class="stat-card__icono"><i data-lucide="alert-circle"></i></span>
                </div>
                <strong class="stat-card__numero tabular" x-text="formatoMonto(resumenVisiblePagos().vencidos.monto)"></strong>
                <p class="stat-card__ayuda" x-text="cantidadPagosTexto(resumenVisiblePagos().vencidos.cantidad)"></p>
            </div>
            <div class="stat-card stat-card--hoy">
                <div class="stat-card__cabecera">
                    <span class="stat-card__etiqueta">Para hoy</span>
                    <span class="stat-card__icono"><i data-lucide="clock"></i></span>
                </div>
                <strong class="stat-card__numero tabular" x-text="formatoMonto(resumenVisiblePagos().hoy.monto)"></strong>
                <p class="stat-card__ayuda" x-text="cantidadPagosTexto(resumenVisiblePagos().hoy.cantidad)"></p>
            </div>
            <div class="stat-card stat-card--proximo">
                <div class="stat-card__cabecera">
                    <span class="stat-card__etiqueta">Próximos 7 días</span>
                    <span class="stat-card__icono"><i data-lucide="calendar-days"></i></span>
                </div>
                <strong class="stat-card__numero tabular" x-text="formatoMonto(resumenVisiblePagos().proximos.monto)"></strong>
                <p class="stat-card__ayuda" x-text="cantidadPagosTexto(resumenVisiblePagos().proximos.cantidad)"></p>
            </div>
            <div class="stat-card stat-card--concreto">
                <div class="stat-card__cabecera">
                    <span class="stat-card__etiqueta">Cobrado</span>
                    <span class="stat-card__icono"><i data-lucide="check"></i></span>
                </div>
                <strong class="stat-card__numero tabular" x-text="formatoMonto(resumenVisiblePagos().cobrado_mes)"></strong>
                <p class="stat-card__ayuda">Este mes</p>
            </div>
        </div>
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
        <div class="filtro-pills pagos-filtros" role="tablist" aria-label="Estado de los compromisos">
            <template
                x-for="opcion in [{id: 'pendientes', texto: 'Pendientes'}, {id: 'vencidos', texto: 'Vencidos'}, {id: 'hoy', texto: 'Para hoy'}, {id: 'proximos', texto: 'Próximos'}, {id: 'pagados', texto: 'Pagados'}, {id: 'incumplidos', texto: 'Incumplidos'}, {id: 'reprogramados', texto: 'Reprogramados'}, {id: 'todos', texto: 'Todos'}]"
                :key="opcion.id"
            >
                <button
                    class="filtro-pill"
                    type="button"
                    role="tab"
                    :aria-selected="filtroPagos === opcion.id"
                    @click="filtrarPagos(opcion.id)"
                    x-text="opcion.texto"
                ></button>
            </template>
        </div>
        <div class="pagos-filtros-extra">
            <div class="filtro-pills" role="group" aria-label="Fecha del pago">
                <template x-for="rango in rangosPagos" :key="rango.id">
                    <button
                        class="filtro-pill"
                        type="button"
                        :aria-pressed="pagosRango === rango.id"
                        @click="elegirRangoPagos(rango.id)"
                        x-text="rango.texto"
                    ></button>
                </template>
            </div>
            <label class="pagos-campo">
                <span class="pagos-campo__rotulo">Cargó</span>
                <select x-model="pagosCreadoPor" @change="cargarPagos(true)">
                    <option value="">Todos</option>
                    <option value="yo">Yo</option>
                    <template x-for="usuario in otrosUsuariosPagos()" :key="usuario.filtro">
                        <option
                            :value="usuario.filtro"
                            x-text="usuario.usuario_id === null ? `${usuario.nombre} (cuenta eliminada)` : usuario.nombre"
                        ></option>
                    </template>
                </select>
            </label>
            <label class="pagos-campo">
                <span class="pagos-campo__rotulo">Concepto</span>
                <select x-model="pagosConcepto" @change="cargarPagos(true)">
                    <option value="">Todos</option>
                    <template x-for="opcion in opcionesConceptoFiltro()" :key="opcion.valor">
                        <option :value="opcion.valor" x-text="opcion.texto"></option>
                    </template>
                </select>
            </label>
        </div>
        <div class="pagos-rango" x-show="pagosRango === 'elegir'" x-cloak>
            <label class="pagos-campo">
                <span class="pagos-campo__rotulo">Desde</span>
                <input
                    type="date"
                    x-model="pagosDesde"
                    @click="abrirSelectorFecha($event.target)"
                    @change="aplicarRangoPagos()"
                >
            </label>
            <label class="pagos-campo">
                <span class="pagos-campo__rotulo">Hasta</span>
                <input
                    type="date"
                    x-model="pagosHasta"
                    @click="abrirSelectorFecha($event.target)"
                    @change="aplicarRangoPagos()"
                >
            </label>
            <p class="error-inline" role="alert" x-show="errorRangoPagos" x-text="errorRangoPagos"></p>
        </div>
        <div class="pagos-chips" role="group" aria-label="Filtros activos" x-show="chipsPagos().length" x-cloak>
            <template x-for="chip in chipsPagos()" :key="chip.clave">
                <button
                    class="chip-filtro"
                    type="button"
                    @click="quitarFiltroPago(chip.clave)"
                    :aria-label="`Quitar filtro: ${chip.texto}`"
                >
                    <span x-text="chip.texto"></span>
                    <i data-lucide="x"></i>
                </button>
            </template>
            <button class="btn-texto" type="button" x-show="chipsPagos().length > 1" @click="limpiarFiltrosPagos()">
                Limpiar todo
            </button>
        </div>
        <section class="pagos-usuarios" x-show="pagosPorUsuario.length" x-cloak>
            <button
                class="pagos-usuarios__cabecera"
                type="button"
                :aria-expanded="pagosUsuariosAbierto"
                aria-controls="pagos-usuarios-cuerpo"
                @click="pagosUsuariosAbierto = !pagosUsuariosAbierto"
            >
                <i data-lucide="users"></i>
                <span class="pagos-usuarios__titulo">Por usuario</span>
                <span class="pagos-usuarios__cuenta tabular" x-text="pagosPorUsuario.length"></span>
                <i data-lucide="chevron-down" class="pagos-usuarios__flecha"></i>
            </button>
            <div class="pagos-usuarios__cuerpo" id="pagos-usuarios-cuerpo" x-show="pagosUsuariosAbierto" x-cloak>
                <p class="pagos-usuarios__ayuda">
                    Tocá un usuario para ver solo lo que cargó. Cumplimiento: lo cobrado sobre lo prometido para fechas
                    de este mes que ya llegaron.
                </p>
                <template x-for="usuario in pagosPorUsuario" :key="usuario.filtro">
                    <button
                        class="pagos-usuario"
                        type="button"
                        :aria-pressed="usuarioPagosActivo(usuario)"
                        @click="filtrarPorUsuarioPagos(usuario)"
                    >
                        <span class="pagos-usuario__nombre">
                            <span x-text="usuario.nombre"></span>
                            <small x-show="usuario.usuario_id === null">cuenta eliminada</small>
                        </span>
                        <span class="pagos-usuario__dato">
                            <span class="pagos-usuario__rotulo">Pendientes</span>
                            <strong
                                class="tabular"
                                x-text="formatoMontoCompacto(usuario.pendientes.monto)"
                                :title="formatoMonto(usuario.pendientes.monto)"
                            ></strong>
                            <span class="pagos-usuario__sub" x-text="cantidadPagosTexto(usuario.pendientes.cantidad)"></span>
                        </span>
                        <span
                            class="pagos-usuario__dato"
                            :class="{'pagos-usuario__dato--alerta': usuario.vencidos.cantidad > 0}"
                        >
                            <span class="pagos-usuario__rotulo">Vencido</span>
                            <strong
                                class="tabular"
                                x-text="formatoMontoCompacto(usuario.vencidos.monto)"
                                :title="formatoMonto(usuario.vencidos.monto)"
                            ></strong>
                            <span class="pagos-usuario__sub" x-text="cantidadPagosTexto(usuario.vencidos.cantidad)"></span>
                        </span>
                        <span class="pagos-usuario__dato">
                            <span class="pagos-usuario__rotulo">Cobrado en el mes</span>
                            <strong
                                class="tabular"
                                x-text="formatoMontoCompacto(usuario.cobrado_mes)"
                                :title="formatoMonto(usuario.cobrado_mes)"
                            ></strong>
                        </span>
                        <span class="pagos-usuario__dato">
                            <span class="pagos-usuario__rotulo">Cumplimiento</span>
                            <strong
                                class="tabular"
                                x-text="usuario.cumplimiento_mes === null ? 'Sin datos' : `${usuario.cumplimiento_mes}%`"
                            ></strong>
                            <span class="pagos-barra-fina" aria-hidden="true">
                                <span :style="`--valor:${Math.min(usuario.cumplimiento_mes || 0, 100)}%`"></span>
                            </span>
                        </span>
                    </button>
                </template>
            </div>
        </section>
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
        <template x-if="vistaPagosEfectiva() === 'tabla'">
            <div class="pagos-tabla-envoltorio" role="region" aria-label="Tabla de compromisos de pago" tabindex="0">
                <table class="pagos-tabla">
                    <caption class="sr-only">
                        Compromisos de pago. Se pueden ordenar las columnas Fecha, Cliente, Monto, Atraso y Cargó.
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col" :aria-sort="ariaOrdenPagos('fecha')">
                                <button type="button" class="pagos-tabla__orden" @click="ordenarPagosPor('fecha')">
                                    Fecha
                                </button>
                            </th>
                            <th scope="col" :aria-sort="ariaOrdenPagos('cliente')">
                                <button type="button" class="pagos-tabla__orden" @click="ordenarPagosPor('cliente')">
                                    Cliente
                                </button>
                            </th>
                            <th scope="col">Concepto</th>
                            <th scope="col" class="pagos-tabla__num" :aria-sort="ariaOrdenPagos('monto')">
                                <button type="button" class="pagos-tabla__orden" @click="ordenarPagosPor('monto')">
                                    Monto
                                </button>
                            </th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="pagos-tabla__num" :aria-sort="ariaOrdenPagos('atraso')">
                                <button type="button" class="pagos-tabla__orden" @click="ordenarPagosPor('atraso')">
                                    Atraso
                                </button>
                            </th>
                            <th scope="col" :aria-sort="ariaOrdenPagos('creado_por')">
                                <button type="button" class="pagos-tabla__orden" @click="ordenarPagosPor('creado_por')">
                                    Cargó
                                </button>
                            </th>
                            <th scope="col">Resolvió</th>
                            <th scope="col"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <template x-for="pago in pagos" :key="`pt-${pago.id}`">
                        <tbody
                            class="pagos-tabla__grupo"
                            :class="{'pagos-tabla__grupo--con-sas': chipSas(pago) || sugerenciaSas(pago)}"
                        >
                            <tr :class="`pagos-tabla__fila--${pago.situacion}`">
                                <td class="tabular" x-text="fechaTablaPago(pago)"></td>
                                <td>
                                    <button
                                        type="button"
                                        class="pagos-tabla__cliente"
                                        @click="abrirFicha(pago.contacto_id)"
                                        x-text="pago.contacto_nombre"
                                    ></button>
                                    <small
                                        class="pagos-tabla__aviso"
                                        x-show="avisoPagoTexto(pago)"
                                        x-text="avisoPagoTexto(pago)"
                                    ></small>
                                </td>
                                <td x-text="pago.concepto || '—'"></td>
                                <td class="pagos-tabla__num tabular" x-text="montoPagoTexto(pago)"></td>
                                <td>
                                    <span class="chip-estado chip-estado--estado" x-text="etiquetaPago(pago)"></span>
                                </td>
                                <td class="pagos-tabla__num tabular" x-text="atrasoTablaPago(pago)"></td>
                                <td x-text="pago.creado_por_nombre || 'Sin registrar'"></td>
                                <td x-text="pago.resuelto_por_nombre || '—'"></td>
                                <td>
                                    <?php $variante = 'tabla'; require __DIR__ . '/../parciales/pago_acciones.php'; ?>
                                </td>
                            </tr>
                            <tr
                                class="pagos-tabla__sas"
                                :class="`pagos-tabla__fila--${pago.situacion}`"
                                x-show="chipSas(pago) || sugerenciaSas(pago)"
                            >
                                <td colspan="9">
                                    <div class="pago-fila__sas">
                                        <?php require __DIR__ . '/../parciales/pago_sas.php'; ?>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </template>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="3" x-text="`Total · ${cantidadPagosTexto(totalesPagos.cantidad)}`"></th>
                            <td class="pagos-tabla__num tabular" x-text="formatoMonto(totalesPagos.monto)"></td>
                            <td
                                colspan="2"
                                class="tabular"
                                x-text="Number(totalesPagos.pagado) > 0 ? `Cobrado ${formatoMonto(totalesPagos.pagado)}` : ''"
                            ></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </template>
        <template x-if="vistaPagosEfectiva() === 'tarjetas'">
            <section class="card-pastel-grid pagos-tarjetas" aria-label="Compromisos de pago">
                <template x-for="pago in pagos" :key="`pago-${pago.id}`">
                    <article class="card-pastel" :class="clasePago(pago)">
                        <div class="card-pastel__head">
                            <button type="button" class="card-pastel__persona" @click="abrirFicha(pago.contacto_id)">
                                <span
                                    class="avatar"
                                    :style="`background:${colorAvatar(pago.contacto_nombre)}`"
                                    x-text="iniciales(pago.contacto_nombre)"
                                ></span>
                                <span class="card-pastel__cuerpo">
                                    <span class="card-pastel__nombre" x-text="pago.contacto_nombre"></span>
                                    <span class="card-pastel__detalle" x-text="pago.concepto || 'Sin concepto'"></span>
                                </span>
                            </button>
                            <span class="chip-estado chip-estado--estado" x-text="etiquetaPago(pago)"></span>
                        </div>
                        <p class="card-pastel__monto tabular" x-text="montoPagoTexto(pago)"></p>
                        <p class="card-pastel__fecha tabular">
                            <i data-lucide="calendar-days"></i>
                            <span x-text="fechaPago(pago)"></span>
                        </p>
                        <p class="card-pastel__autor" x-text="`Cargó: ${pago.creado_por_nombre || 'Sin registrar'}`"></p>
                        <p class="card-pastel__aviso" x-show="avisoPagoTexto(pago)" x-text="avisoPagoTexto(pago)"></p>
<?php require __DIR__ . '/../parciales/pago_sas.php'; ?>
<?php $variante = 'texto'; require __DIR__ . '/../parciales/pago_acciones.php'; ?>
                    </article>
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
