<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';
require_once __DIR__ . '/../app/compromisos.php';

if (api_method() === 'GET') {
    json_response(['ok' => true, 'data' => [
        'usuario_id' => usuario_actual_id(),
        'plantillas_whatsapp' => compromiso_plantillas_whatsapp(Db::get()),
        'plantillas_whatsapp_defecto' => COMPROMISO_PLANTILLAS_WHATSAPP,
        'plantilla_variables' => COMPROMISO_PLANTILLA_VARIABLES,
    ]]);
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

// Plantillas del recordatorio por WhatsApp: las usan todos, pero solo el admin las edita.
// Una plantilla vacía vuelve al texto por defecto (se borra la fila de config).
if ($action === 'guardar_plantillas') {
    api_require_admin();
    $enviadas = is_array($body['plantillas'] ?? null) ? $body['plantillas'] : [];
    $cambios = [];
    foreach (array_keys(COMPROMISO_PLANTILLAS_WHATSAPP) as $tipo) {
        if (!array_key_exists($tipo, $enviadas)) {
            continue;
        }
        $texto = trim((string) $enviadas[$tipo]);
        if (mb_strlen($texto) > 500) {
            json_response(['ok' => false, 'error' => 'Cada mensaje puede tener hasta 500 caracteres.'], 422);
        }
        preg_match_all('/\{([^{}]*)\}/', $texto, $variables);
        foreach ($variables[1] as $variable) {
            if (!in_array($variable, COMPROMISO_PLANTILLA_VARIABLES, true)) {
                json_response(['ok' => false, 'error' => 'La variable {' . $variable . '} no existe. Podés usar: {' . implode('}, {', COMPROMISO_PLANTILLA_VARIABLES) . '}.'], 422);
            }
        }
        $cambios[$tipo] = $texto;
    }

    $db = Db::get();
    $guardar = $db->prepare('INSERT INTO config (clave, valor) VALUES (:clave, :valor) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
    $borrar = $db->prepare('DELETE FROM config WHERE clave = :clave');
    $db->beginTransaction();
    try {
        foreach ($cambios as $tipo => $texto) {
            if ($texto === '' || $texto === COMPROMISO_PLANTILLAS_WHATSAPP[$tipo]) {
                $borrar->execute(['clave' => 'wa_plantilla_' . $tipo]);
            } else {
                $guardar->execute(['clave' => 'wa_plantilla_' . $tipo, 'valor' => $texto]);
            }
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
    json_response(['ok' => true, 'data' => ['plantillas_whatsapp' => compromiso_plantillas_whatsapp($db)]]);
}

json_response(['ok' => false, 'error' => 'Acción no válida.'], 422);
