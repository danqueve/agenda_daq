<?php

declare(strict_types=1);

/**
 * Contenido de la fila de un cliente de SAS Imperio dentro de un botón `.cell`, para usar dentro de un x-for cuya
 * variable Alpine se llama `cliente`: iniciales, nombre, DNI / teléfono / localidad y el chip Moroso / Inactivo. Lo
 * usan «Nueva consulta» (buscar en SAS) y «Vincular con SAS»; el botón y su @click son de cada hoja.
 */
?>
<span class="avatar" :style="`background:${colorAvatar(cliente.nombre)}`" x-text="iniciales(cliente.nombre)"></span>
<span class="cell__content">
    <span class="cell__title" x-text="cliente.nombre"></span>
    <span
        class="cell__subtitle"
        x-text="[cliente.dni && `DNI ${cliente.dni}`, cliente.telefono, cliente.localidad].filter(Boolean).join(' · ')"
    ></span>
</span>
<span
    class="chip-estado"
    x-show="cliente.estado && cliente.estado !== 'ACTIVO'"
    x-text="cliente.estado === 'MOROSO' ? 'Moroso' : 'Inactivo'"
></span>
