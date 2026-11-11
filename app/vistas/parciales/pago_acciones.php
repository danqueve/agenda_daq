<?php

declare(strict_types=1);

/**
 * Acciones de un compromiso, para usar dentro de un x-for cuya variable Alpine se llama `pago`:
 * Pagó / Parcial / Reprogramar en los pendientes, y WhatsApp con plantilla, llamar, "Más" (o
 * Deshacer, en los ya registrados). Se incluye en Hoy, en las tres vistas de Pagos y en el
 * panel del día del Calendario, así la botonera es una sola.
 *
 * @var string $variante 'texto' (todo con rótulo: tarjetas y panel), 'fila' (Pagó, Parcial y
 *                       Reprogramar con rótulo, el resto solo ícono) o 'tabla' (todo solo ícono).
 */
$variante = $variante ?? 'texto';
$rotuloContacto = static fn (string $texto): string => $variante === 'texto' ? '<span>' . $texto . '</span>' : '';
$rotuloPrincipal = static fn (string $texto): string => $variante === 'tabla' ? '' : '<span>' . $texto . '</span>';
$contacto = $variante === 'texto' ? 'pago-accion' : 'pago-accion pago-accion--icono';
$principal = $variante === 'tabla' ? 'pago-accion pago-accion--icono' : 'pago-accion';
?>
<div class="pago-acciones pago-acciones--<?= $variante ?>">
    <div class="pago-acciones__grupo pago-acciones__grupo--principal" x-show="pago.estado === 'pendiente'">
        <button type="button" class="<?= $principal ?> pago-accion--pago" :disabled="pagoRapidoOcupado" @click="pagoRapidoPagado(pago)" title="Pagó: registrar el pago completo" :aria-label="`Pagó: registrar el pago de ${formatoMonto(pago.monto)} de ${pago.contacto_nombre}`"><i data-lucide="check"></i><?= $rotuloPrincipal('Pagó') ?></button>
        <button type="button" class="<?= $principal ?>" @click="abrirPagoRapido(pago, 'parcial')" title="Parcial: pagó una parte" :aria-label="`Parcial: registrar un pago parcial de ${pago.contacto_nombre}`"><i data-lucide="circle-dollar-sign"></i><?= $rotuloPrincipal('Parcial') ?></button>
        <button type="button" class="<?= $principal ?>" @click="abrirPagoRapido(pago, 'reprogramar')" title="Reprogramar: paga otro día" :aria-label="`Reprogramar el pago de ${pago.contacto_nombre}`"><i data-lucide="calendar-clock"></i><?= $rotuloPrincipal('Reprogramar') ?></button>
    </div>
    <div class="pago-acciones__grupo">
        <a class="<?= $contacto ?> pago-accion--whatsapp" x-show="celularValidoPago(pago)" target="_blank" rel="noopener" :href="urlWhatsapp(pago)" @click="avisarWhatsapp(pago)" :title="`WhatsApp a ${pago.contacto_nombre}`" :aria-label="`Enviar WhatsApp a ${pago.contacto_nombre}`"><i data-lucide="message-circle"></i><?= $rotuloContacto('WhatsApp') ?></a>
        <button type="button" class="<?= $contacto ?> pago-accion--whatsapp" x-show="!celularValidoPago(pago)" disabled :title="`WhatsApp no disponible: ${pago.contacto_nombre} no tiene un celular válido`" :aria-label="`WhatsApp no disponible: ${pago.contacto_nombre} no tiene un celular válido`"><i data-lucide="message-circle"></i><?= $rotuloContacto('WhatsApp') ?></button>
        <a class="<?= $contacto ?> pago-accion--llamar" x-show="celularValidoPago(pago)" :href="`tel:+${pago.contacto_celular_norm}`" :title="`Llamar a ${pago.contacto_nombre}`" :aria-label="`Llamar a ${pago.contacto_nombre}`"><i data-lucide="phone"></i><?= $rotuloContacto('Llamar') ?></a>
        <button type="button" class="<?= $contacto ?>" x-show="pago.estado === 'pendiente'" aria-haspopup="dialog" @click="abrirPagoRapido(pago, 'mas')" title="Más acciones" :aria-label="`Más acciones para ${pago.contacto_nombre}`"><i data-lucide="ellipsis"></i><?= $rotuloContacto('Más') ?></button>
        <button type="button" class="<?= $contacto ?>" x-show="pago.estado !== 'pendiente'" @click="reabrirCompromiso(pago)" title="Deshacer el registro" :aria-label="`Deshacer el registro de ${pago.contacto_nombre}`"><i data-lucide="rotate-ccw"></i><?= $rotuloContacto('Deshacer') ?></button>
    </div>
</div>
