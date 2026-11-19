<?php

declare(strict_types=1);

/**
 * «Cola y detalle» de Hoy (diseño 1b): a la izquierda la cola de cobro (pendientes vencidos, de hoy y de los próximos
 * 7 días, agrupados por día, con el filtro Todos | Míos y el cumplimiento del mes) y a la derecha el compromiso
 * elegido, con su monto, la botonera y lo que dice SAS. Sin elegir nada, el detalle muestra el siguiente a cobrar.
 * En el celular el detalle va arriba («Siguiente») y la cola debajo. Lógica en assets/js/cobranza.js.
 */
?>
<div class="cola-layout">
    <div class="cobro-hoy" aria-label="Cobrado hoy">
        <div class="cobro-hoy__fila">
            <span class="cobro-hoy__rotulo">Cobrado hoy</span>
            <strong class="tabular" x-text="progresoCobroHoy().texto"></strong>
        </div>
        <span class="barra-progreso" aria-hidden="true">
            <span :style="`width:${progresoCobroHoy().porcentaje}%`"></span>
        </span>
    </div>

    <section class="cola" aria-labelledby="cola-titulo">
        <div class="cola__cabecera">
            <div class="cola__titulo-fila">
                <h2 class="cola__titulo" id="cola-titulo">Cola de cobro</h2>
                <span class="cola__fecha" x-text="fechaHoy"></span>
            </div>
            <div class="cola__filtro">
                <div class="segmented cola__alcance" role="tablist" aria-label="Qué compromisos ver">
                    <button
                        type="button"
                        class="segmented__option"
                        role="tab"
                        :aria-selected="!hoyMios"
                        @click="elegirAlcanceHoy(false)"
                    >
                        Todos
                    </button>
                    <button
                        type="button"
                        class="segmented__option"
                        role="tab"
                        :aria-selected="hoyMios"
                        @click="elegirAlcanceHoy(true)"
                    >
                        Míos
                    </button>
                </div>
                <span
                    class="cola__total tabular"
                    x-text="`${formatoMonto(totalPagosHoy())} · ${pagosEnColaTexto()}`"
                ></span>
            </div>
            <div class="cola__cumplimiento" x-show="panel.cobranza.cumplimiento.porcentaje !== null">
                <div class="cola__cumplimiento-fila">
                    <span>Cumplimiento del mes</span>
                    <strong class="tabular" x-text="`${panel.cobranza.cumplimiento.porcentaje}%`"></strong>
                </div>
                <span class="barra-progreso barra-progreso--fina" aria-hidden="true">
                    <span :style="`width:${Math.min(panel.cobranza.cumplimiento.porcentaje || 0, 100)}%`"></span>
                </span>
            </div>
        </div>
        <div class="cola__lista">
            <p class="cola__vacia" x-show="!cargandoPanel && !panel.compromisos?.length">
                No hay pagos por cobrar en los próximos 7 días.
            </p>
            <template x-for="grupo in gruposCobranza()" :key="grupo.clave">
                <div class="cola__grupo">
                    <h3 class="cola__grupo-titulo">
                        <span x-text="grupo.titulo"></span>
                        <span class="tabular" x-text="formatoMonto(grupo.monto)"></span>
                    </h3>
                    <ul class="cola__items">
                        <template x-for="pago in grupo.items" :key="`cola-${pago.id}`">
                            <li>
                                <button
                                    type="button"
                                    class="cola-fila"
                                    :aria-current="esPagoFoco(pago) ? 'true' : null"
                                    @click="elegirPagoFoco(pago)"
                                    :aria-label="`${pago.contacto_nombre}, ${formatoMonto(pago.monto)}, ${etiquetaPago(pago)}. Ver detalle`"
                                >
                                    <span
                                        class="avatar cola-fila__avatar"
                                        aria-hidden="true"
                                        :style="`background:${colorAvatar(pago.contacto_nombre)}`"
                                        x-text="iniciales(pago.contacto_nombre)"
                                    ></span>
                                    <span class="cola-fila__cuerpo" aria-hidden="true">
                                        <span class="cola-fila__nombre" x-text="pago.contacto_nombre"></span>
                                        <span class="cola-fila__detalle" x-text="pago.concepto || 'Sin concepto'"></span>
                                    </span>
                                    <span class="cola-fila__lado" aria-hidden="true">
                                        <span class="cola-fila__monto tabular" x-text="formatoMonto(pago.monto)"></span>
                                        <span
                                            class="cola-fila__estado"
                                            :class="`cola-fila__estado--${pago.situacion}`"
                                            x-text="etiquetaPago(pago)"
                                        ></span>
                                    </span>
                                    <i data-lucide="chevron-right" class="cola-fila__chevron" aria-hidden="true"></i>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
            <button type="button" class="btn-texto cola__mas" x-show="hayMasPagosEnPagos()" @click="verProximosEnPagos()">
                Ver los demás pagos pendientes en Pagos
            </button>
        </div>
    </section>

    <section class="cola-detalle" aria-label="Detalle del compromiso" aria-live="polite">
        <template x-for="pago in [pagoFoco()].filter(Boolean)" :key="`foco-${pago.id}`">
            <article class="foco">
                <p class="foco__rotulo" x-text="pagoFocoElegido() ? 'Elegido' : 'Siguiente a cobrar'"></p>
                <div class="foco__cabecera">
                    <button type="button" class="foco__persona" @click="abrirFicha(pago.contacto_id)">
                        <span
                            class="avatar foco__avatar"
                            :style="`background:${colorAvatar(pago.contacto_nombre)}`"
                            x-text="iniciales(pago.contacto_nombre)"
                        ></span>
                        <span class="foco__cuerpo">
                            <span class="foco__nombre" x-text="pago.contacto_nombre"></span>
                            <span
                                class="foco__detalle"
                                x-text="[pago.concepto || 'Sin concepto', pago.cobrador_nombre ? `Cobrador ${pago.cobrador_nombre}` : ''].filter(Boolean).join(' · ')"
                            ></span>
                        </span>
                    </button>
                    <span
                        class="chip-estado chip-estado--estado"
                        :class="`chip-estado--${pago.situacion}`"
                        x-text="etiquetaPago(pago)"
                    ></span>
                </div>
                <div class="foco__compromiso">
                    <span class="foco__etiqueta">Compromiso de pago</span>
                    <strong class="foco__monto tabular" x-text="formatoMonto(pago.monto)"></strong>
                    <span
                        class="foco__cuando"
                        x-text="`${fechaPago(pago)} · Cargó ${pago.creado_por_nombre || 'sin registrar'}`"
                    ></span>
                    <p class="foco__aviso" x-show="avisoPagoTexto(pago)" x-text="avisoPagoTexto(pago)"></p>
                    <?php $variante = 'texto'; require __DIR__ . '/pago_acciones.php'; ?>
                </div>
                <div class="foco__sas" x-show="chipSas(pago) || sugerenciaSas(pago)">
                    <span class="foco__sas-titulo">En SAS Imperio</span>
                    <?php require __DIR__ . '/pago_sas.php'; ?>
                </div>
                <button type="button" class="foco__ficha" @click="abrirFicha(pago.contacto_id)">
                    <i data-lucide="contact"></i>
                    <span>Ver ficha e historial</span>
                    <i data-lucide="chevron-right"></i>
                </button>
            </article>
        </template>
        <div class="foco foco--vacio" x-show="!pagoFoco()">
            <i data-lucide="calendar-check"></i>
            <p>No hay pagos por cobrar. Cuando cargues un compromiso aparece acá.</p>
            <button class="workspace-create" type="button" @click="abrirCompromisoNuevo()">
                <i data-lucide="plus"></i>
                <span>Nuevo compromiso</span>
            </button>
        </div>
    </section>
</div>
