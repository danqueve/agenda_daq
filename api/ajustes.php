<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';

if (api_method() === 'GET') {
    json_response(['ok' => true, 'data' => ['usuario_id' => usuario_actual_id()]]);
}

if (api_method() !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$body = api_body();
api_require_write();
$usuarioId = usuario_actual_id();
$action = api_string($body, 'accion', 30);

if ($action === 'cambiar_password') {
    $actual = (string) ($body['password_actual'] ?? '');
    $nueva = (string) ($body['password_nueva'] ?? '');
    if (strlen($nueva) < 10) {
        json_response(['ok' => false, 'error' => 'La nueva contraseña debe tener al menos 10 caracteres.'], 422);
    }
    $db = Db::get();
    $stmt = $db->prepare('SELECT password_hash FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $usuarioId]);
    $hash = (string) $stmt->fetchColumn();
    if ($hash === '' || !password_verify($actual, $hash)) {
        json_response(['ok' => false, 'error' => 'La contraseña actual no es correcta.'], 422);
    }
    $update = $db->prepare('UPDATE usuarios SET password_hash = :password_hash WHERE id = :id');
    $update->execute(['id' => $usuarioId, 'password_hash' => password_hash($nueva, PASSWORD_DEFAULT)]);
    revocar_tokens_recordar((int) $usuarioId);
    borrar_cookie_recordar();
    json_response(['ok' => true]);
}

if ($action === 'cerrar_sesiones') {
    revocar_tokens_recordar((int) $usuarioId);
    borrar_cookie_recordar();
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Acción no válida.'], 422);
