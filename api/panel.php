<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';

/** @return list<array<string, mixed>> */
function panel_contactos(PDO $db, string $condition, array $params = []): array
{
    $sql = "SELECT c.id, c.nombre, c.celular_norm, c.producto_interes, c.proximo_contacto, c.estado,
                (SELECT s.nota FROM seguimientos s WHERE s.contacto_id = c.id AND s.tipo = 'consulta'
                 ORDER BY s.fecha DESC, s.id DESC LIMIT 1) AS consulta
            FROM contactos c WHERE {$condition} ORDER BY c.proximo_contacto ASC, c.creado_en DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function panel_count(PDO $db, string $sql, array $params = []): int
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

$db = Db::get();
$vencidos = panel_contactos($db, "c.estado <> 'cerrada' AND c.proximo_contacto < NOW()");
$hoy = panel_contactos($db, "c.estado <> 'cerrada' AND c.proximo_contacto >= NOW() AND c.proximo_contacto < DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
$proximos = panel_contactos($db, "c.estado <> 'cerrada' AND c.proximo_contacto >= DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND c.proximo_contacto < DATE_ADD(CURDATE(), INTERVAL 8 DAY)");
$sinFecha = panel_contactos($db, "c.estado = 'pendiente' AND c.proximo_contacto IS NULL");

$agendados = panel_count($db, "SELECT COUNT(DISTINCT contacto_id) FROM (
    SELECT id AS contacto_id FROM contactos WHERE DATE(proximo_contacto) = CURDATE()
    UNION
    SELECT contacto_id FROM seguimientos
    WHERE tipo = 'recontacto' AND DATE(fecha) = CURDATE() AND DATE(proximo_anterior) = CURDATE()
) AS agenda_hoy");
$hechos = panel_count($db, "SELECT COUNT(DISTINCT contacto_id) FROM seguimientos
    WHERE tipo = 'recontacto' AND DATE(fecha) = CURDATE()
      AND proximo_anterior < DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
$cerrados = $db->query("SELECT c.motivo_cierre, COUNT(DISTINCT c.id) AS total
    FROM contactos c INNER JOIN seguimientos s ON s.contacto_id = c.id
    WHERE c.estado = 'cerrada' AND s.tipo = 'recontacto' AND s.proximo_asignado IS NULL
      AND s.fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    GROUP BY c.motivo_cierre")->fetchAll();
$cerradosMes = ['concreto' => 0, 'no_interesa' => 0, 'sin_respuesta' => 0];
foreach ($cerrados as $fila) {
    if (isset($cerradosMes[$fila['motivo_cierre']])) {
        $cerradosMes[$fila['motivo_cierre']] = (int) $fila['total'];
    }
}

$porDia = [];
foreach ($proximos as $contacto) {
    $dia = substr((string) $contacto['proximo_contacto'], 0, 10);
    $porDia[$dia][] = $contacto;
}

json_response([
    'ok' => true,
    'data' => [
        'anillo' => ['agendados_hoy' => $agendados, 'hechos_hoy' => min($hechos, $agendados)],
        'vencidos' => $vencidos,
        'hoy' => $hoy,
        'proximos' => $porDia,
        'sin_fecha' => $sinFecha,
        'contadores' => [
            'abiertos' => panel_count($db, "SELECT COUNT(*) FROM contactos WHERE estado <> 'cerrada'"),
            'cerrados_mes' => $cerradosMes,
        ],
    ],
]);
