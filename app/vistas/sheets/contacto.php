<?php

declare(strict_types=1);

/**
 * Hoja «Nueva consulta / Editar contacto»: cliente de SAS Imperio, datos principales, consulta, próximo paso
 * (recontactar o agendar un pago, son excluyentes) y más datos. Lógica en assets/js/contactos.js; estilos en
 * assets/css/pantallas/contacto.css.
 */
?>
<div
    class="sheet-backdrop"
    x-show="sheetContacto"
    x-transition.opacity
    @keydown.escape.window="sheetContacto = false"
    @click.self="sheetContacto = false"
    x-cloak
>
    <form class="sheet sheet--formulario form-contacto" @submit.prevent="guardarContacto()">
        <div class="sheet__grabber"></div>
        <header class="sheet__header">
            <button class="btn-texto" type="button" @click="sheetContacto = false">Cancelar</button>
            <h2 class="sheet__title" x-text="modoFormulario === 'nuevo' ? 'Nueva consulta' : 'Editar contacto'"></h2>
            <button
                class="btn-texto btn-texto--negrita"
                type="submit"
                :disabled="guardandoContacto"
                x-text="guardandoContacto ? 'Guardando…' : 'Guardar'"
            ></button>
        </header>
        <div class="sheet__body form-contacto__cuerpo">
            <template x-if="modoFormulario === 'nuevo' && sasHabilitado">
                <section class="form-contacto__seccion" aria-labelledby="fc-sas">
                    <p id="fc-sas" class="group__header">Cliente de SAS Imperio</p>
                    <div class="form-contacto__panel" x-show="!formularioContacto.sas_cliente_id">
                        <label class="sas-busqueda">
                            <i data-lucide="search" aria-hidden="true"></i>
                            <span class="sr-only">Buscar cliente en SAS Imperio</span>
                            <input
                                x-model="sasBusqueda"
                                @input.debounce.300ms="buscarClienteSas()"
                                type="search"
                                inputmode="search"
                                autocomplete="off"
                                maxlength="80"
                                placeholder="Celular, DNI o apellido"
                            >
                            <span class="sas-busqueda__estado" x-show="sasBuscando">Buscando…</span>
                        </label>
                        <div class="group sas-resultados" x-show="sasResultados.length">
                            <template x-for="cliente in sasResultados" :key="cliente.id">
                                <button
                                    type="button"
                                    class="cell cell--avatar-offset"
                                    @click="elegirClienteSas(cliente)"
                                >
                                    <?php require __DIR__ . '/../parciales/sas_cliente_contenido.php'; ?>
                                </button>
                            </template>
                        </div>
                        <p class="form-contacto__ayuda" x-show="sasMensaje" x-text="sasMensaje"></p>
                        <p class="form-contacto__ayuda" x-show="!sasMensaje && !sasResultados.length">
                            Si es cliente, se completan nombre, celular y localidad.
                        </p>
                    </div>
                    <div class="sas-vinculado" x-show="formularioContacto.sas_cliente_id">
                        <span
                            class="avatar"
                            :style="`background:${colorAvatar(formularioContacto.nombre)}`"
                            x-text="iniciales(formularioContacto.nombre)"
                        ></span>
                        <span class="sas-vinculado__texto">
                            <span class="sas-vinculado__nombre" x-text="formularioContacto.nombre"></span>
                            <span
                                class="sas-vinculado__dato tabular"
                                x-text="formularioContacto.dni ? `DNI ${formularioContacto.dni} · vinculado a SAS` : 'Vinculado a SAS Imperio'"
                            ></span>
                        </span>
                        <button type="button" class="btn-texto" @click="quitarClienteSas()">Quitar</button>
                    </div>
                </section>
            </template>
            <section class="form-contacto__seccion" aria-labelledby="fc-datos">
                <p id="fc-datos" class="group__header">Datos principales</p>
                <div class="group">
                    <label class="cell form-cell">
                        <span class="cell__label">Nombre</span>
                        <input
                            x-model.trim="formularioContacto.nombre"
                            required
                            maxlength="120"
                            autocomplete="name"
                            placeholder="Nombre y apellido"
                        >
                    </label>
                    <label class="cell form-cell">
                        <span class="cell__label">Celular</span>
                        <input
                            x-model.trim="formularioContacto.celular"
                            required
                            type="tel"
                            inputmode="tel"
                            autocomplete="tel"
                            maxlength="30"
                            placeholder="381 456 7890"
                        >
                    </label>
                </div>
            </section>
            <template x-if="modoFormulario === 'nuevo'">
                <section class="form-contacto__seccion" aria-labelledby="fc-consulta">
                    <p id="fc-consulta" class="group__header">Consulta</p>
                    <label class="textarea-celeste">
                        <span class="sr-only">Consulta</span><textarea
                            x-model.trim="formularioContacto.consulta"
                            required
                            maxlength="4000"
                            rows="3"
                            placeholder="¿Qué está buscando?"
                        ></textarea>
                    </label>
                </section>
            </template>
            <template x-if="modoFormulario === 'nuevo'">
                <section class="form-contacto__seccion" aria-labelledby="fc-paso">
                    <p id="fc-paso" class="group__header">Próximo paso</p>
                    <div class="segmented form-contacto__paso" role="tablist" aria-label="Próximo paso">
                        <button
                            type="button"
                            class="segmented__option"
                            role="tab"
                            :aria-selected="!formularioContacto.conPago"
                            @click="formularioContacto.conPago = false"
                        >
                            <i data-lucide="phone-call" aria-hidden="true"></i>
                            Recontactar
                        </button>
                        <button
                            type="button"
                            class="segmented__option"
                            role="tab"
                            :aria-selected="formularioContacto.conPago"
                            @click="activarPagoConsulta()"
                        >
                            <i data-lucide="banknote" aria-hidden="true"></i>
                            Agendar pago
                        </button>
                    </div>
                    <div class="form-contacto__panel" x-show="!formularioContacto.conPago">
                        <div class="pills" role="radiogroup" aria-label="Cuándo recontactar">
                            <template
                                x-for="opcion in [{dias: 1, texto: 'Mañana'}, {dias: 3, texto: 'En 3 días'}, {dias: 7, texto: 'En 1 semana'}]"
                                :key="opcion.dias"
                            >
                                <button
                                    class="pill"
                                    type="button"
                                    role="radio"
                                    :aria-checked="fechaRapidaElegida(opcion.dias)"
                                    :aria-selected="fechaRapidaElegida(opcion.dias)"
                                    @click="alternarFechaRapida(opcion.dias)"
                                    x-text="opcion.texto"
                                ></button>
                            </template>
                            <label class="pill pill--input" :aria-selected="fechaRecontactoElegidaAMano()">
                                Elegir…
                                <input
                                    type="date"
                                    @click="abrirSelectorFecha($event.target)"
                                    @change="elegirFechaRecontacto($event.target.value)"
                                    aria-label="Elegir fecha de recontacto"
                                >
                            </label>
                        </div>
                        <p
                            class="form-contacto__ayuda tabular"
                            x-text="formularioContacto.proximo_contacto ? `Aviso: ${fechaLarga(formularioContacto.proximo_contacto)}` : 'Opcional: sin fecha, queda en «Sin fecha» en Hoy.'"
                        ></p>
                    </div>
                    <div class="form-contacto__panel" x-show="formularioContacto.conPago">
                        <div class="group">
                            <label class="cell form-cell">
                                <span class="cell__label">Monto</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    inputmode="decimal"
                                    x-model="formularioContacto.pago.monto"
                                    :required="formularioContacto.conPago"
                                    placeholder="$ 0"
                                >
                            </label>
                            <label class="cell form-cell">
                                <span class="cell__label">Concepto</span>
                                <input
                                    x-model.trim="formularioContacto.pago.concepto"
                                    maxlength="150"
                                    list="conceptos-pagos"
                                    autocomplete="off"
                                    placeholder="Elegí o escribí"
                                >
                            </label>
                            <label class="cell form-cell" x-show="esConceptoCuota(formularioContacto.pago.concepto)">
                                <span class="cell__label">N° de cuota</span>
                                <input
                                    type="number"
                                    min="1"
                                    max="999"
                                    inputmode="numeric"
                                    x-model="formularioContacto.pago.cuota"
                                    placeholder="Opcional, ej. 3"
                                >
                            </label>
                            <label class="cell form-cell">
                                <span class="cell__label">Fecha</span>
                                <input
                                    type="date"
                                    x-model="formularioContacto.pago.dia"
                                    @click="abrirSelectorFecha($event.target)"
                                    :required="formularioContacto.conPago"
                                    aria-label="Fecha del pago"
                                >
                            </label>
                        </div>
                        <div class="pills" role="radiogroup" aria-label="Fecha rápida del pago">
                            <template
                                x-for="opcion in [{dias: 1, texto: 'Mañana'}, {dias: 7, texto: 'En 1 semana'}, {dias: 30, texto: 'En 1 mes'}]"
                                :key="opcion.dias"
                            >
                                <button
                                    class="pill"
                                    type="button"
                                    role="radio"
                                    :aria-checked="formularioContacto.pago.dia === diaPagoConsulta(opcion.dias)"
                                    :aria-selected="formularioContacto.pago.dia === diaPagoConsulta(opcion.dias)"
                                    @click="formularioContacto.pago.dia = diaPagoConsulta(opcion.dias)"
                                    x-text="opcion.texto"
                                ></button>
                            </template>
                        </div>
                        <p
                            class="form-contacto__ayuda tabular"
                            x-text="formularioContacto.pago.dia ? `Aviso: ${fechaLarga(formularioContacto.pago.dia + 'T09:00')} · el pago reemplaza al recontacto` : 'El aviso va a ser la fecha del pago.'"
                        ></p>
                    </div>
                </section>
            </template>
            <section class="form-contacto__seccion">
                <button
                    class="disclosure"
                    type="button"
                    :aria-expanded="formularioContacto.masDatos"
                    @click="formularioContacto.masDatos = !formularioContacto.masDatos"
                >
                    <span>Más datos</span>
                    <span class="disclosure__icono" :class="{'is-abierto': formularioContacto.masDatos}">
                        <i data-lucide="chevron-down" aria-hidden="true"></i>
                    </span>
                </button>
                <div class="form-contacto__mas" x-show="formularioContacto.masDatos" x-transition.opacity>
                    <div class="group">
                        <label class="cell form-cell">
                            <span class="cell__label">Producto</span>
                            <input
                                x-model.trim="formularioContacto.producto_interes"
                                maxlength="120"
                                placeholder="Opcional"
                            >
                        </label>
                        <label class="cell form-cell">
                            <span class="cell__label">Origen</span>
                            <select x-model="formularioContacto.origen">
                                <option value="otro">Otro</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="instagram">Instagram</option>
                                <option value="facebook">Facebook</option>
                                <option value="llamada">Llamada</option>
                                <option value="local">Local</option>
                                <option value="referido">Referido</option>
                            </select>
                        </label>
                        <label class="cell form-cell">
                            <span class="cell__label">Localidad</span>
                            <input x-model.trim="formularioContacto.localidad" maxlength="100" placeholder="Opcional">
                        </label>
                        <label class="cell form-cell">
                            <span class="cell__label">Provincia</span>
                            <select x-model="formularioContacto.provincia">
                                <option value="">Sin especificar</option>
                                <option>Tucumán</option>
                                <option>Santiago del Estero</option>
                                <option>Catamarca</option>
                                <option>Otra</option>
                            </select>
                        </label>
                    </div>
                    <p class="group__header">Etiquetas</p>
                    <div class="tag-picker">
                        <template x-for="etiqueta in etiquetas" :key="etiqueta.id">
                            <button
                                type="button"
                                class="tag"
                                :class="{'tag--selected': etiquetaSeleccionada(etiqueta.id)}"
                                :aria-pressed="etiquetaSeleccionada(etiqueta.id)"
                                @click="alternarEtiqueta(etiqueta.id)"
                            >
                                <span class="tag__dot" :style="`background:${etiqueta.color}`"></span>
                                <span x-text="etiqueta.nombre"></span>
                            </button>
                        </template>
                    </div>
                    <div class="tag-create">
                        <input
                            x-model="etiquetaNueva"
                            @keydown.enter.prevent="crearEtiqueta()"
                            maxlength="50"
                            placeholder="Nueva etiqueta"
                        >
                        <button type="button" class="btn-texto" @click="crearEtiqueta()">Agregar</button>
                    </div>
                </div>
            </section>
        </div>
    </form>
</div>
