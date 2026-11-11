<?php

declare(strict_types=1);

/**
 * Bloque «Por usuario» de la pantalla Pagos: pendientes, vencido, cobrado en el mes y cumplimiento de cada persona.
 * Tocar un usuario filtra por lo que cargó. Lógica en assets/js/pagos.js.
 */
?>
<section class="pagos-usuarios" x-show="pagosPorUsuario.length" x-cloak>
    <button
        class="pagos-usuarios__cabecera"
        type="button"
        :aria-expanded="pagosUsuariosAbierto"
        aria-controls="pagos-usuarios-cuerpo"
        @click="pagosUsuariosAbierto = !pagosUsuariosAbierto"
    >
        <i data-lucide="users"></i>
        <span class="pagos-usuarios__titulo">Por usuario</span>
        <span class="pagos-usuarios__cuenta tabular" x-text="pagosPorUsuario.length"></span>
        <i data-lucide="chevron-down" class="pagos-usuarios__flecha"></i>
    </button>
    <div class="pagos-usuarios__cuerpo" id="pagos-usuarios-cuerpo" x-show="pagosUsuariosAbierto" x-cloak>
        <p class="pagos-usuarios__ayuda">
            Tocá un usuario para ver solo lo que cargó. Cumplimiento: lo cobrado sobre lo prometido para fechas de este
            mes que ya llegaron.
        </p>
        <template x-for="usuario in pagosPorUsuario" :key="usuario.filtro">
            <button
                class="pagos-usuario"
                type="button"
                :aria-pressed="usuarioPagosActivo(usuario)"
                @click="filtrarPorUsuarioPagos(usuario)"
            >
                <span class="pagos-usuario__nombre">
                    <span x-text="usuario.nombre"></span>
                    <small x-show="usuario.usuario_id === null">cuenta eliminada</small>
                </span>
                <span class="pagos-usuario__dato">
                    <span class="pagos-usuario__rotulo">Pendientes</span>
                    <strong
                        class="tabular"
                        x-text="formatoMontoCompacto(usuario.pendientes.monto)"
                        :title="formatoMonto(usuario.pendientes.monto)"
                    ></strong>
                    <span class="pagos-usuario__sub" x-text="cantidadPagosTexto(usuario.pendientes.cantidad)"></span>
                </span>
                <span
                    class="pagos-usuario__dato"
                    :class="{'pagos-usuario__dato--alerta': usuario.vencidos.cantidad > 0}"
                >
                    <span class="pagos-usuario__rotulo">Vencido</span>
                    <strong
                        class="tabular"
                        x-text="formatoMontoCompacto(usuario.vencidos.monto)"
                        :title="formatoMonto(usuario.vencidos.monto)"
                    ></strong>
                    <span class="pagos-usuario__sub" x-text="cantidadPagosTexto(usuario.vencidos.cantidad)"></span>
                </span>
                <span class="pagos-usuario__dato">
                    <span class="pagos-usuario__rotulo">Cobrado en el mes</span>
                    <strong
                        class="tabular"
                        x-text="formatoMontoCompacto(usuario.cobrado_mes)"
                        :title="formatoMonto(usuario.cobrado_mes)"
                    ></strong>
                </span>
                <span class="pagos-usuario__dato">
                    <span class="pagos-usuario__rotulo">Cumplimiento</span>
                    <strong
                        class="tabular"
                        x-text="usuario.cumplimiento_mes === null ? 'Sin datos' : `${usuario.cumplimiento_mes}%`"
                    ></strong>
                    <span class="pagos-barra-fina" aria-hidden="true">
                        <span :style="`--valor:${Math.min(usuario.cumplimiento_mes || 0, 100)}%`"></span>
                    </span>
                </span>
            </button>
        </template>
    </div>
</section>
