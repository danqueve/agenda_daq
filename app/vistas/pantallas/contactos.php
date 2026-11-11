<?php

declare(strict_types=1);

/**
 * Pantalla «Contactos»: buscador, filtros por estado y grilla de tarjetas. Lógica en assets/js/contactos.js.
 */
?>
<section class="screen" x-show="activo === 'contactos'">
    <header class="navbar">
        <p
            class="subhead tabular"
            x-text="`${resumenContactos.total} contactos · ${resumenContactos.en_seguimiento} en seguimiento`"
        ></p>
        <div class="navbar__large-row">
            <h1 class="navbar__large-title">Contactos</h1>
            <div class="navbar__acciones">
                <label class="search-field search-field--header">
                    <i data-lucide="search"></i>
                    <input
                        type="search"
                        x-model="busqueda"
                        @input.debounce.300ms="aplicarFiltros()"
                        placeholder="Nombre o celular"
                        aria-label="Buscar contactos"
                    >
                </label>
                <button class="workspace-create" type="button" @click="abrirNuevoContacto()">
                    <i data-lucide="plus"></i>
                    <span>Nueva consulta</span>
                </button>
            </div>
        </div>
    </header>
    <div class="screen__body">
        <div class="filtro-pills" role="tablist" aria-label="Estado de contactos">
            <button
                class="filtro-pill"
                type="button"
                role="tab"
                :aria-selected="filtroEstado === ''"
                @click="filtroEstado = ''; aplicarFiltros()"
            >
                Todos
            </button>
            <button
                class="filtro-pill filtro-pill--nuevo"
                type="button"
                role="tab"
                :aria-selected="filtroEstado === 'pendiente'"
                @click="filtroEstado = 'pendiente'; aplicarFiltros()"
            >
                <span class="filtro-pill__punto"></span>
                Nuevos
            </button>
            <button
                class="filtro-pill filtro-pill--seguimiento"
                type="button"
                role="tab"
                :aria-selected="filtroEstado === 'seguimiento'"
                @click="filtroEstado = 'seguimiento'; aplicarFiltros()"
            >
                <span class="filtro-pill__punto"></span>
                En seguimiento
            </button>
            <button
                class="filtro-pill filtro-pill--concreto"
                type="button"
                role="tab"
                :aria-selected="filtroEstado === 'concreto'"
                @click="filtroEstado = 'concreto'; aplicarFiltros()"
            >
                <span class="filtro-pill__punto"></span>
                Concretaron
            </button>
            <button
                class="filtro-pill filtro-pill--cerrado"
                type="button"
                role="tab"
                :aria-selected="filtroEstado === 'cerrados'"
                @click="filtroEstado = 'cerrados'; aplicarFiltros()"
            >
                <span class="filtro-pill__punto"></span>
                Cerrados
            </button>
        </div>
        <p class="error-inline" x-show="errorContactos" x-text="errorContactos"></p>
        <template x-if="contactos.length">
            <div class="card-pastel-grid contact-list">
                <template x-for="contacto in contactos" :key="contacto.id">
                    <button
                        type="button"
                        class="card-pastel"
                        :class="[`card-pastel--${estadoVisual(contacto)}`, {'card-pastel--seleccionada': contactoActual && Number(contactoActual.id) === Number(contacto.id)}]"
                        @click="abrirFicha(contacto.id)"
                    >
                        <div class="card-pastel__head">
                            <span
                                class="avatar"
                                :style="`background:${colorAvatar(contacto.nombre)}`"
                                x-text="iniciales(contacto.nombre)"
                            ></span>
                            <span class="card-pastel__cuerpo">
                                <span class="card-pastel__nombre" x-text="contacto.nombre"></span>
                                <span class="card-pastel__detalle" x-text="contacto.celular"></span>
                            </span>
                        </div>
                        <div class="card-pastel__tags">
                            <span
                                class="chip-estado"
                                x-show="contacto.producto_interes"
                                x-text="contacto.producto_interes"
                            ></span>
                            <span class="chip-estado chip-estado--estado" x-text="etiquetaEstado(contacto)"></span>
                        </div>
                        <p
                            class="card-pastel__autor"
                            x-text="`Cargó: ${contacto.creado_por_nombre || 'Sin registrar'}`"
                        ></p>
                        <p class="card-pastel__pie">
                            <span x-show="contacto.localidad" x-text="contacto.localidad"></span>
                            <span class="origen-chip" :class="`origen-chip--${origenVisual(contacto.origen).clase}`">
                                <i :data-lucide="origenVisual(contacto.origen).icono"></i>
                                <span x-text="origenVisual(contacto.origen).etiqueta"></span>
                            </span>
                            <span class="tabular" x-text="fechaRelativa(contacto.creado_en)"></span>
                        </p>
                    </button>
                </template>
            </div>
        </template>
        <div class="empty-state" x-show="!cargandoContactos && !contactos.length && !errorContactos">
            <i data-lucide="users"></i>
            <p
                class="body-text"
                x-text="busqueda || filtroEstado ? 'No encontramos contactos con esos filtros' : 'Todavía no cargaste ningún contacto'"
            ></p>
            <button
                class="empty-state__boton"
                type="button"
                @click="abrirNuevoContacto()"
                x-show="!busqueda && !filtroEstado"
            >
                Cargar consulta
            </button>
        </div>
        <button
            class="btn-texto contacts-load-more"
            type="button"
            x-show="hayMasContactos"
            @click="cargarContactos()"
            :disabled="cargandoContactos"
            x-text="cargandoContactos ? 'Cargando…' : 'Cargar más contactos'"
        ></button>
    </div>
</section>
