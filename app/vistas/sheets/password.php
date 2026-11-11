<?php

declare(strict_types=1);

/**
 * Hoja «Cambiar contraseña». Lógica en assets/js/ajustes.js.
 */
?>
<div
    class="sheet-backdrop"
    x-show="sheetPassword"
    x-transition.opacity
    @keydown.escape.window="sheetPassword = false"
    @click.self="sheetPassword = false"
    x-cloak
>
    <form class="sheet sheet--media" @submit.prevent="cambiarPassword()">
        <div class="sheet__grabber"></div>
        <header class="sheet__header">
            <button class="btn-texto" type="button" @click="sheetPassword = false">Cancelar</button>
            <h2 class="sheet__title">Cambiar contraseña</h2>
            <button class="btn-texto btn-texto--negrita" type="submit" :disabled="guardandoAjuste">Guardar</button>
        </header>
        <div class="sheet__body">
            <div class="group form-group">
                <label class="cell form-cell">
                    <span class="cell__label">Actual</span>
                    <input type="password" x-model="formularioPassword.actual" autocomplete="current-password" required>
                </label>
                <label class="cell form-cell">
                    <span class="cell__label">Nueva</span>
                    <input
                        type="password"
                        x-model="formularioPassword.nueva"
                        autocomplete="new-password"
                        minlength="10"
                        required
                    >
                </label>
                <label class="cell form-cell">
                    <span class="cell__label">Repetir</span>
                    <input
                        type="password"
                        x-model="formularioPassword.confirmacion"
                        autocomplete="new-password"
                        minlength="10"
                        required
                    >
                </label>
            </div>
            <p class="group__footer footnote">
                Usá al menos 10 caracteres. Al cambiarla se cerrarán las sesiones guardadas.
            </p>
        </div>
    </form>
</div>
