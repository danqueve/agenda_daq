<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';

function agenda_fecha(string $value, string $fallback): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value ?: $fallback);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
        json_response(['ok' => false, 'error' => 'El rango de fechas no es válido.'], 422);
    }
    return $date->format('Y-m-d');
}

$from = agenda_fecha((string) ($_GET['desde'] ?? ''), date('Y-m-d'));
$to = agenda_fecha((string) ($_GET['hasta'] ?? ''), date('Y-m-d', strtotime('+6 days')));
if ($to < $from || (strtotime($to) - strtotime($from)) > 41 * 86400) {
    json_response(['ok' => false, 'error' => 'Elegí un rango de hasta 42 días.'], 422);
}

$desde = $from . ' 00:00:00';
$hasta = $to . ' 00:00:00';

$db = Db::get();

// Contactos abiertos con próximo contacto en el rango (vencido/hoy/próximo
// según la hora actual, calculado más abajo).
$stmtAbiertos = $db->prepare(
    "SELECT c.id, c.nombre, c.celular, c.celular_norm, c.producto_interes, c.localidad, c.proximo_contacto AS fecha_evento, c.estado,
        (SELECT s.nota FROM seguimientos s WHERE s.contacto_id = c.id AND s.tipo = 'consulta'
         ORDER BY s.fecha DESC, s.id DESC LIMIT 1) AS consulta
     FROM contactos c
     WHERE c.estado <> 'cerrada' AND c.proximo_contacto >= :desde AND c.proximo_contacto < DATE_ADD(:hasta, INTERVAL 1 DAY)"
);
$stmtAbiertos->execute(['desde' => $desde, 'hasta' => $hasta]);
$abiertos = $stmtAbiertos->fetchAll();

// Contactos concretados: el calendario los muestra en verde el día en que
// se cerraron (fecha del seguimiento de cierre), no en una fecha futura.
$stmtConcretados = $db->prepare(
    "SELECT c.id, c.nombre, c.celular, c.celular_norm, c.producto_interes, c.localidad, s.fecha AS fecha_evento, c.estado,
        (SELECT s2.nota FROM seguimientos s2 WHERE s2.contacto_id = c.id AND s2.tipo = 'consulta'
         ORDER BY s2.fecha DESC, s2.id DESC LIMIT 1) AS consulta
     FROM contactos c
     INNER JOIN seguimientos s ON s.contacto_id = c.id
     WHERE c.estado = 'cerrada' AND c.motivo_cierre = 'concreto'
       AND s.tipo = 'recontacto' AND s.proximo_asignado IS NULL
       AND s.fecha >= :desde AND s.fecha < DATE_ADD(:hasta, INTERVAL 1 DAY)"
);
$stmtConcretados->execute(['desde' => $desde, 'hasta' => $hasta]);
$concretados = $stmtConcretados->fetchAll();

$ahora = date('Y-m-d H:i:s');
$finHoy = date('Y-m-d 00:00:00', strtotime('+1 day'));

$items = [];
foreach ($abiertos as $item) {
    if ($item['fecha_evento'] < $ahora) {
        $item['tipo_evento'] = 'vencido';
    } elseif ($item['fecha_evento'] < $finHoy) {
        $item['tipo_evento'] = 'hoy';
    } else {
        $item['tipo_evento'] = 'proximo';
    }
    $items[] = $item;
}
foreach ($concretados as $item) {
    $item['tipo_evento'] = 'hecho';
    $items[] = $item;
}

usort($items, fn (array $a, array $b) => $a['fecha_evento'] <=> $b['fecha_evento']);

$porDia = [];
foreach ($items as $item) {
    $day = substr((string) $item['fecha_evento'], 0, 10);
    $porDia[$day] ??= [];
    $porDia[$day][] = $item;
}

$puntos = [];
foreach ($porDia as $day => $contactos) {
    $puntos[$day] = count($contactos);
}

json_response(['ok' => true, 'data' => ['desde' => $from, 'hasta' => $to, 'por_dia' => $porDia, 'puntos' => $puntos]]);
