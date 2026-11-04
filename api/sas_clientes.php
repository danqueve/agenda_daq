<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';
require_once __DIR__ . '/../app/sas.php';

if (api_method() !== 'GET') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

if (!SasDb::habilitado()) {
    json_response(['ok' => true, 'data' => ['habilitado' => false, 'items' => []]]);
}

$texto = api_string($_GET, 'q', 80);
if (mb_strlen($texto) < 3) {
    json_response(['ok' => true, 'data' => ['habilitado' => true, 'items' => []]]);
}

$sas = SasDb::get();
if ($sas === null) {
    json_response(['ok' => false, 'error' => 'No se pudo consultar SAS Imperio.'], 503);
}

try {
    $items = sas_buscar_clientes($sas, $texto);
} catch (Throwable $error) {
    error_log('SAS Imperio: error en la búsqueda: ' . $error->getMessage());
    json_response(['ok' => false, 'error' => 'No se pudo consultar SAS Imperio.'], 503);
}

json_response(['ok' => true, 'data' => ['habilitado' => true, 'items' => $items]]);
