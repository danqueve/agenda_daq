<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';
require_once __DIR__ . '/../app/sas.php';

/*
 * Resumen de SAS Imperio de uno o varios contactos de la Agenda (solo lectura, con sesión).
 *   GET ?contacto_id=N               un contacto
 *   GET ?contacto_ids=1,2,3          varios, con UNA consulta agrupada a SAS (máx. 100)
 *   &actualizar=1                    ignora el caché de 10 minutos (botón «Actualizar» de la ficha)
 * Si SAS no responde, la Agenda sigue igual: `disponible` pasa a false y no se da ningún detalle técnico.
 */
if (api_method() !== 'GET') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$ids = [];
if (isset($_GET['contacto_ids'])) {
    $ids = array_map('intval', explode(',', api_string($_GET, 'contacto_ids', 1200)));
}
if (isset($_GET['contacto_id'])) {
    $ids[] = api_integer($_GET['contacto_id']);
}
$ids = array_slice(array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0))), 0, 100);

try {
    $resultado = sas_resumenes_de_contactos(Db::get(), $ids, ($_GET['actualizar'] ?? '') === '1');
} catch (Throwable $error) {
    error_log('SAS Imperio: error en api/sas_resumen.php: ' . $error->getMessage());
    $resultado = ['habilitado' => SasDb::habilitado(), 'disponible' => false, 'items' => []];
}

// Siempre un objeto JSON {contacto_id: resumen}, aun vacío.
$resultado['items'] = (object) $resultado['items'];
json_response(['ok' => true, 'data' => $resultado]);
