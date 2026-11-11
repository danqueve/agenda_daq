<?php

declare(strict_types=1);

/**
 * Lo que dice SAS Imperio de un compromiso pendiente, para usar dentro de un x-for cuya variable
 * Alpine se llama `pago`: un chip chico («SAS: 3 cuotas vencidas · 42 días») cuando el contacto
 * está vinculado y, si SAS registró un pago posterior a la fecha del compromiso, la sugerencia
 * «¿Marcar como pagado?». Nunca se marca solo: el botón usa el registro rápido (con «Deshacer»).
 * Se incluye en Hoy, en las tres vistas de Pagos y en el panel del día del Calendario.
 */
?>
<span
    class="chip-sas"
    :class="`chip-sas--${chipSas(pago)?.clase}`"
    x-show="chipSas(pago)"
    :title="chipSasTitulo(pago)"
    x-text="chipSas(pago)?.texto"
></span>
<div
    class="sas-sugerencia"
    x-show="sugerenciaSas(pago)"
    role="group"
    :aria-label="`Sugerencia de SAS para ${pago.contacto_nombre}`"
>
    <i data-lucide="badge-check"></i>
    <span class="sas-sugerencia__texto" x-text="sugerenciaSas(pago)?.texto"></span>
    <button
        type="button"
        class="sas-sugerencia__boton"
        :disabled="pagoRapidoOcupado"
        @click="pagoRapidoPagado(pago)"
        :aria-label="`Marcar como pagado el compromiso de ${pago.contacto_nombre}`"
    >
        ¿Marcar como pagado?
    </button>
</div>
