<?php

declare(strict_types=1);

/**
 * Hoja compacta de las acciones rápidas de un compromiso (Parcial, Reprogramar y Más). Lógica en
 * assets/js/pagos_rapido.js.
 */
?>

<div
    class="sheet-backdrop"
    x-show="sheetPagoRapido"
    x-transition.opacity
    @keydown.escape.window="sheetPagoRapido && cerrarPagoRapido()"
    @click.self="cerrarPagoRapido()"
    x-cloak
>
    <section
        class="sheet sheet--media sheet--compacta"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pago-rapido-titulo"
    >
        <div class="sheet__grabber"></div>
        <header class="sheet__header">
            <button class="btn-texto" type="button" @click="cerrarPagoRapido()">Cancelar</button>
            <h2 class="sheet__title" id="pago-rapido-titulo" x-text="pagoRapidoTitulo()"></h2>
            <span aria-hidden="true"></span>
        </header>
        <div class="sheet__body">
            <p
                class="sheet-contact-name"
                x-text="pagoRapido ? `${pagoRapido.contacto_nombre} · ${formatoMonto(pagoRapido.monto)}` : ''"
            ></p>
            <p
                class="sheet-contact-sub"
                x-show="pagoRapido?.cobrador_nombre"
                x-text="`Cobrador: ${pagoRapido?.cobrador_nombre}`"
            ></p>
            <div class="pago-rapido-opciones" x-show="pagoRapidoModo === 'mas'">
                <button
                    type="button"
                    class="pago-rapido-opcion"
                    :disabled="pagoRapidoOcupado"
                    @click="pagoRapidoNoPago()"
                >
                    <i data-lucide="circle-x"></i>
                    <span>
                        <strong>No pagó</strong><small>Queda como incumplido y baja el cumplimiento. Si avisó que paga otro día, usá Reprogramar.</small>
                    </span>
                </button>
                <button type="button" class="pago-rapido-opcion" @click="pagoRapidoDetalle()">
                    <i data-lucide="square-pen"></i>
                    <span>
                        <strong>Abrir detalle</strong><small>El formulario completo, con nota y opción de agendar un saldo.</small>
                    </span>
                </button>
            </div>
            <form x-show="pagoRapidoModo === 'parcial' && pagoRapidoPaso === 1" @submit.prevent="pagoRapidoSeguir()">
                <div class="group form-group">
                    <label class="cell form-cell">
                        <span class="cell__label">Recibido</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            inputmode="decimal"
                            x-ref="pagoRapidoRecibido"
                            x-model="pagoRapidoForm.recibido"
                            placeholder="Monto que pagó"
                            required
                        >
                    </label>
                </div>
                <p
                    class="group__footer footnote"
                    x-show="pagoRapidoRecibidoValido()"
                    x-text="`Falta cobrar ${formatoMonto(pagoRapidoSaldo())}`"
                ></p>
                <p
                    class="error-inline"
                    x-show="pagoRapidoRecibido() >= Number(pagoRapido?.monto || 0) && pagoRapidoRecibido() > 0"
                >
                    Lo recibido tiene que ser menor al monto. Si pagó todo, usá «Pagó».
                </p>
                <button class="btn-principal" type="submit" :disabled="!pagoRapidoRecibidoValido()">Continuar</button>
            </form>
            <div x-show="(pagoRapidoModo === 'parcial' && pagoRapidoPaso === 2) || pagoRapidoModo === 'reprogramar'">
                <p
                    class="group__header footnote"
                    x-text="pagoRapidoModo === 'parcial' ? `¿agendás el saldo de ${formatoMonto(pagoRapidoSaldo())}?` : '¿para qué día?'"
                ></p>
                <div class="pills date-pills">
                    <button
                        class="pill"
                        type="button"
                        :aria-selected="pagoRapidoDiaElegido(1)"
                        @click="pagoRapidoFechaDias(1)"
                    >
                        Mañana
                    </button>
                    <button
                        class="pill"
                        type="button"
                        :aria-selected="pagoRapidoDiaElegido(3)"
                        @click="pagoRapidoFechaDias(3)"
                    >
                        En 3 días
                    </button>
                    <button
                        class="pill"
                        type="button"
                        :aria-selected="pagoRapidoDiaElegido(7)"
                        @click="pagoRapidoFechaDias(7)"
                    >
                        En 1 semana
                    </button>
                    <label class="pill pill--input" :aria-selected="pagoRapidoFechaPropia()">
                        Elegir…
                        <input
                            type="date"
                            :min="fechaIso(new Date())"
                            @click="abrirSelectorFecha($event.target)"
                            @change="pagoRapidoElegirFecha($event.target.value)"
                            aria-label="Elegir otra fecha"
                        >
                    </label>
                </div>
                <p
                    class="group__footer footnote"
                    x-show="pagoRapidoForm.fecha"
                    x-text="fechaLarga(pagoRapidoForm.fecha)"
                ></p>
                <div class="group form-group" x-show="pagoRapidoModo === 'reprogramar'">
                    <label class="cell form-cell">
                        <span class="cell__label">Monto</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            inputmode="decimal"
                            x-model="pagoRapidoForm.monto"
                            :placeholder="pagoRapido ? `${formatoMonto(pagoRapido.monto)} (el mismo)` : ''"
                        >
                    </label>
                </div>
                <p class="group__footer footnote" x-show="pagoRapidoModo === 'reprogramar'">
                    Queda registrado como «reprogramado»: no cuenta como incumplido.
                </p>
                <div class="pago-rapido-botones">
                    <button
                        class="btn-principal"
                        type="button"
                        :disabled="!pagoRapidoForm.fecha || pagoRapidoOcupado"
                        @click="pagoRapidoGuardar(true)"
                        x-text="pagoRapidoModo === 'parcial' ? 'Registrar y agendar el saldo' : 'Reprogramar'"
                    ></button>
                    <button
                        class="btn-texto"
                        type="button"
                        x-show="pagoRapidoModo === 'parcial'"
                        :disabled="pagoRapidoOcupado"
                        @click="pagoRapidoGuardar(false)"
                    >
                        Registrar sin agendar el saldo
                    </button>
                </div>
            </div>
        </div>
    </section>
</div>
