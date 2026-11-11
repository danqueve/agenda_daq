<?php

declare(strict_types=1);

/**
 * Lista de recontactos del día elegido en el Calendario (vistas Semana y Día): título, un ítem por contacto y el
 * aviso de que no hay nada agendado.
 */
?>
<p class="group__header footnote" x-text="tituloDiaSeleccionado()"></p>
<div class="cal-item-lista" x-show="contactosDiaSeleccionado().length">
    <template x-for="contacto in contactosDiaSeleccionado()" :key="`a-${contacto.id}`">
        <button
            type="button"
            class="cal-item"
            :class="`cal-item--${contacto.tipo_evento}`"
            @click="abrirEventoCalendario(contacto)"
        >
            <span class="cal-item__hora tabular" x-text="fechaCorta(contacto.fecha_evento)"></span>
            <span class="cal-item__texto">
                <span class="cal-item__nombre" x-text="contacto.nombre"></span>
                <span
                    class="cal-item__motivo"
                    x-text="[contacto.producto_interes, contacto.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"
                ></span>
                <span class="cal-item__autor" x-text="`Cargó: ${contacto.creado_por_nombre || 'Sin registrar'}`"></span>
            </span>
            <i data-lucide="chevron-right"></i>
        </button>
    </template>
</div>
<div class="empty-state agenda-empty" x-show="!cargandoAgenda && !contactosDiaSeleccionado().length">
    <i data-lucide="calendar-days"></i>
    <p class="body-text">Sin recontactos agendados</p>
</div>
