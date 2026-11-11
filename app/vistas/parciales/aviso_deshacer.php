<?php

declare(strict_types=1);

/**
 * Aviso con acción («Pago registrado · Deshacer»): dura 6 segundos. Lógica en assets/js/pagos_rapido.js.
 */
?>
        <div class="toast-accion" role="status" aria-live="polite" x-show="toastAccion.visible" x-transition.opacity x-cloak @mouseenter="pausarToastAccion()" @mouseleave="reanudarToastAccion()" @focusin="pausarToastAccion()" @focusout="reanudarToastAccion()">
            <i data-lucide="circle-check"></i>
            <span class="toast-accion__texto" x-text="toastAccion.texto"></span>
            <button type="button" class="toast-accion__boton" @click="ejecutarToastAccion()" x-text="toastAccion.etiqueta"></button>
        </div>
