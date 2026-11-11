<?php

declare(strict_types=1);

/**
 * Ficha del contacto: identidad, acciones, próximo recontacto, SAS, compromisos, historial, estado y notas. Lógica en
 * assets/js/contactos.js, seguimientos.js, pagos.js y sas.js.
 */
?>
<div
    class="sheet-backdrop detail-backdrop"
    x-show="sheetFicha"
    x-transition.opacity
    @keydown.escape.window="sheetFicha = false"
    @click.self="sheetFicha = false"
    x-cloak
>
    <article class="sheet contact-detail contact-detail--ancho" x-show="contactoActual">
        <div class="sheet__grabber"></div>
        <header class="sheet__header">
            <button class="btn-texto" type="button" @click="sheetFicha = false">Cerrar</button>
            <span class="sheet__title">Contacto</span>
            <button class="btn-texto btn-texto--negrita" type="button" @click="editarContacto()">Editar</button>
        </header>
        <div class="sheet__body">
            <div
                class="contact-detail__identidad"
                :class="`contact-detail__identidad--${estadoVisual(contactoActual)}`"
            >
                <span
                    class="avatar avatar--grande"
                    :style="`background:${colorAvatar(contactoActual?.nombre)}`"
                    x-text="iniciales(contactoActual?.nombre)"
                ></span>
                <div class="contact-detail__texto">
                    <div class="contact-detail__nombre-fila">
                        <h2 class="title2" x-text="contactoActual?.nombre"></h2>
                        <span class="contact-detail__badge" x-text="etiquetaEstado(contactoActual)"></span>
                    </div>
                    <p
                        class="contact-detail__sub tabular"
                        x-text="`${contactoActual?.celular} · Cliente desde el ${fechaSoloDia(contactoActual?.creado_en)}`"
                    ></p>
                    <p
                        class="contact-detail__cargado"
                        x-text="`Cargó: ${contactoActual?.creado_por_nombre || 'Sin registrar'}`"
                    ></p>
                    <div
                        class="card-pastel__tags"
                        x-show="contactoActual?.producto_interes || contactoActual?.etiquetas?.length"
                    >
                        <span
                            class="chip-estado"
                            x-show="contactoActual?.producto_interes"
                            x-text="contactoActual?.producto_interes"
                        ></span>
                        <span class="chip-estado" x-show="contactoActual?.dni" x-text="`DNI ${contactoActual?.dni}`"></span>
                        <span class="chip-estado chip-estado--estado" x-show="contactoActual?.sas_cliente_id">
                            Cliente SAS
                        </span>
                        <template x-for="etiqueta in contactoActual?.etiquetas" :key="etiqueta.id">
                            <span class="chip-estado" x-text="etiqueta.nombre"></span>
                        </template>
                    </div>
                </div>
                <div class="action-buttons">
                    <a class="action-button action-button--llamar" :href="`tel:+${contactoActual?.celular_norm}`">
                        <span class="action-button__icon"><i data-lucide="phone"></i></span>
                        <span class="action-button__label">Llamar</span>
                    </a>
                    <a
                        class="action-button action-button--whatsapp"
                        target="_blank"
                        rel="noopener"
                        :href="`https://wa.me/${contactoActual?.celular_norm}`"
                    >
                        <span class="action-button__icon"><i data-lucide="message-circle"></i></span>
                        <span class="action-button__label">WhatsApp</span>
                    </a>
                    <button
                        class="action-button action-button--recontacto"
                        type="button"
                        @click="abrirSeguimiento(contactoActual)"
                    >
                        <span class="action-button__icon"><i data-lucide="phone-forwarded"></i></span>
                        <span class="action-button__label">Recontacto</span>
                    </button>
                    <button
                        class="action-button action-button--nota"
                        type="button"
                        @click="abrirNotaSeguimiento(contactoActual)"
                    >
                        <span class="action-button__icon"><i data-lucide="sticky-note"></i></span>
                        <span class="action-button__label">Nota</span>
                    </button>
                    <button
                        class="action-button action-button--pago"
                        type="button"
                        @click="abrirCompromisoNuevo(contactoActual)"
                    >
                        <span class="action-button__icon"><i data-lucide="hand-coins"></i></span>
                        <span class="action-button__label">Pago</span>
                    </button>
                </div>
            </div>
            <div class="contact-detail__columnas">
                <div class="contact-detail__columna">
                    <div
                        class="ficha-proximo"
                        :class="{'ficha-proximo--vencido': fichaProximoVencido(contactoActual)}"
                        x-show="contactoActual?.estado !== 'cerrada'"
                    >
                        <span class="ficha-proximo__icono"><i data-lucide="phone-forwarded"></i></span>
                        <div class="ficha-proximo__texto">
                            <p class="ficha-proximo__rotulo">Próximo recontacto</p>
                            <p
                                class="ficha-proximo__cuando tabular"
                                x-text="fechaLarga(contactoActual?.proximo_contacto)"
                            ></p>
                            <p
                                class="ficha-proximo__detalle"
                                x-show="ultimaNotaProximo(contactoActual)"
                                x-text="ultimaNotaProximo(contactoActual)"
                            ></p>
                        </div>
                        <div class="ficha-proximo__acciones">
                            <button
                                type="button"
                                class="boton-blanco boton-blanco--posponer"
                                @click="posponerContacto(contactoActual, 'manana')"
                            >
                                <i data-lucide="clock-3"></i>
                                Posponer
                            </button>
                            <button
                                type="button"
                                class="boton-blanco boton-blanco--hecho"
                                @click="abrirSeguimiento(contactoActual)"
                            >
                                <i data-lucide="check"></i>
                                Hecho
                            </button>
                        </div>
                    </div>
                    <?php require __DIR__ . '/../parciales/sas_ficha.php'; ?>
                    <div class="ficha-compromisos" x-show="contactoActual?.compromisos?.length">
                        <div class="ficha-compromisos__cabecera">
                            <p class="group__header footnote">compromisos de pago</p>
                            <button type="button" class="btn-texto" @click="abrirCompromisoNuevo(contactoActual)">
                                Agregar
                            </button>
                        </div>
                        <template x-for="compromiso in contactoActual?.compromisos" :key="`cp-${compromiso.id}`">
                            <div class="compromiso-item" :class="clasePagoItem(compromiso)">
                                <div class="compromiso-item__fila">
                                    <strong class="compromiso-item__monto tabular" x-text="montoPagoTexto(compromiso)"></strong>
                                    <span class="chip-estado chip-estado--estado" x-text="etiquetaPago(compromiso)"></span>
                                </div>
                                <p
                                    class="compromiso-item__detalle"
                                    x-text="[compromiso.concepto, fechaPago(compromiso)].filter(Boolean).join(' · ')"
                                ></p>
                                <p
                                    class="compromiso-item__cadena"
                                    x-show="cadenaCompromisoTexto(compromiso)"
                                    x-text="cadenaCompromisoTexto(compromiso)"
                                ></p>
                                <div class="compromiso-item__acciones">
                                    <button
                                        type="button"
                                        class="boton-blanco boton-blanco--hecho"
                                        x-show="compromiso.estado === 'pendiente'"
                                        @click="abrirPagoResolver(compromiso)"
                                    >
                                        <i data-lucide="hand-coins"></i>
                                        Registrar pago
                                    </button>
                                    <button
                                        type="button"
                                        class="boton-blanco"
                                        x-show="compromiso.estado === 'pendiente'"
                                        @click="abrirCompromisoEditar(compromiso)"
                                    >
                                        <i data-lucide="pencil"></i>
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="boton-blanco"
                                        x-show="compromiso.estado !== 'pendiente'"
                                        @click="reabrirCompromiso(compromiso)"
                                    >
                                        <i data-lucide="rotate-ccw"></i>
                                        Deshacer
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <p class="group__header footnote">historial</p>
                    <div class="historial">
                        <template x-for="registro in contactoActual?.historial" :key="registro.id">
                            <div class="historial__item">
                                <div class="historial__riel">
                                    <span
                                        class="historial__icono"
                                        :class="`historial__icono--${colorTimeline(registro)}`"
                                    >
                                        <i :data-lucide="iconoTimeline(registro)"></i>
                                    </span>
                                    <span class="historial__linea"></span>
                                </div>
                                <div class="historial__cuerpo">
                                    <span class="historial__titulo" x-text="tituloTimeline(registro)"></span><span
                                        class="historial__chip historial__chip--orange"
                                        x-show="registro.proximo_asignado"
                                        x-text="`Para ${fechaCorta(registro.proximo_asignado)}`"
                                    ></span>
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
                            <button
                                type="button"
                                class="estado-opcion estado-opcion--nuevo"
                                role="radio"
                                :aria-checked="estadoVisual(contactoActual) === 'nuevo'"
                                @click="cambiarEstadoSelector(contactoActual, 'nuevo')"
                            >
                                <span class="estado-opcion__punto"></span>
                                Nuevo
                            </button>
                            <button
                                type="button"
                                class="estado-opcion estado-opcion--seguimiento"
                                role="radio"
                                :aria-checked="estadoVisual(contactoActual) === 'seguimiento'"
                                @click="cambiarEstadoSelector(contactoActual, 'seguimiento')"
                            >
                                <span class="estado-opcion__punto"></span>
                                En seguimiento
                            </button>
                            <button
                                type="button"
                                class="estado-opcion estado-opcion--concreto"
                                role="radio"
                                :aria-checked="estadoVisual(contactoActual) === 'concreto'"
                                @click="cambiarEstadoSelector(contactoActual, 'concreto')"
                            >
                                <span class="estado-opcion__punto"></span>
                                Concretó
                            </button>
                            <button
                                type="button"
                                class="estado-opcion estado-opcion--cerrado"
                                role="radio"
                                :aria-checked="estadoVisual(contactoActual) === 'cerrado'"
                                @click="cambiarEstadoSelector(contactoActual, 'cerrado')"
                            >
                                <span class="estado-opcion__punto"></span>
                                Cerrado
                            </button>
                        </div>
                    </div>
                    <div class="group">
                        <div class="cell" x-show="contactoActual?.producto_interes">
                            <span class="cell__label">Producto</span>
                            <span class="cell__value" x-text="contactoActual?.producto_interes"></span>
                        </div>
                        <div class="cell" x-show="contactoActual?.localidad">
                            <span class="cell__label">Localidad</span>
                            <span class="cell__value" x-text="contactoActual?.localidad"></span>
                        </div>
                        <div class="cell" x-show="contactoActual?.cobrador_nombre">
                            <span class="cell__label">Cobrador</span>
                            <span class="cell__value" x-text="contactoActual?.cobrador_nombre"></span>
                        </div>
                        <div class="cell">
                            <span class="cell__label">Origen</span>
                            <span class="cell__value">
                                <span
                                    class="origen-chip"
                                    :class="`origen-chip--${origenVisual(contactoActual?.origen).clase}`"
                                >
                                    <i :data-lucide="origenVisual(contactoActual?.origen).icono"></i>
                                    <span x-text="origenVisual(contactoActual?.origen).etiqueta"></span>
                                </span>
                            </span>
                        </div>
                    </div>
                    <template x-if="notasDelContacto(contactoActual).length">
                        <div>
                            <p class="group__header footnote">notas</p>
                            <div class="notas-mini-lista">
                                <template x-for="nota in notasDelContacto(contactoActual)" :key="nota.id">
                                    <div class="nota-mini nota--durazno">
                                        <p class="nota-mini__titulo tabular" x-text="fechaLarga(nota.fecha)"></p>
                                        <p class="nota-mini__texto" x-text="nota.nota"></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <button class="btn-texto btn-texto--peligro delete-contact" type="button" @click="pedirEliminar()">
                Eliminar contacto
            </button>
        </div>
    </article>
</div>
