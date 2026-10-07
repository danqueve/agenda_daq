<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/push.php';

function cron_push_log(string $message): void
{
    $directory = __DIR__ . '/../storage/logs';
    if (!is_dir($directory)) {
        mkdir($directory, 0750, true);
    }
    file_put_contents($directory . '/push.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

if (!push_configurado()) {
    cron_push_log('VAPID no configurado; no se enviaron recordatorios.');
    exit(0);
}

$db = Db::get();
$dispositivos = $db->query('SELECT endpoint, p256dh, auth FROM push_suscripciones')->fetchAll();
if ($dispositivos === []) {
    cron_push_log('No hay dispositivos suscriptos.');
    exit(0);
}

$pendientes = $db->query(
    "SELECT c.id, c.nombre, c.producto_interes, c.proximo_contacto,
        (SELECT s.nota FROM seguimientos s WHERE s.contacto_id = c.id ORDER BY s.fecha DESC, s.id DESC LIMIT 1) AS ultima_nota
     FROM contactos c
     WHERE c.estado <> 'cerrada' AND c.proximo_contacto BETWEEN DATE_SUB(NOW(), INTERVAL 24 HOUR) AND NOW()
       AND NOT EXISTS (
           SELECT 1 FROM avisos_enviados a WHERE a.contacto_id = c.id AND a.proximo_contacto_ref = c.proximo_contacto
       )
     ORDER BY c.proximo_contacto ASC"
)->fetchAll();

$expirados = [];
if (count($pendientes) > 5) {
    [, $expirados] = push_enviar_a_dispositivos($dispositivos, [
        'title' => 'Agenda DAQ', 'body' => 'Tenés ' . count($pendientes) . ' recontactos pendientes.',
        'url' => APP_URL . '/', 'tag' => 'agenda-pendientes',
    ]);
} else {
    foreach ($pendientes as $contacto) {
        $producto = trim((string) ($contacto['producto_interes'] ?? ''));
        $nota = mb_substr(trim((string) ($contacto['ultima_nota'] ?? '')), 0, 80);
        $body = trim(implode(' — ', array_filter([$producto, $nota]))) ?: 'Tenés un recontacto pendiente.';
        [, $invalidos] = push_enviar_a_dispositivos($dispositivos, [
            'title' => 'Recontactar a ' . $contacto['nombre'], 'body' => $body,
            'url' => APP_URL . '/?contacto=' . (int) $contacto['id'], 'tag' => 'contacto-' . (int) $contacto['id'],
        ]);
        $expirados = array_merge($expirados, $invalidos);
    }
}

if ($pendientes !== []) {
    $insert = $db->prepare('INSERT IGNORE INTO avisos_enviados (contacto_id, proximo_contacto_ref) VALUES (:contacto_id, :proximo_contacto_ref)');
    foreach ($pendientes as $contacto) {
        $insert->execute(['contacto_id' => $contacto['id'], 'proximo_contacto_ref' => $contacto['proximo_contacto']]);
    }
    cron_push_log('Recordatorios procesados: ' . count($pendientes) . '.');
}
push_eliminar_endpoints($db, $expirados);

$hora = $db->query("SELECT valor FROM config WHERE clave = 'hora_resumen_diario'")->fetchColumn() ?: '08:30';
$ultimoResumen = $db->query("SELECT valor FROM config WHERE clave = 'ultimo_resumen_push'")->fetchColumn();
$hoy = date('Y-m-d');
if (date('H:i') === $hora && $ultimoResumen !== $hoy) {
    $paraHoy = (int) $db->query("SELECT COUNT(*) FROM contactos WHERE estado <> 'cerrada' AND DATE(proximo_contacto) = CURDATE()")->fetchColumn();
    $vencidos = (int) $db->query("SELECT COUNT(*) FROM contactos WHERE estado <> 'cerrada' AND proximo_contacto < NOW()")->fetchColumn();
    if ($paraHoy + $vencidos > 0) {
        [, $invalidos] = push_enviar_a_dispositivos($dispositivos, [
            'title' => 'Agenda DAQ', 'body' => "Hoy: {$paraHoy} para recontactar, {$vencidos} vencidos.",
            'url' => APP_URL . '/', 'tag' => 'agenda-resumen-' . $hoy,
        ]);
        push_eliminar_endpoints($db, $invalidos);
        $upsert = $db->prepare("INSERT INTO config (clave, valor) VALUES ('ultimo_resumen_push', :fecha) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $upsert->execute(['fecha' => $hoy]);
        cron_push_log('Resumen diario enviado.');
    }
}
