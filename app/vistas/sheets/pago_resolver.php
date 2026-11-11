<?php

declare(strict_types=1);

/**
 * Hoja «Registrar pago» completa (pagó / parcial / no pagó, con nota y saldo). Lógica en assets/js/pagos.js.
 */
?>
<div
    class="sheet-backdrop"
    x-show="sheetPagoResolver"
    x-transition.opacity
    @keydown.escape.window="sheetPagoResolver && cerrarPagoResolver()"
    @click.self="cerrarPagoResolver()"
    x-cloak
>
    <form class="sheet sheet--media" @submit.prevent="guardarPagoResolver()">
        <div class="sheet__grabber"></div>
        <header class="sheet__header">
            <button class="btn-texto" type="button" @click="cerrarPagoResolver()">Cancelar</button>
            <h2 class="sheet__title">Registrar pago</h2>
            <button
                class="btn-texto btn-texto--negrita"
                type="submit"
                :disabled="guardandoPagoResolver || !pagoResolverValido()"
                x-text="guardandoPagoResolver ? 'Guardando…' : 'Guardar'"
            ></button>
        </header>
        <div class="sheet__body">
            <p
                class="sheet-contact-name"
                x-text="pagoResolver ? `${pagoResolver.contacto_nombre} · ${formatoMonto(pagoResolver.monto)}` : ''"
            ></p>
            <div class="resultado-grid resultado-grid--tres" role="tablist" aria-label="Qué pasó con el pago">
                <template
                    x-for="opcion in [{id: 'pagado', texto: 'Pagó'}, {id: 'parcial', texto: 'Pago parcial'}, {id: 'incumplido', texto: 'No pagó'}]"
                    :key="opcion.id"
                >
                    <button
                        type="button"
                        class="resultado-opcion"
                        :class="`resultado-opcion--${opcion.id}`"
                        role="tab"
                        :aria-selected="pagoResolverForm.resultado === opcion.id"
                        @click="seleccionarResultadoPago(opcion.id)"
                        x-text="opcion.texto"
                    ></button>
                </template>
            </div>
            <div x-show="pagoResolverForm.resultado === 'parcial'">
                <p class="group__header footnote">cuánto recibiste</p>
                <div class="group form-group">
                    <label class="cell form-cell">
                        <span class="cell__label">Recibido</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            inputmode="decimal"
                            x-model="pagoResolverForm.monto_pagado"
                            placeholder="$ 0"
                        >
                    </label>
                </div>
                <p
                    class="group__footer footnote"
                    x-show="pagoResolverRecibido() > 0 && pagoResolverRecibido() < Number(pagoResolver?.monto)"
                    x-text="`Falta cobrar ${formatoMonto(pagoResolverSaldo())}`"
                ></p>
                <p class="error-inline" x-show="pagoResolverRecibido() >= Number(pagoResolver?.monto)">
                    Lo recibido tiene que ser menor al monto. Si pagó todo, elegí «Pagó».
                </p>
            </div>
            <div x-show="pagoResolverForm.resultado !== 'pagado'">
                <p
                    class="group__header footnote"
                    x-text="pagoResolverForm.resultado === 'parcial' ? 'agendar el saldo' : 'volver a agendar'"
                ></p>
                <div class="segmented" role="tablist" aria-label="Agendar otro pago">
                    <button
                        type="button"
                        class="segmented__option"
                        role="tab"
                        :aria-selected="!pagoResolverForm.agendar"
                        @click="pagoResolverForm.agendar = false"
                    >
                        No agendar
                    </button>
                    <button
                        type="button"
                        class="segmented__option"
                        role="tab"
                        :aria-selected="pagoResolverForm.agendar"
                        @click="pagoResolverForm.agendar = true"
                    >
                        Agendar
                    </button>
                </div>
                <div x-show="pagoResolverForm.agendar">
                    <div class="pills date-pills">
                        <button
                            class="pill"
                            type="button"
                            :aria-selected="pagosEsManana(pagoResolverForm.fecha)"
                            @click="asignarFechaPagoResolver(1)"
                        >
                            Mañana
                        </button>
                        <label
                            class="pill pill--input"
                            :aria-selected="Boolean(pagoResolverForm.fecha) && !pagosEsManana(pagoResolverForm.fecha)"
                        >
                            Elegir…
                            <input
                                type="date"
                                @click="abrirSelectorFecha($event.target)"
                                @change="elegirFechaPagoResolver($event.target.value)"
                                aria-label="Elegir la nueva fecha de pago"
                            >
                        </label>
                    </div>
                    <p
                        class="group__footer footnote"
                        x-show="pagoResolverForm.fecha"
                        x-text="fechaLarga(pagoResolverForm.fecha)"
                    ></p>
                    <div class="group form-group">
                        <label class="cell form-cell">
                            <span class="cell__label">Monto</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                inputmode="decimal"
                                x-model="pagoResolverForm.monto"
                                :placeholder="formatoMonto(pagoResolverSaldo())"
                            >
                        </label>
                    </div>
                </div>
            </div>
            <label class="note-field">
                <span class="footnote">nota (opcional)</span>
                <textarea
                    x-model.trim="pagoResolverForm.nota"
                    maxlength="2000"
                    rows="2"
                    placeholder="Cómo fue, qué quedó acordado"
                ></textarea>
            </label>
        </div>
    </form>
</div>
