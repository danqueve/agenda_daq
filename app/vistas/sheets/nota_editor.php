<?php

declare(strict_types=1);

/**
 * Editor de una nota (color, fijar, título, texto, contacto, recordatorio). Lógica en assets/js/notas.js.
 */
?>
<div
    class="sheet-backdrop nota-editor-backdrop"
    x-show="sheetNota"
    x-transition.opacity
    @keydown.escape.window="cerrarNota()"
    @click.self="cerrarNota()"
    x-cloak
>
    <div class="nota-editor" :class="`nota--${notaActual?.color}`" x-show="notaActual">
        <div class="nota-editor__barra">
            <div class="color-dots" role="radiogroup" aria-label="Color de la nota">
                <template x-for="color in ['celeste','durazno','lavanda','menta','rosa']" :key="color">
                    <button
                        type="button"
                        class="color-dot"
                        :class="`color-dot--${color}`"
                        role="radio"
                        :aria-checked="notaActual?.color === color"
                        :aria-label="color"
                        @click="elegirColorNotaActual(color)"
                    ></button>
                </template>
            </div>
            <div class="nota-editor__acciones">
                <button
                    type="button"
                    class="nota-editor__icono"
                    :aria-pressed="Number(notaActual?.fijada) === 1"
                    aria-label="Fijar nota"
                    @click="alternarFijada(notaActual)"
                >
                    <i data-lucide="pin"></i>
                </button>
                <button type="button" class="nota-editor__icono" aria-label="Cerrar" @click="cerrarNota()">
                    <i data-lucide="x"></i>
                </button>
            </div>
        </div>
        <div class="nota-editor__cuerpo">
            <label>
                <span class="sr-only">Título</span><input
                    type="text"
                    class="nota-editor__titulo"
                    x-model="notaActual.titulo"
                    @input="programarAutoguardado()"
                    placeholder="Título"
                >
            </label>
            <label>
                <span class="sr-only">Texto de la nota</span><textarea
                    class="nota-editor__texto"
                    rows="5"
                    x-model="notaActual.texto"
                    @input="programarAutoguardado()"
                    placeholder="Escribí tu nota…"
                ></textarea>
            </label>
            <div class="nota-editor__contacto" x-show="notaActual?.contacto_nombre">
                <p class="rotulo">Contacto</p>
                <div class="nota-editor__contacto-fila">
                    <span
                        class="avatar"
                        :style="`background:${colorAvatar(notaActual?.contacto_nombre)}`"
                        x-text="iniciales(notaActual?.contacto_nombre)"
                    ></span>
                    <span class="nota-editor__contacto-nombre" x-text="notaActual?.contacto_nombre"></span>
                    <button type="button" class="btn-texto" @click="abrirFichaDesdeNota()">Abrir ficha</button>
                </div>
            </div>
            <div>
                <p class="rotulo">Recordarme</p>
                <div class="repro-pills">
                    <button
                        type="button"
                        class="repro-pill"
                        role="radio"
                        :aria-checked="Boolean(notaActual?.recordar_en)"
                        @click="elegirRecordatorioNota('hoy')"
                    >
                        Más tarde hoy
                    </button>
                    <button type="button" class="repro-pill" role="radio" @click="elegirRecordatorioNota('manana')">
                        Mañana 9:00
                    </button>
                    <button
                        type="button"
                        class="repro-pill"
                        role="radio"
                        :aria-checked="!notaActual?.recordar_en"
                        @click="elegirRecordatorioNota('nunca')"
                    >
                        Sin recordatorio
                    </button>
                </div>
            </div>
        </div>
        <div class="nota-editor__pie">
            <span class="nota-editor__guardado">
                <i data-lucide="check"></i>
                <span x-text="textoGuardadoNota()"></span>
            </span>
            <button type="button" class="btn-texto btn-texto--peligro" @click="pedirEliminarNota()">Eliminar</button>
            <button type="button" class="btn-principal" @click="cerrarNota()">Listo</button>
        </div>
    </div>
</div>
