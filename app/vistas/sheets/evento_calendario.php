<?php

declare(strict_types=1);

/**
 * Detalle de un evento del calendario (hoja en celular; en escritorio se ve en el panel del día). Lógica en assets/js/panel.js.
 */
?>
<div class="sheet-backdrop cal-evento-sheet" x-show="eventoAbierto" x-transition.opacity @keydown.escape.window="cerrarEventoCalendario()" @click.self="cerrarEventoCalendario()" x-cloak>
    <div class="sheet" role="dialog" aria-label="Recontacto">
        <div class="sheet__grabber"></div>
        <div class="sheet__cabecera-pastel">
            <div class="cal-detalle__persona">
                <span class="avatar" :style="`background:${colorAvatar(eventoAbierto?.nombre)}`" x-text="iniciales(eventoAbierto?.nombre)"></span>
                <div class="cal-detalle__persona-texto">
                    <p class="cal-detalle__nombre" x-text="eventoAbierto?.nombre"></p>
                    <p class="cal-detalle__sub tabular" x-text="`${fechaEventoCalendario(eventoAbierto)} · ${etiquetaEventoCalendario(eventoAbierto)}`"></p>
                    <p class="cal-detalle__autor" x-text="`Cargó: ${eventoAbierto?.creado_por_nombre || 'Sin registrar'}`"></p>
                </div>
                <button type="button" aria-label="Cerrar" class="cal-detalle__cerrar" @click="cerrarEventoCalendario()"><i data-lucide="x"></i></button>
            </div>
        </div>
        <div class="sheet__body">
            <p x-show="eventoAbierto?.consulta" x-text="eventoAbierto?.consulta"></p>
            <div class="cal-evento-sheet__tiles">
                <a class="accion-tile accion-tile--llamar" :href="`tel:+${eventoAbierto?.celular_norm}`"><i data-lucide="phone"></i>Llamar</a>
                <a class="accion-tile accion-tile--whatsapp" target="_blank" rel="noopener" :href="`https://wa.me/${eventoAbierto?.celular_norm}`"><i data-lucide="message-circle"></i>WhatsApp</a>
                <button type="button" class="accion-tile accion-tile--posponer" @click="posponerContacto(eventoAbierto, 'manana'); cerrarEventoCalendario()"><i data-lucide="clock-3"></i>Posponer</button>
            </div>
            <div class="cal-detalle__pie">
                <button type="button" class="boton-blanco" @click="abrirFicha(eventoAbierto.id); cerrarEventoCalendario()">Ver ficha</button>
                <button type="button" class="btn-principal" @click="marcarEventoCalendarioHecho()">Marcar hecho</button>
            </div>
        </div>
    </div>
</div>
