<?php

declare(strict_types=1);

/**
 * Pantalla «Notas»: compositor, notas fijadas y recientes. Lógica en assets/js/notas.js.
 */
?>
<section class="screen" x-show="activo === 'notas'">
    <header class="navbar">
        <p class="subhead tabular" x-text="`${resumenNotas.total} notas · ${resumenNotas.fijadas} fijadas`"></p>
        <div class="navbar__large-row">
            <h1 class="navbar__large-title">Notas</h1>
            <div class="navbar__acciones">
                <label class="search-field search-field--header">
                    <i data-lucide="search"></i>
                    <input
                        type="search"
                        x-model="busquedaNotas"
                        @input.debounce.300ms="cargarNotas()"
                        placeholder="Buscar en notas"
                        aria-label="Buscar en notas"
                    >
                </label>
            </div>
        </div>
    </header>
    <div class="screen__body">
        <p class="error-inline" x-show="errorNotas" x-text="errorNotas"></p>
        <form class="nota-composer" @submit.prevent="crearNotaRapida()">
            <p class="rotulo">Nueva nota</p>
            <textarea x-model="composerTexto" rows="2" placeholder="Escribí algo para acordarte…"></textarea>
            <div class="nota-composer__barra">
                <span class="footnote">Color</span>
                <div class="color-dots" role="radiogroup" aria-label="Color de la nota">
                    <template x-for="color in ['celeste','durazno','lavanda','menta','rosa']" :key="color">
                        <button
                            type="button"
                            class="color-dot"
                            :class="`color-dot--${color}`"
                            role="radio"
                            :aria-checked="composerColor === color"
                            :aria-label="color"
                            @click="composerColor = color"
                        ></button>
                    </template>
                </div>
                <button type="button" class="nota-composer__vincular" @click="buscadorContacto = !buscadorContacto">
                    <i data-lucide="users"></i>
                    <span x-text="composerContacto ? composerContacto.nombre : 'Vincular contacto'"></span>
                </button>
                <button type="submit" class="btn-principal" :disabled="guardandoComposer || !composerTexto.trim()">
                    Guardar
                </button>
            </div>
            <div class="group" x-show="buscadorContacto" x-cloak>
                <label class="cell form-cell">
                    <span class="cell__label">Buscar</span>
                    <input
                        x-model="busquedaContacto"
                        @input.debounce.300ms="buscarContactoParaNota()"
                        placeholder="Nombre o celular"
                    >
                </label>
                <template x-for="contacto in resultadosContacto" :key="contacto.id">
                    <button type="button" class="cell" @click="elegirContactoComposer(contacto)">
                        <span
                            class="avatar"
                            :style="`background:${colorAvatar(contacto.nombre)}`"
                            x-text="iniciales(contacto.nombre)"
                        ></span>
                        <span class="cell__content"><span class="cell__title" x-text="contacto.nombre"></span></span>
                    </button>
                </template>
            </div>
        </form>
        <section x-show="notasFijadas().length">
            <p class="group__header footnote">fijadas</p>
            <div class="notas-fijadas">
                <template x-for="nota in notasFijadas()" :key="nota.id">
                    <button
                        type="button"
                        class="nota-card"
                        :class="`nota--${nota.color}`"
                        @click="abrirNotaEditor(nota)"
                    >
                        <div class="nota-card__cabecera">
                            <h3 class="nota-card__titulo" x-text="nota.titulo || 'Sin título'"></h3>
                            <i class="nota-card__pin" data-lucide="pin"></i>
                        </div>
                        <p class="nota-card__texto" x-text="nota.texto"></p>
                        <?php $metodoFecha = 'fechaNotaFijada'; require __DIR__ . '/../parciales/nota_pie.php'; ?>
                    </button>
                </template>
            </div>
        </section>
        <section x-show="notasRecientes().length">
            <p class="group__header footnote">recientes</p>
            <div class="notas-recientes">
                <template x-for="nota in notasRecientes()" :key="nota.id">
                    <button
                        type="button"
                        class="nota-card"
                        :class="`nota--${nota.color}`"
                        @click="abrirNotaEditor(nota)"
                    >
                        <h3 class="nota-card__titulo" x-text="nota.titulo || 'Sin título'"></h3>
                        <p class="nota-card__texto" x-text="nota.texto"></p>
                        <?php $metodoFecha = 'fechaNotaReciente'; require __DIR__ . '/../parciales/nota_pie.php'; ?>
                    </button>
                </template>
            </div>
        </section>
        <div class="empty-state" x-show="!cargandoNotas && !notas.length">
            <i data-lucide="sticky-note"></i>
            <p
                class="body-text"
                x-text="busquedaNotas ? 'No encontramos notas con esa búsqueda' : 'Todavía no tenés notas'"
            ></p>
        </div>
    </div>
</section>
