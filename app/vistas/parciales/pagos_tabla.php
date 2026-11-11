<?php

declare(strict_types=1);

/**
 * Vista «Tabla» de la pantalla Pagos: columnas ordenables, acciones de cada fila, resumen de SAS y totales.
 * Lógica en assets/js/pagos.js.
 */
?>
<template x-if="vistaPagosEfectiva() === 'tabla'">
    <div class="pagos-tabla-envoltorio" role="region" aria-label="Tabla de compromisos de pago" tabindex="0">
        <table class="pagos-tabla">
            <caption class="sr-only">
                Compromisos de pago. Se pueden ordenar las columnas Fecha, Cliente, Monto, Atraso y Cargó.
            </caption>
            <thead>
                <tr>
                    <th scope="col" :aria-sort="ariaOrdenPagos('fecha')">
                        <button type="button" class="pagos-tabla__orden" @click="ordenarPagosPor('fecha')">
                            Fecha
                        </button>
                    </th>
                    <th scope="col" :aria-sort="ariaOrdenPagos('cliente')">
                        <button type="button" class="pagos-tabla__orden" @click="ordenarPagosPor('cliente')">
                            Cliente
                        </button>
                    </th>
                    <th scope="col">Concepto</th>
                    <th scope="col" class="pagos-tabla__num" :aria-sort="ariaOrdenPagos('monto')">
                        <button type="button" class="pagos-tabla__orden" @click="ordenarPagosPor('monto')">
                            Monto
                        </button>
                    </th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="pagos-tabla__num" :aria-sort="ariaOrdenPagos('atraso')">
                        <button type="button" class="pagos-tabla__orden" @click="ordenarPagosPor('atraso')">
                            Atraso
                        </button>
                    </th>
                    <th scope="col" :aria-sort="ariaOrdenPagos('creado_por')">
                        <button type="button" class="pagos-tabla__orden" @click="ordenarPagosPor('creado_por')">
                            Cargó
                        </button>
                    </th>
                    <th scope="col">Resolvió</th>
                    <th scope="col"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <template x-for="pago in pagos" :key="`pt-${pago.id}`">
                <tbody
                    class="pagos-tabla__grupo"
                    :class="{'pagos-tabla__grupo--con-sas': chipSas(pago) || sugerenciaSas(pago)}"
                >
                    <tr :class="`pagos-tabla__fila--${pago.situacion}`">
                        <td class="tabular" x-text="fechaTablaPago(pago)"></td>
                        <td>
                            <button
                                type="button"
                                class="pagos-tabla__cliente"
                                @click="abrirFicha(pago.contacto_id)"
                                x-text="pago.contacto_nombre"
                            ></button>
                            <small
                                class="pagos-tabla__aviso"
                                x-show="avisoPagoTexto(pago)"
                                x-text="avisoPagoTexto(pago)"
                            ></small>
                        </td>
                        <td x-text="pago.concepto || '—'"></td>
                        <td class="pagos-tabla__num tabular" x-text="montoPagoTexto(pago)"></td>
                        <td>
                            <span class="chip-estado chip-estado--estado" x-text="etiquetaPago(pago)"></span>
                        </td>
                        <td class="pagos-tabla__num tabular" x-text="atrasoTablaPago(pago)"></td>
                        <td x-text="pago.creado_por_nombre || 'Sin registrar'"></td>
                        <td x-text="pago.resuelto_por_nombre || '—'"></td>
                        <td>
                            <?php $variante = 'tabla'; require __DIR__ . '/pago_acciones.php'; ?>
                        </td>
                    </tr>
                    <tr
                        class="pagos-tabla__sas"
                        :class="`pagos-tabla__fila--${pago.situacion}`"
                        x-show="chipSas(pago) || sugerenciaSas(pago)"
                    >
                        <td colspan="9">
                            <div class="pago-fila__sas">
                                <?php require __DIR__ . '/pago_sas.php'; ?>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>
            <tfoot>
                <tr>
                    <th scope="row" colspan="3" x-text="`Total · ${cantidadPagosTexto(totalesPagos.cantidad)}`"></th>
                    <td class="pagos-tabla__num tabular" x-text="formatoMonto(totalesPagos.monto)"></td>
                    <td
                        colspan="2"
                        class="tabular"
                        x-text="Number(totalesPagos.pagado) > 0 ? `Cobrado ${formatoMonto(totalesPagos.pagado)}` : ''"
                    ></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
