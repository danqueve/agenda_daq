<?php

declare(strict_types=1);

/**
 * Aviso «Este número ya está cargado». Lógica en assets/js/contactos.js.
 */
?>
<div class="sheet-backdrop" x-show="sheetDuplicado" x-transition.opacity @keydown.escape.window="sheetDuplicado = false" @click.self="sheetDuplicado = false" x-cloak>
    <section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true" aria-labelledby="duplicado-titulo"><div class="sheet__grabber"></div><div class="sheet__body"><i class="alert-sheet__icon" data-lucide="copy"></i><h2 id="duplicado-titulo" class="title2">Este número ya está cargado</h2><p class="subhead">Se lo agregará como una nueva consulta a <strong x-text="duplicadoPendiente?.nombre"></strong>.</p><div class="alert-sheet__actions"><button type="button" class="btn-principal" @click="guardarContacto(true)">Agregar consulta</button><button type="button" class="btn-texto" @click="sheetDuplicado = false">Cancelar</button></div></div></section>
</div>
