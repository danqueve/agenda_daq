<?php

declare(strict_types=1);

/**
 * Tira de 14 días de Hoy (esta semana y la siguiente, de lunes a domingo): cada día muestra lo que falta cobrar o lo
 * ya cobrado, con su color de estado. Al tocar un día se abre en el Calendario. Lógica en assets/js/cobranza.js.
 */
?>
<div class="semana-card">
    <div class="semana-card__cabecera">
        <h2 class="semana-card__titulo">Próximos 14 días</h2>
        <button type="button" class="semana-card__ver" @click="activo = 'agenda'">Ver calendario</button>
    </div>
    <div class="semana-card__dias">
        <template x-for="dia in diasCobranza()" :key="dia.iso">
            <button
                type="button"
                class="semana-dia"
                :class="claseDiaCobranza(dia)"
                :aria-label="dia.etiqueta"
                :aria-current="dia.esHoy ? 'date' : null"
                @click="irACalendarioDia(dia.iso)"
            >
                <span class="semana-dia__letra" aria-hidden="true" x-text="dia.letra"></span>
                <span class="semana-dia__num tabular" aria-hidden="true" x-text="dia.numero"></span>
                <span class="semana-dia__monto tabular" aria-hidden="true" x-text="dia.monto_texto"></span>
            </button>
        </template>
    </div>
    <div class="calendario-leyenda semana-card__leyenda">
        <span class="calendario-leyenda__item">
            <span class="calendario-leyenda__punto calendario-leyenda__punto--vencido"></span>
            Vencido
        </span>
        <span class="calendario-leyenda__item">
            <span class="calendario-leyenda__punto calendario-leyenda__punto--hoy"></span>
            Para hoy
        </span>
        <span class="calendario-leyenda__item">
            <span class="calendario-leyenda__punto calendario-leyenda__punto--proximo"></span>
            Próximo
        </span>
        <span class="calendario-leyenda__item">
            <span class="calendario-leyenda__punto calendario-leyenda__punto--hecho"></span>
            Cobrado
        </span>
    </div>
</div>
