<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';
require_once __DIR__ . '/../app/sas.php';

/*
 * Vincula o desvincula un contacto de la Agenda con un cliente de SAS Imperio.
 * Solo se escribe en la base de la Agenda (contactos.sas_cliente_id y, si faltaba, contactos.dni);
 * de SAS únicamente se lee que el cliente exista.
 *   POST {accion: 'vincular', contacto_id, sas_cliente_id}
 *   POST {accion: 'desvincular', contacto_id}
 */
if (api_method() !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$body = api_body();
api_require_write();
$accion = api_string($body, 'accion', 20);
$contactoId = api_integer($body['contacto_id'] ?? null);

$db = Db::get();
$contacto = $db->prepare('SELECT id, nombre, sas_cliente_id FROM contactos WHERE id = :id');
$contacto->execute(['id' => $contactoId]);
if (!$contacto->fetch()) {
    json_response(['ok' => false, 'error' => 'El contacto no existe.'], 404);
}

if ($accion === 'desvincular') {
    $db->prepare('UPDATE contactos SET sas_cliente_id = NULL WHERE id = :id')->execute(['id' => $contactoId]);
    json_response(['ok' => true, 'data' => ['sas_cliente_id' => null]]);
}

if ($accion !== 'vincular') {
    json_response(['ok' => false, 'error' => 'Acción no válida.'], 422);
}

$sasId = api_integer($body['sas_cliente_id'] ?? null);
if ($sasId === 0) {
    json_response(['ok' => false, 'error' => 'Elegí un cliente de SAS Imperio.'], 422);
}
if (!SasDb::habilitado()) {
    json_response(['ok' => false, 'error' => 'La integración con SAS Imperio no está activa.'], 503);
}
$sas = SasDb::get();
if ($sas === null) {
    json_response(['ok' => false, 'error' => 'No se pudo consultar SAS Imperio.'], 503);
}

try {
    $cliente = $sas->prepare('SELECT id, dni FROM ic_clientes WHERE id = :id');
    $cliente->execute(['id' => $sasId]);
    $cliente = $cliente->fetch();
} catch (Throwable $error) {
    error_log('SAS Imperio: error al vincular: ' . $error->getMessage());
    json_response(['ok' => false, 'error' => 'No se pudo consultar SAS Imperio.'], 503);
}
if (!$cliente) {
    json_response(['ok' => false, 'error' => 'Ese cliente no existe en SAS Imperio.'], 422);
}

$otro = $db->prepare('SELECT nombre FROM contactos WHERE sas_cliente_id = :sas AND id <> :id LIMIT 1');
$otro->execute(['sas' => $sasId, 'id' => $contactoId]);
$nombreOtro = $otro->fetchColumn();
if ($nombreOtro !== false) {
    json_response(['ok' => false, 'error' => 'Ese cliente de SAS ya está vinculado al contacto «' . $nombreOtro . '».'], 409);
}

$dni = substr(sas_dni_digitos((string) ($cliente['dni'] ?? '')) ?? '', 0, 15);
$db->prepare(
    "UPDATE contactos SET sas_cliente_id = :sas, dni = COALESCE(NULLIF(dni, ''), NULLIF(:dni, '')) WHERE id = :id"
)->execute(['sas' => $sasId, 'dni' => $dni, 'id' => $contactoId]);

json_response(['ok' => true, 'data' => ['sas_cliente_id' => $sasId]]);
