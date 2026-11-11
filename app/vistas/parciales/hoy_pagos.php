<?php

declare(strict_types=1);

/**
 * «Pagos a cobrar» de Hoy: los pendientes vencidos, de hoy y de los próximos 7 días, agrupados (Vencidos, Hoy, Mañana,
 * «Vie 14 nov»…) con el subtotal de cada grupo. Las tarjetas son las de siempre. Lógica en assets/js/cobranza.js.
 */
?>
<section class="hoy-pagos" x-show="panel.compromisos?.length">
    <div class="seccion-cabecera">
        <h2>Pagos a cobrar</h2>
        <span
            class="hoy-pagos__total tabular"
            x-text="`${formatoMonto(totalPagosHoy())} · ${cantidadPagosTexto(panel.compromisos.length)}`"
        ></span>
    </div>
    <template x-for="grupo in gruposCobranza()" :key="grupo.clave">
        <div class="hoy-grupo" :class="`hoy-grupo--${grupo.tipo}`">
            <div class="seccion-cabecera hoy-grupo__cabecera">
                <h3 x-text="grupo.titulo"></h3>
                <span
                    class="hoy-pagos__total tabular"
                    x-text="`${formatoMonto(grupo.monto)} · ${cantidadPagosTexto(grupo.cantidad)}`"
                ></span>
            </div>
            <div class="card-pastel-grid">
                <template x-for="pago in grupo.items" :key="`hp-${pago.id}`">
                    <?php $montoExpresion = 'formatoMonto(pago.monto)'; require __DIR__ . '/tarjeta_pago.php'; ?>
                </template>
            </div>
        </div>
    </template>
    <button type="button" class="btn-texto hoy-pagos__mas" x-show="hayMasPagosEnPagos()" @click="verProximosEnPagos()">
        Ver los demás pagos pendientes en Pagos
    </button>
</section>
