<?php

declare(strict_types=1);

/**
 * Hoja «Notificaciones» (dispositivos y resumen diario). Lógica en assets/js/ajustes.js.
 */
?>
<div
    class="sheet-backdrop"
    x-show="sheetPush"
    x-transition.opacity
    @keydown.escape.window="sheetPush = false"
    @click.self="sheetPush = false"
    x-cloak
>
    <section class="sheet" aria-labelledby="push-titulo">
        <div class="sheet__grabber"></div>
        <header class="sheet__header">
            <button class="btn-texto" type="button" @click="sheetPush = false">Cerrar</button>
            <h2 id="push-titulo" class="sheet__title">Notificaciones</h2>
            <span></span>
        </header>
        <div class="sheet__body">
            <div class="group">
                <button class="cell" type="button" @click="activarPush()" :disabled="activandoPush">
                    <span class="cell__content">
                        <span class="cell__title">Activar en este dispositivo</span>
                        <span
                            class="cell__subtitle"
                            x-text="activandoPush ? 'Solicitando permiso…' : (esIos && !instalada ? 'Primero agregá la app a Inicio' : 'Recibí recordatorios push')"
                        ></span>
                    </span>
                    <span class="toggle" role="switch" :aria-checked="push.dispositivos?.length ? 'true' : 'false'">
                        <span class="toggle__perilla"></span>
                    </span>
                </button>
            </div>
            <p class="group__header footnote">dispositivos activos</p>
            <div class="group">
                <template x-for="dispositivo in push.dispositivos" :key="dispositivo.id">
                    <div class="cell">
                        <span class="cell__icon-box" style="background:var(--tint)">
                            <i data-lucide="smartphone"></i>
                        </span>
                        <span class="cell__content">
                            <span class="cell__title" x-text="dispositivo.dispositivo || 'Dispositivo' "></span>
                            <span class="cell__subtitle" x-text="dispositivo.ultimo_uso || dispositivo.creado_en"></span>
                        </span>
                        <button class="btn-texto btn-texto--peligro" type="button" @click="quitarPush(dispositivo)">
                            Quitar
                        </button>
                    </div>
                </template>
                <p class="empty-inline" x-show="!push.dispositivos?.length">Todavía no hay dispositivos activos.</p>
            </div>
            <button
                class="btn-principal"
                type="button"
                @click="enviarPruebaPush()"
                :disabled="!push.dispositivos?.length"
            >
                Enviar prueba
            </button>
            <p class="group__header footnote settings-list-header">resumen diario</p>
            <div class="group">
                <label class="cell form-cell">
                    <span class="cell__label">Hora</span>
                    <input
                        type="time"
                        x-model="push.hora_resumen"
                        @change="guardarHoraResumen()"
                        aria-label="Hora del resumen diario"
                    >
                </label>
            </div>
            <p class="group__footer footnote">
                En iPhone, las notificaciones requieren que Agenda esté instalada desde Compartir → Agregar a inicio.
            </p>
        </div>
    </section>
</div>
