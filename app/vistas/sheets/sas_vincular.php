<?php

declare(strict_types=1);

/**
 * Hoja «Vincular con SAS» de la ficha: reutiliza el buscador de api/sas_clientes.php (celular, DNI o
 * nombre) y guarda el vínculo con api/sas_vinculo.php. Solo se escribe en la base de la Agenda.
 */
?>

<div
    class="sheet-backdrop"
    x-show="vinculoSas.abierta"
    x-transition.opacity
    @keydown.escape.window="vinculoSas.abierta && cerrarVinculoSas()"
    @click.self="cerrarVinculoSas()"
    x-cloak
>
    <section class="sheet sheet--media" role="dialog" aria-modal="true" aria-labelledby="vinculo-sas-titulo">
        <div class="sheet__grabber"></div>
        <header class="sheet__header">
            <button class="btn-texto" type="button" @click="cerrarVinculoSas()">Cancelar</button>
            <h2 class="sheet__title" id="vinculo-sas-titulo">Vincular con SAS</h2>
            <span aria-hidden="true"></span>
        </header>
        <div class="sheet__body">
            <p class="sheet-contact-name" x-text="contactoActual?.nombre"></p>
            <div class="group form-group">
                <label class="cell form-cell">
                    <span class="cell__label">Cliente</span>
                    <input
                        x-model="vinculoSas.busqueda"
                        @input.debounce.300ms="buscarVinculoSas()"
                        inputmode="search"
                        autocomplete="off"
                        maxlength="80"
                        placeholder="Celular, DNI o nombre"
                        aria-label="Buscar cliente en SAS Imperio"
                    >
                </label>
                <template x-for="cliente in vinculoSas.resultados" :key="cliente.id">
                    <button
                        type="button"
                        class="cell"
                        :disabled="vinculoSas.guardando"
                        @click="elegirVinculoSas(cliente)"
                    >
                        <?php require __DIR__ . '/../parciales/sas_cliente_contenido.php'; ?>
                    </button>
                </template>
                <p class="group__footer footnote" x-show="vinculoSas.buscando">Buscando…</p>
                <p class="group__footer footnote" x-show="vinculoSas.mensaje" x-text="vinculoSas.mensaje"></p>
            </div>
            <p class="group__footer footnote">
                Elegí el cliente que corresponde a este contacto. Solo se guarda el vínculo en la Agenda; SAS no se
                modifica.
            </p>
        </div>
    </section>
</div>
