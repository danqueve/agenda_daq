<?php

declare(strict_types=1);

/**
 * Pantalla «Calendario»: vistas Mes / Semana / Día, panel del día y detalle del evento. Lógica en assets/js/panel.js y
 * pagos.js.
 */
?>
<section class="screen" x-show="activo === 'agenda'">
    <header class="navbar">
        <p
            class="subhead"
            x-text="`${eventosDelMes()} recontactos agendados este mes${pagosDelMes() ? ` · ${pagosDelMes()} ${pagosDelMes() === 1 ? 'pago' : 'pagos'}` : ''}`"
        ></p>
        <div class="navbar__large-row">
            <h1 class="navbar__large-title" x-text="tituloMes()"></h1>
        </div>
    </header>
    <div class="screen__body">
        <div class="calendario-toolbar">
            <div
                class="segmented"
                role="tablist"
                aria-label="Vista del calendario"
                style="margin-bottom:0; max-width:260px"
            >
                <button
                    type="button"
                    class="segmented__option"
                    role="tab"
                    :aria-selected="vistaCalendario === 'mes'"
                    @click="vistaCalendario = 'mes'; refrescarIconos()"
                >
                    Mes
                </button>
                <button
                    type="button"
                    class="segmented__option"
                    role="tab"
                    :aria-selected="vistaCalendario === 'semana'"
                    @click="vistaCalendario = 'semana'"
                >
                    Semana
                </button>
                <button
                    type="button"
                    class="segmented__option"
                    role="tab"
                    :aria-selected="vistaCalendario === 'dia'"
                    @click="vistaCalendario = 'dia'"
                >
                    Día
                </button>
            </div>
            <div class="calendario-nav">
                <button type="button" class="calendario-nav__flecha" @click="moverMes(-1)" aria-label="Mes anterior">
                    <i data-lucide="chevron-left"></i>
                </button>
                <button type="button" class="calendario-nav__hoy" @click="volverMesHoy()">Hoy</button>
                <button type="button" class="calendario-nav__flecha" @click="moverMes(1)" aria-label="Mes siguiente">
                    <i data-lucide="chevron-right"></i>
                </button>
            </div>
        </div>
        <div class="calendario-layout">
            <div class="calendario-principal">
                <template x-if="vistaCalendario === 'mes'">
                    <div class="calendario-grid">
                        <template x-for="letra in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="letra">
                            <div class="calendario-grid__encabezado" x-text="letra"></div>
                        </template>
                        <template x-for="dia in diasDelMes()" :key="dia.iso">
                            <button
                                type="button"
                                class="calendario-dia"
                                :class="claseCeldaCalendario(dia)"
                                :aria-label="etiquetaCeldaCalendario(dia)"
                                @click="seleccionarDia(dia.iso)"
                            >
                                <span class="calendario-dia__numero" x-text="dia.numero"></span>
                                <div class="calendario-dia__eventos">
                                    <template x-if="cobroDia(dia.iso).cantidad">
                                        <span
                                            class="calendario-dia__cobro"
                                            :class="{'calendario-dia__cobro--vencido': cobroDia(dia.iso).vencido}"
                                        >
                                            <strong
                                                class="calendario-dia__monto tabular"
                                                x-text="formatoMontoCompacto(cobroDia(dia.iso).monto)"
                                            ></strong>
                                            <span
                                                class="calendario-dia__cantidad"
                                                x-text="cobroDia(dia.iso).cantidad === 1 ? '1 pago' : `${cobroDia(dia.iso).cantidad} pagos`"
                                            ></span>
                                        </span>
                                    </template>
                                    <template x-if="eventosDia(dia.iso).length">
                                        <span class="calendario-dia__recontactos">
                                            <i data-lucide="phone"></i>
                                            <span class="tabular" x-text="eventosDia(dia.iso).length"></span>
                                        </span>
                                    </template>
                                </div>
                            </button>
                        </template>
                    </div>
                </template>
                <template x-if="vistaCalendario === 'semana'">
                    <div>
                        <div class="week-strip">
                            <div class="week-strip__days">
                                <template x-for="dia in diasSemana()" :key="dia.iso">
                                    <button
                                        type="button"
                                        class="week-strip__day"
                                        :class="{'week-strip__day--selected': diaSeleccionado === dia.iso, 'week-strip__day--today': dia.esHoy}"
                                        @click="seleccionarDia(dia.iso)"
                                    >
                                        <span class="caption" x-text="dia.letra"></span>
                                        <strong x-text="dia.numero"></strong>
                                        <span
                                            class="week-strip__dot"
                                            x-show="eventosDia(dia.iso).length || pagosDia(dia.iso).length"
                                            :class="{'week-strip__dot--selected': diaSeleccionado === dia.iso}"
                                        ></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <?php require __DIR__ . '/../parciales/cal_lista_dia.php'; ?>
                    </div>
                </template>
                <template x-if="vistaCalendario === 'dia'">
                    <div>
                        <?php require __DIR__ . '/../parciales/cal_lista_dia.php'; ?>
                    </div>
                </template>
            </div>
            <aside class="calendario-panel">
                <div
                    class="cal-detalle-wrap"
                    x-show="eventoAbierto"
                    x-cloak
                    @keydown.escape.window="cerrarEventoCalendario()"
                >
                    <article class="cal-detalle" :class="claseCalDetalle()">
                        <div class="cal-detalle__cabecera">
                            <div class="cal-detalle__fila">
                                <span class="cal-detalle__chip" x-text="etiquetaEventoCalendario(eventoAbierto)"></span>
                                <button
                                    type="button"
                                    class="cal-detalle__cerrar"
                                    aria-label="Cerrar"
                                    @click="cerrarEventoCalendario()"
                                >
                                    <i data-lucide="x"></i>
                                </button>
                            </div>
                            <div class="cal-detalle__persona">
                                <span
                                    class="avatar"
                                    :style="`background:${colorAvatar(eventoAbierto?.nombre)}`"
                                    x-text="iniciales(eventoAbierto?.nombre)"
                                ></span>
                                <div class="cal-detalle__persona-texto">
                                    <p class="cal-detalle__nombre" x-text="eventoAbierto?.nombre"></p>
                                    <p
                                        class="cal-detalle__sub tabular"
                                        x-text="[eventoAbierto?.celular, eventoAbierto?.localidad].filter(Boolean).join(' · ')"
                                    ></p>
                                    <p
                                        class="cal-detalle__autor"
                                        x-text="`Cargó: ${eventoAbierto?.creado_por_nombre || 'Sin registrar'}`"
                                    ></p>
                                </div>
                            </div>
                            <p class="cal-detalle__cuando tabular">
                                <i data-lucide="clock"></i>
                                <span x-text="fechaEventoCalendario(eventoAbierto)"></span>
                            </p>
                        </div>
                        <div class="cal-detalle__cuerpo">
                            <template x-if="!resultadoEventoAbierto">
                                <div class="cal-detalle__detalle">
                                    <p x-show="eventoAbierto?.consulta" x-text="eventoAbierto?.consulta"></p>
                                    <div class="cal-detalle__contacto">
                                        <a
                                            class="boton-blanco boton-blanco--llamar"
                                            :href="`tel:+${eventoAbierto?.celular_norm}`"
                                        >
                                            <i data-lucide="phone"></i>
                                            Llamar
                                        </a>
                                        <a
                                            class="boton-blanco boton-blanco--whatsapp"
                                            target="_blank"
                                            rel="noopener"
                                            :href="`https://wa.me/${eventoAbierto?.celular_norm}`"
                                        >
                                            <i data-lucide="message-circle"></i>
                                            WhatsApp
                                        </a>
                                    </div>
                                    <div class="cal-detalle__repro">
                                        <p class="rotulo">Reprogramar</p>
                                        <div class="repro-pills">
                                            <button
                                                type="button"
                                                class="repro-pill"
                                                role="radio"
                                                aria-checked="false"
                                                @click="reprogramarEventoCalendario('hora')"
                                            >
                                                Hoy +1 h
                                            </button>
                                            <button
                                                type="button"
                                                class="repro-pill"
                                                role="radio"
                                                aria-checked="false"
                                                @click="reprogramarEventoCalendario('manana')"
                                            >
                                                Mañana 9:00
                                            </button>
                                            <button
                                                type="button"
                                                class="repro-pill"
                                                role="radio"
                                                aria-checked="false"
                                                @click="reprogramarEventoCalendario('tres_dias')"
                                            >
                                                En 3 días
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div class="cal-detalle__ok" x-show="resultadoEventoAbierto">
                                <i data-lucide="check"></i>
                                <span x-text="resultadoEventoAbierto === 'reprogramado' ? 'Reprogramado' : 'Hecho'"></span>
                            </div>
                            <div class="cal-detalle__pie">
                                <button
                                    type="button"
                                    class="boton-blanco"
                                    @click="abrirFicha(eventoAbierto.id); cerrarEventoCalendario()"
                                >
                                    Abrir ficha
                                </button>
                                <button type="button" class="btn-principal" @click="marcarEventoCalendarioHecho()">
                                    Marcar hecho
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
                <div class="calendario-panel__resumen">
                    <p
                        class="calendario-panel__dia"
                        x-text="new Intl.DateTimeFormat('es-AR', {weekday:'long'}).format(desdeIso(diaSeleccionado))"
                    ></p>
                    <p class="calendario-panel__titulo" x-text="resumenDiaCalendario()"></p>
                </div>
                <div class="calendario-panel__lista">
                    <template
                        x-for="evento in contactosDiaSeleccionado()"
                        :key="`p-${evento.id}-${evento.fecha_evento}`"
                    >
                        <button
                            type="button"
                            class="calendario-panel__evento"
                            :class="`calendario-panel__evento--${evento.tipo_evento}`"
                            @click="abrirEventoCalendario(evento)"
                        >
                            <p class="calendario-panel__hora tabular" x-text="fechaCorta(evento.fecha_evento)"></p>
                            <p class="calendario-panel__nombre" x-text="evento.nombre"></p>
                            <p
                                class="calendario-panel__detalle"
                                x-text="[evento.producto_interes, evento.consulta].filter(Boolean).join(' · ') || 'Sin detalle'"
                            ></p>
                            <p
                                class="calendario-panel__autor"
                                x-text="`Cargó: ${evento.creado_por_nombre || 'Sin registrar'}`"
                            ></p>
                        </button>
                    </template>
                </div>
                <div class="calendario-pagos" x-show="pagosDia(diaSeleccionado).length">
                    <p class="calendario-panel__dia">Pagos a cobrar</p>
                    <template x-for="pago in pagosDia(diaSeleccionado)" :key="`pc-${pago.id}`">
                        <div class="compromiso-item" :class="clasePagoItem(pago)">
                            <div class="compromiso-item__fila">
                                <strong class="compromiso-item__monto tabular" x-text="montoPagoTexto(pago)"></strong>
                                <span class="chip-estado chip-estado--estado" x-text="etiquetaPago(pago)"></span>
                            </div>
                            <p
                                class="compromiso-item__detalle"
                                x-text="[pago.contacto_nombre, pago.concepto].filter(Boolean).join(' · ')"
                            ></p>
                            <p
                                class="compromiso-item__autor"
                                x-text="`Cargó: ${pago.creado_por_nombre || 'Sin registrar'}`"
                            ></p>
                            <p
                                class="compromiso-item__autor"
                                x-show="pago.cobrador_nombre"
                                x-text="`Cobrador: ${pago.cobrador_nombre}`"
                            ></p>
                            <p
                                class="compromiso-item__aviso"
                                x-show="avisoPagoTexto(pago)"
                                x-text="avisoPagoTexto(pago)"
                            ></p>
                            <?php require __DIR__ . '/../parciales/pago_sas.php'; ?>
                            <?php $variante = 'texto'; require __DIR__ . '/../parciales/pago_acciones.php'; ?>
                        </div>
                    </template>
                </div>
                <div class="calendario-leyenda">
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
                        Hecho
                    </span>
                    <span class="calendario-leyenda__item">
                        <span class="calendario-leyenda__punto calendario-leyenda__punto--incumplido"></span>
                        Incumplido
                    </span>
                </div>
                <div class="calendario-leyenda calendario-leyenda--cobro" x-show="vistaCalendario === 'mes'">
                    <span class="calendario-leyenda__titulo">A cobrar por día</span>
                    <span class="calendario-leyenda__item">
                        Menos
                        <span class="calendario-leyenda__escala" aria-hidden="true">
                            <span class="calendario-leyenda__nivel calendario-leyenda__nivel--1"></span>
                            <span class="calendario-leyenda__nivel calendario-leyenda__nivel--2"></span>
                            <span class="calendario-leyenda__nivel calendario-leyenda__nivel--3"></span>
                            <span class="calendario-leyenda__nivel calendario-leyenda__nivel--4"></span>
                        </span>
                        Más
                    </span>
                    <span class="calendario-leyenda__item">
                        <strong class="calendario-leyenda__rojo">$ en rojo</strong>
                        pagos vencidos
                    </span>
                </div>
                <?php require __DIR__ . '/../sheets/evento_calendario.php'; ?>
            </aside>
        </div>
    </div>
</section>
