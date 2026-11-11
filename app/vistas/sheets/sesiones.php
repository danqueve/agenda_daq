<?php

declare(strict_types=1);

/**
 * Confirmación «Cerrar sesiones guardadas». Lógica en assets/js/ajustes.js.
 */
?>
<div class="sheet-backdrop" x-show="sheetSesiones" x-transition.opacity @keydown.escape.window="sheetSesiones = false" @click.self="sheetSesiones = false" x-cloak><section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true"><div class="sheet__grabber"></div><div class="sheet__body"><i class="alert-sheet__icon" data-lucide="monitor-off"></i><h2 class="title2">¿Cerrar sesiones guardadas?</h2><p class="subhead">Los otros dispositivos tendrán que iniciar sesión nuevamente. Este dispositivo seguirá abierto.</p><div class="alert-sheet__actions"><button class="btn-principal" type="button" :disabled="guardandoAjuste" @click="cerrarSesiones()">Cerrar sesiones</button><button class="btn-texto" type="button" @click="sheetSesiones = false">Cancelar</button></div></div></section></div>
