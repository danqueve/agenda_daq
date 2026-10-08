<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';

api_require_admin();

$db = Db::get();
$method = api_method();

if ($method === 'GET') {
    $stmt = $db->query('SELECT id, usuario, rol, creado_en FROM usuarios ORDER BY usuario ASC');
    json_response(['ok' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    api_require_write();
    $body = api_body();
    $usuario = api_string($body, 'usuario', 50);
    $password = (string) ($body['password'] ?? '');
    $rol = api_string($body, 'rol', 20);

    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $usuario)) {
        json_response(['ok' => false, 'error' => 'El usuario debe tener entre 3 y 50 caracteres: letras, números, punto, guion o guion bajo.'], 422);
    }
    if (!in_array($rol, [ROL_ADMIN, ROL_SUPERVISOR], true)) {
        json_response(['ok' => false, 'error' => 'El rol indicado no es válido.'], 422);
    }
    if (mb_strlen($password) < 10) {
        json_response(['ok' => false, 'error' => 'La contraseña debe tener al menos 10 caracteres.'], 422);
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO usuarios (usuario, password_hash, rol) VALUES (:usuario, :password_hash, :rol)'
        );
        $stmt->execute([
            'usuario' => $usuario,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'rol' => $rol,
        ]);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            json_response(['ok' => false, 'error' => 'Ya existe un usuario con ese nombre.'], 422);
        }
        throw $error;
    }

    json_response(['ok' => true, 'data' => [
        'id' => (int) $db->lastInsertId(),
        'usuario' => $usuario,
        'rol' => $rol,
    ]], 201);
}

if ($method === 'DELETE') {
    api_require_write();
    $id = api_integer($_GET['id'] ?? null);
    if ($id === 0) {
        json_response(['ok' => false, 'error' => 'El usuario indicado no es válido.'], 422);
    }
    if ($id === usuario_actual_id()) {
        json_response(['ok' => false, 'error' => 'No podés eliminar tu propia cuenta.'], 422);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT id, rol FROM usuarios WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $usuario = $stmt->fetch();
        if (!$usuario) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'El usuario no existe.'], 404);
        }

        if ($usuario['rol'] === ROL_ADMIN) {
            $admins = $db->query("SELECT id FROM usuarios WHERE rol = 'admin' FOR UPDATE")->fetchAll();
            if (count($admins) <= 1) {
                $db->rollBack();
                json_response(['ok' => false, 'error' => 'Debe existir al menos un administrador.'], 422);
            }
        }

        $delete = $db->prepare('DELETE FROM usuarios WHERE id = :id');
        $delete->execute(['id' => $id]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
