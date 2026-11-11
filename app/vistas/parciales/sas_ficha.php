<?php

declare(strict_types=1);

/**
 * Bloque «En SAS Imperio» de la ficha del contacto: el resumen completo de SAS (solo lectura) con
 * la hora de la última actualización, o el botón «Vincular con SAS» si el contacto no está vinculado.
 * La lógica está en assets/js/sas.js; los datos salen de api/sas_resumen.php.
 */
?>

<div class="ficha-sas" x-show="sasHabilitado" x-cloak>
    <div class="ficha-compromisos__cabecera">
        <p class="group__header footnote">en sas imperio</p>
        <button type="button" class="btn-texto" x-show="!contactoActual?.sas_cliente_id" @click="abrirVinculoSas()">
            Vincular con SAS
        </button>
        <button
            type="button"
            class="btn-texto"
            x-show="contactoActual?.sas_cliente_id"
            :disabled="sasFicha.cargando"
            @click="cargarResumenSasFicha(true)"
            x-text="sasFicha.cargando ? 'Consultando…' : 'Actualizar'"
        ></button>
    </div>
    <p class="ficha-sas__vacio" x-show="!contactoActual?.sas_cliente_id">
        Este contacto todavía no está vinculado a un cliente de SAS Imperio. Al vincularlo ves acá cuánto debe y si ya
        pagó algo.
    </p>
    <template x-if="contactoActual?.sas_cliente_id">
        <div class="sas-bloque">
            <p class="ficha-sas__vacio" x-show="sasFicha.cargando && !fichaSas()">Consultando SAS Imperio…</p>
            <p class="ficha-sas__vacio" x-show="!sasFicha.cargando && !fichaSas()">
                SAS Imperio no está disponible ahora. Probá de nuevo en unos minutos; el resto de la Agenda funciona
                igual.
            </p>
            <p class="ficha-sas__vacio" x-show="fichaSas() && !fichaSas().existe">
                Este cliente ya no figura en SAS Imperio. Podés desvincularlo.
            </p>
            <template x-if="fichaSas() && fichaSas().existe">
                <dl class="sas-datos">
                    <div class="sas-dato">
                        <dt>Créditos activos</dt>
                        <dd>
                            <strong class="tabular" x-text="fichaSas().creditos_activos"></strong><template x-if="fichaSas().creditos_activos > 0">
                                <span>
                                    · saldo
                                    <strong class="tabular" x-text="formatoMonto(fichaSas().saldo)"></strong></span>
                            </template><span
                                class="chip-sas chip-sas--vencido"
                                x-show="fichaSas().estado_cliente === 'MOROSO'"
                            >
                                Moroso
                            </span>
                        </dd>
                    </div>
                    <div class="sas-dato" :class="{'sas-dato--alerta': fichaSas().cuotas_vencidas > 0}">
                        <dt>Cuotas vencidas</dt>
                        <dd>
                            <span x-show="fichaSas().cuotas_vencidas === 0">Ninguna</span>
                            <template x-if="fichaSas().cuotas_vencidas > 0">
                                <span><strong class="tabular" x-text="fichaSas().cuotas_vencidas"></strong>
                                    ·
                                    <strong class="tabular" x-text="formatoMonto(fichaSas().monto_vencido)"></strong>
                                    · la más vieja venció el
                                    <span class="tabular" x-text="fechaCortaPago(fichaSas().vencida_mas_vieja)"></span>
                                    (<span
                                        class="tabular"
                                        x-text="`${fichaSas().dias_atraso} ${fichaSas().dias_atraso === 1 ? 'día' : 'días'}`"
                                    ></span>
                                    de atraso)</span>
                            </template>
                        </dd>
                    </div>
                    <div class="sas-dato">
                        <dt>Último pago confirmado</dt>
                        <dd>
                            <span x-show="!fichaSas().ultimo_pago">Sin pagos confirmados</span>
                            <template x-if="fichaSas().ultimo_pago">
                                <span><strong class="tabular" x-text="formatoMonto(fichaSas().ultimo_pago.monto)"></strong>
                                    el
                                    <span class="tabular" x-text="fechaCortaPago(fichaSas().ultimo_pago.fecha)"></span></span>
                            </template>
                        </dd>
                    </div>
                    <div class="sas-dato" :class="{'sas-dato--aviso': fichaSas().informados}">
                        <dt>Informados por el cobrador</dt>
                        <dd>
                            <span x-show="!fichaSas().informados">Ninguno pendiente de aprobar</span>
                            <template x-if="fichaSas().informados">
                                <span><strong class="tabular" x-text="fichaSas().informados.cantidad"></strong>
                                    ·
                                    <strong class="tabular" x-text="formatoMonto(fichaSas().informados.monto)"></strong>
                                    · último el
                                    <span class="tabular" x-text="fechaCortaPago(fichaSas().informados.fecha)"></span>, esperan aprobación en SAS</span>
                            </template>
                        </dd>
                    </div>
                </dl>
            </template>
            <p class="ficha-sas__pie" x-show="fichaSas()">
                <span x-text="`Actualizado ${horaActualizacionSas(fichaSas())}${fichaSas()?.desactualizado ? ' · SAS no respondió, es el último dato guardado' : ''}`"></span>
                <span>Montos sin mora. Atraso en días hábiles (lunes a sábado), como en SAS.</span>
            </p>
            <button type="button" class="btn-texto btn-texto--peligro ficha-sas__desvincular" @click="desvincularSas()">
                Desvincular de SAS
            </button>
        </div>
    </template>
</div>
