<?php

declare(strict_types=1);

/**
 * Confirmación para eliminar una nota. Lógica en assets/js/notas.js.
 */
?>
<div
    class="sheet-backdrop"
    x-show="eliminandoNota"
    @keydown.escape.window="eliminandoNota = false"
    @click.self="eliminandoNota = false"
    x-cloak
>
    <section class="sheet sheet--media alert-sheet" role="alertdialog" aria-modal="true">
        <div class="sheet__grabber"></div>
        <div class="sheet__body">
            <i class="alert-sheet__icon alert-sheet__icon--danger" data-lucide="trash-2"></i>
            <h2 class="title2">¿Eliminar nota?</h2>
            <p class="subhead">Esta acción no se puede deshacer.</p>
            <div class="alert-sheet__actions">
                <button class="btn-principal btn-principal--danger" type="button" @click="eliminarNotaActual()">
                    Eliminar nota
                </button>
                <button class="btn-texto" type="button" @click="eliminandoNota = false">Cancelar</button>
            </div>
        </div>
    </section>
</div>
