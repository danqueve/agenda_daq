<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';
require_once __DIR__ . '/../app/compromisos.php';

const COMPROMISOS_POR_PAGINA = 30;

/** @return array<string, mixed>|null */
function compromiso_buscar(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT ' . COMPROMISO_COLUMNAS_SQL . ', ' . COMPROMISO_SITUACION_SQL . ' AS situacion,
                k.nombre AS contacto_nombre, k.celular AS contacto_celular, k.celular_norm AS contacto_celular_norm,
                k.estado AS contacto_estado
         FROM compromisos c INNER JOIN contactos k ON k.id = c.contacto_id
         WHERE c.id = :id'
    );
    $stmt->execute(['id' => $id]);

    return $stmt->fetch() ?: null;
}

/**
 * Busca el contacto por celular o lo crea. Un deudor nuevo ya es cliente
 * (compró y quedó debiendo), así que entra cerrado/concretó: no aparece como
 * lead "sin fecha" en Hoy.
 *
 * @param array<string, mixed> $nuevo
 */
function compromiso_contacto_para(PDO $db, array $nuevo): int
{
    $nombre = api_string($nuevo, 'nombre', 120);
    $celular = api_string($nuevo, 'celular', 30);
    if ($nombre === '' || $celular === '') {
        json_response(['ok' => false, 'error' => 'Para un contacto nuevo hacen falta nombre y celular.'], 422);
    }
    $celularNorm = normalizar_celular($celular);
    if (!preg_match('/^549\\d{10}$/', $celularNorm)) {
        json_response(['ok' => false, 'error' => 'Ingresá un celular argentino válido.'], 422);
    }

    // Vínculo opcional con SAS Imperio (cuando se eligió un cliente de SAS).
    $dni = preg_replace('/\D+/', '', api_string($nuevo, 'dni', 20)) ?: null;
    $dni = $dni === null ? null : substr($dni, 0, 15);
    $sasId = api_integer($nuevo['sas_cliente_id'] ?? null) ?: null;

    $buscar = $db->prepare('SELECT id FROM contactos WHERE celular_norm = :celular_norm LIMIT 1');
    $buscar->execute(['celular_norm' => $celularNorm]);
    $existente = (int) $buscar->fetchColumn();
    if ($existente !== 0) {
        if ($dni !== null || $sasId !== null) {
            $db->prepare(
                'UPDATE contactos SET dni = COALESCE(dni, :dni), sas_cliente_id = COALESCE(sas_cliente_id, :sas_id) WHERE id = :id'
            )->execute(['dni' => $dni, 'sas_id' => $sasId, 'id' => $existente]);
        }
        return $existente;
    }

    $insert = $db->prepare(
        "INSERT INTO contactos (nombre, celular, celular_norm, dni, sas_cliente_id, origen, estado, motivo_cierre, creado_por_usuario_id, creado_por_nombre)
         VALUES (:nombre, :celular, :celular_norm, :dni, :sas_id, 'otro', 'cerrada', 'concreto', :usuario_id, :usuario_nombre)"
    );
    $insert->execute([
        'nombre' => $nombre, 'celular' => $celular, 'celular_norm' => $celularNorm,
        'dni' => $dni, 'sas_id' => $sasId,
        'usuario_id' => usuario_actual_id(), 'usuario_nombre' => usuario_actual_nombre(),
    ]);
    $contactoId = (int) $db->lastInsertId();

    $db->prepare(
        "INSERT INTO seguimientos (contacto_id, tipo, resultado, nota)
         VALUES (:contacto_id, 'consulta', 'sin_dato', 'Alta desde compromiso de pago')"
    )->execute(['contacto_id' => $contactoId]);

    return $contactoId;
}

function compromiso_crear(PDO $db, array $body): never
{
    $centavos = compromiso_monto_obligatorio($body, 'monto', 'Ingresá un monto válido, mayor a cero.');
    $fecha = compromiso_fecha_obligatoria($body, 'fecha_compromiso');
    $concepto = api_string($body, 'concepto', 150);
    $nota = api_nullable_string($body, 'nota', 2000);

    $db->beginTransaction();
    try {
        $contactoId = api_integer($body['contacto_id'] ?? null);
        if ($contactoId > 0) {
            $existe = $db->prepare('SELECT id FROM contactos WHERE id = :id');
            $existe->execute(['id' => $contactoId]);
            if (!$existe->fetchColumn()) {
                $db->rollBack();
                json_response(['ok' => false, 'error' => 'El contacto no existe.'], 422);
            }
        } elseif (is_array($body['nuevo_contacto'] ?? null)) {
            $contactoId = compromiso_contacto_para($db, $body['nuevo_contacto']);
        } else {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'Elegí un contacto o cargá uno nuevo.'], 422);
        }

        $id = compromiso_insertar($db, $contactoId, $centavos, $concepto, $fecha, $nota, null);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    json_response(['ok' => true, 'data' => compromiso_buscar($db, $id)], 201);
}

/** Edición de un compromiso pendiente: solo se toca lo que viene en el body. */
function compromiso_editar(PDO $db, int $id, array $actual, array $body): never
{
    if ($actual['estado'] !== 'pendiente') {
        json_response(['ok' => false, 'error' => 'Solo se puede editar un compromiso pendiente.'], 422);
    }

    $campos = [];
    $params = ['id' => $id];
    if (array_key_exists('monto', $body)) {
        $campos[] = 'monto = :monto';
        $params['monto'] = centavos_a_decimal(compromiso_monto_obligatorio($body, 'monto', 'Ingresá un monto válido, mayor a cero.'));
    }
    if (array_key_exists('concepto', $body)) {
        $campos[] = 'concepto = :concepto';
        $params['concepto'] = api_string($body, 'concepto', 150);
    }
    if (array_key_exists('nota', $body)) {
        $campos[] = 'nota = :nota';
        $params['nota'] = api_nullable_string($body, 'nota', 2000);
    }
    if (array_key_exists('fecha_compromiso', $body)) {
        $fecha = compromiso_fecha_obligatoria($body, 'fecha_compromiso');
        $campos[] = 'fecha_compromiso = :fecha';
        $campos[] = 'recordatorio_enviado = :enviado';
        $params['fecha'] = $fecha;
        $params['enviado'] = compromiso_ya_paso($fecha) ? 1 : 0;
    }

    if ($campos !== []) {
        $db->prepare('UPDATE compromisos SET ' . implode(', ', $campos) . " WHERE id = :id AND estado = 'pendiente'")->execute($params);
    }
    json_response(['ok' => true, 'data' => compromiso_buscar($db, $id)]);
}

/**
 * Registra qué pasó: pagado, parcial (con lo recibido) o incumplido. Si viene
 * `reagendar`, crea en la misma transacción un compromiso nuevo con el saldo
 * (o con el monto completo si no pagó) enlazado por origen_id.
 */
function compromiso_resolver(PDO $db, int $id, array $actual, array $body): never
{
    $resultado = api_string($body, 'resultado', 20);
    if (!in_array($resultado, ['pagado', 'parcial', 'incumplido'], true)) {
        json_response(['ok' => false, 'error' => 'Elegí qué pasó con el pago.'], 422);
    }

    $montoCentavos = api_centavos($actual['monto']);
    $pagadoCentavos = null;
    if ($resultado === 'pagado') {
        $pagadoCentavos = $montoCentavos;
    } elseif ($resultado === 'parcial') {
        $pagadoCentavos = api_centavos($body['monto_pagado'] ?? null);
        if ($pagadoCentavos <= 0 || $pagadoCentavos >= $montoCentavos) {
            json_response(['ok' => false, 'error' => 'En un pago parcial, lo recibido tiene que ser mayor a cero y menor al monto.'], 422);
        }
    }

    $reagendar = is_array($body['reagendar'] ?? null) ? $body['reagendar'] : null;
    if ($reagendar !== null && $resultado === 'pagado') {
        json_response(['ok' => false, 'error' => 'Un compromiso pagado no se reagenda.'], 422);
    }
    $nuevaFecha = null;
    $nuevoMonto = 0;
    if ($reagendar !== null) {
        $nuevaFecha = compromiso_fecha_obligatoria($reagendar, 'fecha');
        $saldo = $montoCentavos - ($pagadoCentavos ?? 0);
        $nuevoMonto = array_key_exists('monto', $reagendar)
            ? compromiso_monto_obligatorio($reagendar, 'monto', 'Ingresá un monto válido para el nuevo compromiso.')
            : $saldo;
    }

    $db->beginTransaction();
    try {
        // El estado = 'pendiente' en el WHERE evita que dos usuarios resuelvan
        // el mismo compromiso: el segundo no modifica ninguna fila.
        $update = $db->prepare(
            "UPDATE compromisos
             SET estado = :estado, monto_pagado = :monto_pagado, fecha_resolucion = NOW(),
                 resuelto_por_usuario_id = :usuario_id, resuelto_por_nombre = :usuario_nombre,
                 nota = COALESCE(:nota, nota)
             WHERE id = :id AND estado = 'pendiente'"
        );
        $update->execute([
            'estado' => $resultado,
            'monto_pagado' => $pagadoCentavos === null ? null : centavos_a_decimal($pagadoCentavos),
            'usuario_id' => usuario_actual_id(), 'usuario_nombre' => usuario_actual_nombre(),
            'nota' => api_nullable_string($body, 'nota', 2000), 'id' => $id,
        ]);
        if ($update->rowCount() === 0) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'Este compromiso ya fue registrado por otra persona.'], 409);
        }
        if ($nuevaFecha !== null) {
            compromiso_insertar($db, (int) $actual['contacto_id'], $nuevoMonto, (string) $actual['concepto'], $nuevaFecha, null, $id);
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    json_response(['ok' => true, 'data' => compromiso_buscar($db, $id)]);
}

/** Deshacer: vuelve un compromiso resuelto a pendiente. */
function compromiso_reabrir(PDO $db, int $id, array $actual): never
{
    if ($actual['estado'] === 'pendiente') {
        json_response(['ok' => false, 'error' => 'El compromiso ya está pendiente.'], 422);
    }

    // Si al resolverlo se agendó un saldo o una reprogramación, deshacer
    // también quita ese compromiso nuevo, salvo que ya se haya registrado.
    $hijos = $db->prepare('SELECT id, estado FROM compromisos WHERE origen_id = :id');
    $hijos->execute(['id' => $id]);
    $hijos = $hijos->fetchAll();
    foreach ($hijos as $hijo) {
        if ($hijo['estado'] !== 'pendiente') {
            json_response(['ok' => false, 'error' => 'No se puede deshacer: el compromiso que se agendó después ya fue registrado.'], 422);
        }
    }

    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM compromisos WHERE origen_id = :id AND estado = 'pendiente'")->execute(['id' => $id]);
        $db->prepare(
            "UPDATE compromisos
             SET estado = 'pendiente', monto_pagado = NULL, fecha_resolucion = NULL,
                 resuelto_por_usuario_id = NULL, resuelto_por_nombre = '', recordatorio_enviado = :enviado
             WHERE id = :id"
        )->execute(['id' => $id, 'enviado' => compromiso_ya_paso((string) $actual['fecha_compromiso']) ? 1 : 0]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
    json_response(['ok' => true, 'data' => compromiso_buscar($db, $id)]);
}

function compromisos_listar(PDO $db): never
{
    $filtro = api_string($_GET, 'estado', 20) ?: 'todos';
    $texto = api_string($_GET, 'q', 120);
    $contactoId = api_integer($_GET['contacto_id'] ?? null);

    $where = [];
    $params = [];
    switch ($filtro) {
        case 'pendientes': $where[] = "c.estado = 'pendiente'"; break;
        case 'vencidos': $where[] = "c.estado = 'pendiente' AND DATE(c.fecha_compromiso) < CURDATE()"; break;
        case 'hoy': $where[] = "c.estado = 'pendiente' AND DATE(c.fecha_compromiso) = CURDATE()"; break;
        case 'proximos': $where[] = "c.estado = 'pendiente' AND DATE(c.fecha_compromiso) > CURDATE()"; break;
        case 'pagados': $where[] = "c.estado IN ('pagado','parcial')"; break;
        case 'incumplidos': $where[] = "c.estado = 'incumplido'"; break;
        case 'todos': break;
        default: json_response(['ok' => false, 'error' => 'El filtro no es válido.'], 422);
    }
    if ($contactoId > 0) {
        $where[] = 'c.contacto_id = :contacto_id';
        $params['contacto_id'] = $contactoId;
    }
    if ($texto !== '') {
        $where[] = '(k.nombre LIKE :q_nombre OR k.celular LIKE :q_celular OR k.celular_norm LIKE :q_norm OR c.concepto LIKE :q_concepto)';
        $like = '%' . $texto . '%';
        $params['q_nombre'] = $like;
        $params['q_celular'] = $like;
        $params['q_norm'] = $like;
        $params['q_concepto'] = $like;
    }
    $condiciones = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    $pagina = max(1, api_integer($_GET['pagina'] ?? 1));
    $offset = ($pagina - 1) * COMPROMISOS_POR_PAGINA;

    $from = ' FROM compromisos c INNER JOIN contactos k ON k.id = c.contacto_id' . $condiciones;
    $total = $db->prepare('SELECT COUNT(*)' . $from);
    $total->execute($params);
    $total = (int) $total->fetchColumn();

    $stmt = $db->prepare(
        'SELECT ' . COMPROMISO_COLUMNAS_SQL . ', ' . COMPROMISO_SITUACION_SQL . ' AS situacion,
                k.nombre AS contacto_nombre, k.celular AS contacto_celular, k.celular_norm AS contacto_celular_norm,
                k.estado AS contacto_estado' . $from . "
         ORDER BY c.estado = 'pendiente' DESC,
                  IF(c.estado = 'pendiente', c.fecha_compromiso, NULL) ASC,
                  c.fecha_resolucion DESC, c.id DESC
         LIMIT :limite OFFSET :offset"
    );
    foreach ($params as $clave => $valor) {
        $stmt->bindValue(':' . $clave, $valor);
    }
    $stmt->bindValue(':limite', COMPROMISOS_POR_PAGINA, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    // Totales de la pantalla: siempre sobre todos los compromisos, sin los filtros.
    $resumen = $db->query(
        "SELECT
            SUM(estado = 'pendiente') AS pendientes,
            COALESCE(SUM(IF(estado = 'pendiente' AND DATE(fecha_compromiso) < CURDATE(), monto, 0)), 0) AS vencidos_monto,
            SUM(estado = 'pendiente' AND DATE(fecha_compromiso) < CURDATE()) AS vencidos_cantidad,
            COALESCE(SUM(IF(estado = 'pendiente' AND DATE(fecha_compromiso) = CURDATE(), monto, 0)), 0) AS hoy_monto,
            SUM(estado = 'pendiente' AND DATE(fecha_compromiso) = CURDATE()) AS hoy_cantidad,
            COALESCE(SUM(IF(estado = 'pendiente' AND DATE(fecha_compromiso) > CURDATE() AND DATE(fecha_compromiso) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY), monto, 0)), 0) AS proximos_monto,
            SUM(estado = 'pendiente' AND DATE(fecha_compromiso) > CURDATE() AND DATE(fecha_compromiso) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS proximos_cantidad,
            COALESCE(SUM(IF(estado IN ('pagado','parcial') AND fecha_resolucion >= DATE_FORMAT(CURDATE(), '%Y-%m-01'), monto_pagado, 0)), 0) AS cobrado_mes
         FROM compromisos"
    )->fetch();

    json_response(['ok' => true, 'data' => [
        'items' => $items,
        'pagina' => $pagina,
        'total' => $total,
        'hay_mas' => $offset + count($items) < $total,
        'resumen' => [
            'pendientes' => (int) $resumen['pendientes'],
            'vencidos' => ['monto' => $resumen['vencidos_monto'], 'cantidad' => (int) $resumen['vencidos_cantidad']],
            'hoy' => ['monto' => $resumen['hoy_monto'], 'cantidad' => (int) $resumen['hoy_cantidad']],
            'proximos' => ['monto' => $resumen['proximos_monto'], 'cantidad' => (int) $resumen['proximos_cantidad']],
            'cobrado_mes' => $resumen['cobrado_mes'],
        ],
    ]]);
}

$db = Db::get();
$method = api_method();
$id = api_integer($_GET['id'] ?? null);

if ($method === 'GET') {
    if ($id > 0) {
        $compromiso = compromiso_buscar($db, $id);
        if ($compromiso === null) {
            json_response(['ok' => false, 'error' => 'Compromiso no encontrado.'], 404);
        }
        json_response(['ok' => true, 'data' => $compromiso]);
    }
    compromisos_listar($db);
}

$body = api_body();
api_require_write();
$id = $id ?: api_integer($body['id'] ?? null);

if ($method === 'POST' && ($body['accion'] ?? '') === '') {
    compromiso_crear($db, $body);
}

$actual = $id > 0 ? compromiso_buscar($db, $id) : null;
if ($actual === null) {
    json_response(['ok' => false, 'error' => 'Compromiso no encontrado.'], 404);
}

if ($method === 'POST') {
    $accion = api_string($body, 'accion', 20);
    if ($accion === 'resolver') {
        compromiso_resolver($db, $id, $actual, $body);
    }
    if ($accion === 'reabrir') {
        compromiso_reabrir($db, $id, $actual);
    }
    json_response(['ok' => false, 'error' => 'Acción no válida.'], 422);
}

if ($method === 'PUT' || $method === 'PATCH') {
    compromiso_editar($db, $id, $actual, $body);
}

if ($method === 'DELETE') {
    $db->prepare('DELETE FROM compromisos WHERE id = :id')->execute(['id' => $id]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
