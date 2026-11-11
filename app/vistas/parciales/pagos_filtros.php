<?php

declare(strict_types=1);

/**
 * Filtros de la pantalla Pagos: estado, fecha, «Cargó», «Concepto», rango personalizado y chips de filtros activos.
 * Lógica en assets/js/pagos.js.
 */
?>
<div class="filtro-pills pagos-filtros" role="tablist" aria-label="Estado de los compromisos">
    <template
        x-for="opcion in [
            {id: 'pendientes', texto: 'Pendientes'},
            {id: 'vencidos', texto: 'Vencidos'},
            {id: 'hoy', texto: 'Para hoy'},
            {id: 'proximos', texto: 'Próximos'},
            {id: 'pagados', texto: 'Pagados'},
            {id: 'incumplidos', texto: 'Incumplidos'},
            {id: 'reprogramados', texto: 'Reprogramados'},
            {id: 'todos', texto: 'Todos'},
        ]"
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
