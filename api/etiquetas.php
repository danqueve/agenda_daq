<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';

$method = api_method();
$db = Db::get();

if ($method === 'GET') {
    $stmt = $db->query('SELECT id, nombre, color FROM etiquetas ORDER BY nombre ASC');
    json_response(['ok' => true, 'data' => $stmt->fetchAll()]);
}

$body = api_body();
api_require_write();

if ($method === 'POST') {
    $nombre = api_string($body, 'nombre', 50);
    $color = api_string($body, 'color', 20) ?: '#8E8E93';
    if ($nombre === '') {
        json_response(['ok' => false, 'error' => 'Escribí un nombre para la etiqueta.'], 422);
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        json_response(['ok' => false, 'error' => 'El color de la etiqueta no es válido.'], 422);
    }

    try {
        $stmt = $db->prepare('INSERT INTO etiquetas (nombre, color) VALUES (:nombre, :color)');
        $stmt->execute(['nombre' => $nombre, 'color' => $color]);
        json_response(['ok' => true, 'data' => ['id' => (int) $db->lastInsertId(), 'nombre' => $nombre, 'color' => $color]], 201);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            $stmt = $db->prepare('SELECT id, nombre, color FROM etiquetas WHERE nombre = :nombre');
            $stmt->execute(['nombre' => $nombre]);
            json_response(['ok' => true, 'data' => $stmt->fetch(), 'existente' => true]);
        }
        throw $error;
    }
}

$id = api_integer($_GET['id'] ?? $body['id'] ?? null);
if ($id === 0) {
    json_response(['ok' => false, 'error' => 'Etiqueta no encontrada.'], 404);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $nombre = api_string($body, 'nombre', 50);
    $color = api_string($body, 'color', 20);
    if ($nombre === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        json_response(['ok' => false, 'error' => 'Revisá el nombre y color de la etiqueta.'], 422);
    }
    $stmt = $db->prepare('UPDATE etiquetas SET nombre = :nombre, color = :color WHERE id = :id');
    $stmt->execute(['id' => $id, 'nombre' => $nombre, 'color' => $color]);
    json_response(['ok' => true, 'data' => ['id' => $id, 'nombre' => $nombre, 'color' => $color]]);
}

if ($method === 'DELETE') {
    $stmt = $db->prepare('DELETE FROM etiquetas WHERE id = :id');
    $stmt->execute(['id' => $id]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
