<?php

declare(strict_types=1);

/**
 * Confirmación para eliminar un contacto. Lógica en assets/js/contactos.js.
 */
?>
<div class="sheet-backdrop" x-show="sheetEliminar" x-transition.opacity @keydown.escape.window="sheetEliminar = false" @click.self="sheetEliminar = false" x-cloak><section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true"><div class="sheet__grabber"></div><div class="sheet__body"><i class="alert-sheet__icon alert-sheet__icon--danger" data-lucide="trash-2"></i><h2 class="title2">¿Eliminar contacto?</h2><p class="subhead">Se eliminarán también sus consultas y su historial. Esta acción no se puede deshacer.</p><div class="alert-sheet__actions"><button class="btn-principal btn-principal--danger" type="button" @click="eliminarContacto()">Eliminar contacto</button><button class="btn-texto" type="button" @click="sheetEliminar = false">Cancelar</button></div></div></section></div>
