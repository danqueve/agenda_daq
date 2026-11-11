<?php

declare(strict_types=1);

/**
 * Hoja «Nuevo / Editar compromiso» de pago. Lógica en assets/js/pagos.js.
 */
?>
<div
    class="sheet-backdrop"
    x-show="sheetCompromiso"
    x-transition.opacity
    @keydown.escape.window="sheetCompromiso && cerrarCompromiso()"
    @click.self="cerrarCompromiso()"
    x-cloak
>
    <form class="sheet" @submit.prevent="guardarCompromiso()">
        <div class="sheet__grabber"></div>
        <header class="sheet__header">
            <button class="btn-texto" type="button" @click="cerrarCompromiso()">Cancelar</button>
            <h2 class="sheet__title" x-text="compromisoModo === 'nuevo' ? 'Nuevo compromiso' : 'Editar compromiso'"></h2>
            <button
                class="btn-texto btn-texto--negrita"
                type="submit"
                :disabled="guardandoCompromiso"
                x-text="guardandoCompromiso ? 'Guardando…' : 'Guardar'"
            ></button>
        </header>
        <div class="sheet__body">
            <p class="group__header footnote">quién paga</p>
            <div class="group" x-show="compromisoContacto">
                <div class="cell">
                    <span
                        class="avatar"
                        :style="`background:${colorAvatar(compromisoContacto?.nombre)}`"
                        x-text="iniciales(compromisoContacto?.nombre)"
                    ></span>
                    <span class="cell__content">
                        <span class="cell__title" x-text="compromisoContacto?.nombre"></span>
                    </span>
                    <button
                        type="button"
                        class="btn-texto"
                        x-show="compromisoModo === 'nuevo'"
                        @click="compromisoContacto = null"
                    >
                        Cambiar
                    </button>
                </div>
            </div>
            <div x-show="!compromisoContacto">
                <div class="group form-group" x-show="!compromisoContactoNuevo">
                    <label class="cell form-cell">
                        <span class="cell__label">Buscar</span>
                        <input
                            x-model="compromisoBusqueda"
                            @input.debounce.300ms="buscarContactoCompromiso()"
                            placeholder="Nombre o celular"
                            autocomplete="off"
                        >
                    </label>
                    <template x-for="contacto in compromisoResultados" :key="contacto.id">
                        <button type="button" class="cell" @click="elegirContactoCompromiso(contacto)">
                            <span
                                class="avatar"
                                :style="`background:${colorAvatar(contacto.nombre)}`"
                                x-text="iniciales(contacto.nombre)"
                            ></span>
                            <span class="cell__content">
                                <span class="cell__title" x-text="contacto.nombre"></span>
                                <span class="cell__subtitle" x-text="contacto.celular"></span>
                            </span>
                        </button>
                    </template>
                </div>
                <div class="group form-group" x-show="compromisoContactoNuevo">
                    <label class="cell form-cell">
                        <span class="cell__label">Nombre</span>
                        <input
                            x-model.trim="compromisoForm.nombre"
                            maxlength="120"
                            autocomplete="name"
                            placeholder="Nombre y apellido"
                        >
                    </label>
                    <label class="cell form-cell">
                        <span class="cell__label">Celular</span>
                        <input
                            x-model.trim="compromisoForm.celular"
                            inputmode="tel"
                            autocomplete="tel"
                            maxlength="30"
                            placeholder="381 456 7890"
                        >
                    </label>
                </div>
                <button
                    type="button"
                    class="btn-texto"
                    @click="compromisoContactoNuevo = !compromisoContactoNuevo"
                    x-text="compromisoContactoNuevo ? 'Buscar un contacto existente' : 'Es un contacto nuevo'"
                ></button>
            </div>
            <p class="group__header footnote">pago</p>
            <div class="group form-group">
                <label class="cell form-cell">
                    <span class="cell__label">Monto</span>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        inputmode="decimal"
                        x-model="compromisoForm.monto"
                        required
                        placeholder="$ 0"
                    >
                </label>
                <label class="cell form-cell">
                    <span class="cell__label">Concepto</span>
                    <input
                        x-model.trim="compromisoForm.concepto"
                        maxlength="150"
                        list="conceptos-pagos"
                        autocomplete="off"
                        placeholder="Elegí o escribí"
                    >
                </label>
                <label class="cell form-cell" x-show="esConceptoCuota(compromisoForm.concepto)">
                    <span class="cell__label">N° de cuota</span>
                    <input
                        type="number"
                        min="1"
                        max="999"
                        inputmode="numeric"
                        x-model="compromisoForm.cuota"
                        placeholder="Opcional, ej. 3"
                    >
                </label>
            </div>
            <p class="group__header footnote">fecha del pago</p>
            <div class="pills date-pills">
                <button
                    class="pill"
                    type="button"
                    :aria-selected="pagosEsManana(compromisoForm.fecha_compromiso)"
                    @click="asignarFechaCompromiso(1)"
                >
                    Mañana
                </button>
                <label
                    class="pill pill--input"
                    :aria-selected="Boolean(compromisoForm.fecha_compromiso) && !pagosEsManana(compromisoForm.fecha_compromiso)"
                >
                    Elegir…
                    <input
                        type="date"
                        @click="abrirSelectorFecha($event.target)"
                        @change="elegirFechaCompromiso($event.target.value)"
                        aria-label="Elegir fecha del pago"
                    >
                </label>
            </div>
            <p
                class="group__footer footnote"
                x-show="compromisoForm.fecha_compromiso"
                x-text="fechaLarga(compromisoForm.fecha_compromiso)"
            ></p>
            <p class="group__header footnote">nota</p>
            <div class="group form-group">
                <label class="cell form-cell">
                    <span class="cell__label">Nota</span>
                    <input x-model.trim="compromisoForm.nota" maxlength="2000" placeholder="Opcional">
                </label>
            </div>
            <button
                type="button"
                class="btn-texto btn-texto--peligro delete-contact"
                x-show="compromisoModo === 'editar'"
                @click="eliminarCompromiso()"
            >
                Eliminar compromiso
            </button>
        </div>
    </form>
</div>
