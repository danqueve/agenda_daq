<?php

declare(strict_types=1);

/**
 * Las cuatro tarjetas de totales de cobranza: vencidos, para hoy, próximos 7 días y cobrado del mes. Las usan Pagos y Hoy.
 *
 * @var string      $resumen      Expresión Alpine del resumen ({vencidos, hoy, proximos: {monto, cantidad}, cobrado_mes}):
 *                                `resumenVisiblePagos()` en Pagos y `panel.cobranza.resumen` en Hoy.
 * @var string|null $cumplimiento Expresión Alpine del cumplimiento del mes ({porcentaje}) o null. Si viene, la tarjeta
 *                                «Cobrado» muestra la barra de cumplimiento (Hoy).
 */
?>
<div class="stat-card-grid stat-card-grid--montos">
    <div class="stat-card stat-card--vencido">
        <div class="stat-card__cabecera">
            <span class="stat-card__etiqueta">Vencidos</span>
            <span class="stat-card__icono"><i data-lucide="alert-circle"></i></span>
        </div>
        <strong
            class="stat-card__numero tabular"
            :title="formatoMonto(<?= e($resumen) ?>.vencidos.monto)"
            x-text="formatoMontoTarjeta(<?= e($resumen) ?>.vencidos.monto)"
        ></strong>
        <p class="stat-card__ayuda" x-text="cantidadPagosTexto(<?= e($resumen) ?>.vencidos.cantidad)"></p>
    </div>
    <div class="stat-card stat-card--hoy">
        <div class="stat-card__cabecera">
            <span class="stat-card__etiqueta">Para hoy</span>
            <span class="stat-card__icono"><i data-lucide="clock"></i></span>
        </div>
        <strong
            class="stat-card__numero tabular"
            :title="formatoMonto(<?= e($resumen) ?>.hoy.monto)"
            x-text="formatoMontoTarjeta(<?= e($resumen) ?>.hoy.monto)"
        ></strong>
        <p class="stat-card__ayuda" x-text="cantidadPagosTexto(<?= e($resumen) ?>.hoy.cantidad)"></p>
    </div>
    <div class="stat-card stat-card--proximo">
        <div class="stat-card__cabecera">
            <span class="stat-card__etiqueta">Próximos 7 días</span>
            <span class="stat-card__icono"><i data-lucide="calendar-days"></i></span>
        </div>
        <strong
            class="stat-card__numero tabular"
            :title="formatoMonto(<?= e($resumen) ?>.proximos.monto)"
            x-text="formatoMontoTarjeta(<?= e($resumen) ?>.proximos.monto)"
        ></strong>
        <p class="stat-card__ayuda" x-text="cantidadPagosTexto(<?= e($resumen) ?>.proximos.cantidad)"></p>
    </div>
    <div class="stat-card stat-card--concreto">
        <div class="stat-card__cabecera">
            <span class="stat-card__etiqueta">Cobrado</span>
            <span class="stat-card__icono"><i data-lucide="check"></i></span>
        </div>
        <strong
            class="stat-card__numero tabular"
            :title="formatoMonto(<?= e($resumen) ?>.cobrado_mes)"
            x-text="formatoMontoTarjeta(<?= e($resumen) ?>.cobrado_mes)"
        ></strong>
        <p class="stat-card__ayuda">Este mes</p>
        <?php if ($cumplimiento !== null) : ?>
        <div class="stat-card__cumplimiento" x-show="<?= e($cumplimiento) ?>.porcentaje !== null">
            <p class="stat-card__ayuda">
                Cumplimiento
                <strong class="tabular" x-text="`${<?= e($cumplimiento) ?>.porcentaje}%`"></strong>
            </p>
            <span class="pagos-barra-fina" aria-hidden="true">
                <span :style="`--valor:${Math.min(<?= e($cumplimiento) ?>.porcentaje || 0, 100)}%`"></span>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>
