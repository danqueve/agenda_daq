<?php

declare(strict_types=1);

/**
 * Pie de una tarjeta de nota, para usar dentro de un x-for cuya variable Alpine se llama `nota`: contacto vinculado
 * y fecha. Lo usan las notas fijadas y las recientes de la pantalla Notas.
 *
 * @var string $metodoFecha Método Alpine que da el texto de la fecha: `fechaNotaFijada` o `fechaNotaReciente`.
 */
?>
<div class="nota-card__pie">
    <span class="contacto-chip" x-show="nota.contacto_nombre">
        <span
            class="avatar"
            :style="`background:${colorAvatar(nota.contacto_nombre)}`"
            x-text="iniciales(nota.contacto_nombre)"
        ></span>
        <span x-text="nota.contacto_nombre"></span>
    </span>
    <span class="nota-card__fecha tabular" x-text="<?= e($metodoFecha) ?>(nota.actualizado_en)"></span>
</div>
