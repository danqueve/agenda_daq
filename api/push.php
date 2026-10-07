<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';
require_once __DIR__ . '/../app/push.php';

$db = Db::get();
$usuarioId = (int) usuario_actual_id();

if (api_method() === 'GET') {
    $stmt = $db->prepare('SELECT id, dispositivo, creado_en, ultimo_uso FROM push_suscripciones WHERE usuario_id = :usuario_id ORDER BY creado_en DESC');
    $stmt->execute(['usuario_id' => $usuarioId]);
    $hora = $db->query("SELECT valor FROM config WHERE clave = 'hora_resumen_diario'")->fetchColumn() ?: '08:30';
    json_response(['ok' => true, 'data' => ['dispositivos' => $stmt->fetchAll(), 'hora_resumen' => $hora, 'configurado' => push_configurado(), 'vapid_public' => VAPID_PUBLIC]]);
}

if (api_method() !== 'POST' && api_method() !== 'DELETE') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$body = api_body();
api_require_write();
$accion = api_string($body, 'accion', 30);

if ($accion === 'suscribir') {
    $suscripcion = $body['suscripcion'] ?? null;
    if (!is_array($suscripcion)) {
        json_response(['ok' => false, 'error' => 'La suscripción push no es válida.'], 422);
    }
    $endpoint = api_string($suscripcion, 'endpoint', 500);
    $keys = $suscripcion['keys'] ?? [];
    $p256dh = is_array($keys) ? api_string($keys, 'p256dh', 255) : '';
    $auth = is_array($keys) ? api_string($keys, 'auth', 255) : '';
    if (!filter_var($endpoint, FILTER_VALIDATE_URL) || $p256dh === '' || $auth === '') {
        json_response(['ok' => false, 'error' => 'La suscripción push está incompleta.'], 422);
    }
    $dispositivo = api_string($body, 'dispositivo', 100) ?: 'Este dispositivo';
    $stmt = $db->prepare(
        'INSERT INTO push_suscripciones (usuario_id, endpoint, p256dh, auth, dispositivo, ultimo_uso)
         VALUES (:usuario_id, :endpoint, :p256dh, :auth, :dispositivo, NOW())
         ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), p256dh = VALUES(p256dh), auth = VALUES(auth), dispositivo = VALUES(dispositivo), ultimo_uso = NOW()'
    );
    $stmt->execute([
        'usuario_id' => $usuarioId,
        'endpoint' => $endpoint,
        'p256dh' => $p256dh,
        'auth' => $auth,
        'dispositivo' => $dispositivo,
    ]);
    json_response(['ok' => true]);
}

if ($accion === 'eliminar') {
    $id = api_integer($body['id'] ?? null);
    $stmt = $db->prepare('DELETE FROM push_suscripciones WHERE id = :id AND usuario_id = :usuario_id');
    $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
    json_response(['ok' => true]);
}

if ($accion === 'hora_resumen') {
    $hora = api_string($body, 'hora', 5);
    if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $hora)) {
        json_response(['ok' => false, 'error' => 'Elegí una hora válida.'], 422);
    }
    $stmt = $db->prepare("INSERT INTO config (clave, valor) VALUES ('hora_resumen_diario', :hora) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $stmt->execute(['hora' => $hora]);
    json_response(['ok' => true]);
}

if ($accion === 'prueba') {
    if (!push_configurado()) {
        json_response(['ok' => false, 'error' => 'Faltan las claves VAPID en .env.'], 422);
    }
    $stmt = $db->prepare('SELECT endpoint, p256dh, auth FROM push_suscripciones WHERE usuario_id = :usuario_id');
    $stmt->execute(['usuario_id' => $usuarioId]);
    $dispositivos = $stmt->fetchAll();
    if ($dispositivos === []) {
        json_response(['ok' => false, 'error' => 'Activá las notificaciones en este dispositivo primero.'], 422);
    }
    [$enviados, $expirados] = push_enviar_a_dispositivos($dispositivos, [
        'title' => 'Agenda DAQ', 'body' => 'Las notificaciones están funcionando.', 'url' => APP_URL . '/', 'tag' => 'agenda-prueba',
    ]);
    push_eliminar_endpoints($db, $expirados);
    json_response(['ok' => true, 'data' => ['enviados' => $enviados]]);
}

json_response(['ok' => false, 'error' => 'Acción no válida.'], 422);
