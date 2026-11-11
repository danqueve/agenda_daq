<?php

declare(strict_types=1);

/**
 * Tarjeta de un compromiso de pago, para usar dentro de un x-for cuya variable Alpine se llama `pago`: persona,
 * estado, monto, fecha, quién lo cargó, aviso, resumen de SAS y la botonera de acciones. La usan Hoy (Pagos a
 * cobrar) y Pagos › Tarjetas; el x-for y su :key quedan en cada pantalla.
 *
 * @var string $montoExpresion Expresión Alpine del monto: `formatoMonto(pago.monto)` en Hoy y
 *                            `montoPagoTexto(pago)` en Pagos (que muestra el saldo de los parciales).
 */
?>
<article class="card-pastel" :class="clasePago(pago)">
    <div class="card-pastel__head">
        <button type="button" class="card-pastel__persona" @click="abrirFicha(pago.contacto_id)">
            <span
                class="avatar"
                :style="`background:${colorAvatar(pago.contacto_nombre)}`"
                x-text="iniciales(pago.contacto_nombre)"
            ></span>
            <span class="card-pastel__cuerpo">
                <span class="card-pastel__nombre" x-text="pago.contacto_nombre"></span>
                <span class="card-pastel__detalle" x-text="pago.concepto || 'Sin concepto'"></span>
            </span>
        </button>
        <span class="chip-estado chip-estado--estado" x-text="etiquetaPago(pago)"></span>
    </div>
    <p class="card-pastel__monto tabular" x-text="<?= e($montoExpresion) ?>"></p>
    <p class="card-pastel__fecha tabular">
        <i data-lucide="calendar-days"></i>
        <span x-text="fechaPago(pago)"></span>
    </p>
    <p class="card-pastel__autor" x-text="`Cargó: ${pago.creado_por_nombre || 'Sin registrar'}`"></p>
    <p class="card-pastel__aviso" x-show="avisoPagoTexto(pago)" x-text="avisoPagoTexto(pago)"></p>
    <?php require __DIR__ . '/pago_sas.php'; ?>
    <?php $variante = 'texto'; require __DIR__ . '/pago_acciones.php'; ?>
</article>
