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

$db = Db::get();
$stmt = $db->prepare(
    "SELECT c.id, c.nombre, c.celular_norm, c.producto_interes, c.proximo_contacto, c.estado,
        (SELECT s.nota FROM seguimientos s WHERE s.contacto_id = c.id AND s.tipo = 'consulta'
         ORDER BY s.fecha DESC, s.id DESC LIMIT 1) AS consulta
     FROM contactos c
     WHERE c.estado <> 'cerrada' AND c.proximo_contacto >= :desde AND c.proximo_contacto < DATE_ADD(:hasta, INTERVAL 1 DAY)
     ORDER BY c.proximo_contacto ASC, c.creado_en DESC"
);
$stmt->execute(['desde' => $from . ' 00:00:00', 'hasta' => $to . ' 00:00:00']);
$items = $stmt->fetchAll();
$porDia = [];
foreach ($items as $item) {
    $day = substr((string) $item['proximo_contacto'], 0, 10);
    $porDia[$day] ??= [];
    $porDia[$day][] = $item;
}

$puntos = [];
foreach ($porDia as $day => $contactos) {
    $puntos[$day] = count($contactos);
}

json_response(['ok' => true, 'data' => ['desde' => $from, 'hasta' => $to, 'por_dia' => $porDia, 'puntos' => $puntos]]);
