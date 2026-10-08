<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';

/** @return array<string, mixed>|null */
function contacto_buscar(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        "SELECT c.*, (
            SELECT s.nota FROM seguimientos s
            WHERE s.contacto_id = c.id AND s.tipo = 'consulta'
            ORDER BY s.fecha DESC, s.id DESC LIMIT 1
        ) AS consulta
        FROM contactos c WHERE c.id = :id"
    );
    $stmt->execute(['id' => $id]);
    $contacto = $stmt->fetch();
    if (!$contacto) {
        return null;
    }

    $tags = $db->prepare(
        'SELECT e.id, e.nombre, e.color FROM etiquetas e
         INNER JOIN contacto_etiqueta ce ON ce.etiqueta_id = e.id
         WHERE ce.contacto_id = :id ORDER BY e.nombre'
    );
    $tags->execute(['id' => $id]);
    $contacto['etiquetas'] = $tags->fetchAll();

    $history = $db->prepare(
        'SELECT id, tipo, resultado, nota, fecha, proximo_asignado
         FROM seguimientos WHERE contacto_id = :id ORDER BY fecha DESC, id DESC'
    );
    $history->execute(['id' => $id]);
    $contacto['historial'] = $history->fetchAll();

    return $contacto;
}

/** @param list<int> $etiquetas */
function contacto_sincronizar_etiquetas(PDO $db, int $contactoId, array $etiquetas): void
{
    $db->prepare('DELETE FROM contacto_etiqueta WHERE contacto_id = :contacto_id')
        ->execute(['contacto_id' => $contactoId]);
    if ($etiquetas === []) {
        return;
    }

    $exists = $db->prepare('SELECT id FROM etiquetas WHERE id = :id');
    $insert = $db->prepare(
        'INSERT IGNORE INTO contacto_etiqueta (contacto_id, etiqueta_id)
         VALUES (:contacto_id, :etiqueta_id)'
    );
    foreach ($etiquetas as $etiquetaId) {
        $exists->execute(['id' => $etiquetaId]);
        if ($exists->fetchColumn()) {
            $insert->execute(['contacto_id' => $contactoId, 'etiqueta_id' => $etiquetaId]);
        }
    }
}

/** @return array<string, mixed> */
function contacto_validar(array $body): array
{
    $nombre = api_string($body, 'nombre', 120);
    $celular = api_string($body, 'celular', 30);
    $consulta = api_string($body, 'consulta', 4000);
    $producto = api_nullable_string($body, 'producto_interes', 120);
    $origen = api_string($body, 'origen', 20) ?: 'otro';
    $localidad = api_nullable_string($body, 'localidad', 100);
    $provincia = api_nullable_string($body, 'provincia', 30);
    $proximo = api_date(isset($body['proximo_contacto']) ? (string) $body['proximo_contacto'] : null);
    $origenes = ['whatsapp', 'instagram', 'facebook', 'llamada', 'local', 'referido', 'otro'];
    $provincias = ['Tucumán', 'Santiago del Estero', 'Catamarca', 'Otra'];

    if ($nombre === '' || $celular === '' || $consulta === '') {
        json_response(['ok' => false, 'error' => 'Nombre, celular y consulta son obligatorios.'], 422);
    }
    $celularNorm = normalizar_celular($celular);
    if (!preg_match('/^549\\d{10}$/', $celularNorm)) {
        json_response(['ok' => false, 'error' => 'Ingresá un celular argentino válido.'], 422);
    }
    if (!in_array($origen, $origenes, true) || ($provincia !== null && !in_array($provincia, $provincias, true))) {
        json_response(['ok' => false, 'error' => 'Revisá el origen o la provincia elegidos.'], 422);
    }

    return compact('nombre', 'celular', 'celularNorm', 'consulta', 'producto', 'origen', 'localidad', 'provincia', 'proximo');
}

function contacto_crear_o_agregar(PDO $db, array $body): never
{
    $values = contacto_validar($body);
    $creadoPorUsuarioId = usuario_actual_id();
    $creadoPorNombre = usuario_actual_nombre();
    $search = $db->prepare('SELECT id FROM contactos WHERE celular_norm = :celular_norm LIMIT 1');
    $search->execute(['celular_norm' => $values['celularNorm']]);
    $duplicateId = (int) $search->fetchColumn();
    $addToExisting = (bool) ($body['agregar_a_existente'] ?? false);

    if ($duplicateId !== 0 && !$addToExisting) {
        json_response([
            'ok' => true,
            'duplicado' => true,
            'contacto' => contacto_buscar($db, $duplicateId),
        ]);
    }

    $tags = api_ids($body['etiquetas'] ?? []);
    $db->beginTransaction();
    try {
        if ($duplicateId !== 0) {
            $contactoId = $duplicateId;
            $update = $db->prepare(
                "UPDATE contactos
                 SET proximo_contacto = COALESCE(:proximo_contacto, proximo_contacto),
                     estado = CASE WHEN :proximo_contacto IS NOT NULL AND estado <> 'cerrada' THEN 'seguimiento' ELSE estado END
                 WHERE id = :id"
            );
            $update->execute(['id' => $contactoId, 'proximo_contacto' => $values['proximo']]);
        } else {
            $insert = $db->prepare(
                'INSERT INTO contactos
                    (nombre, celular, celular_norm, producto_interes, origen, localidad, provincia, estado, proximo_contacto, creado_por_usuario_id, creado_por_nombre)
                 VALUES
                    (:nombre, :celular, :celular_norm, :producto, :origen, :localidad, :provincia, :estado, :proximo_contacto, :creado_por_usuario_id, :creado_por_nombre)'
            );
            $insert->execute([
                'nombre' => $values['nombre'], 'celular' => $values['celular'], 'celular_norm' => $values['celularNorm'],
                'producto' => $values['producto'], 'origen' => $values['origen'], 'localidad' => $values['localidad'],
                'provincia' => $values['provincia'], 'estado' => $values['proximo'] === null ? 'pendiente' : 'seguimiento',
                'proximo_contacto' => $values['proximo'],
                'creado_por_usuario_id' => $creadoPorUsuarioId,
                'creado_por_nombre' => $creadoPorNombre,
            ]);
            $contactoId = (int) $db->lastInsertId();
        }

        $follow = $db->prepare(
            "INSERT INTO seguimientos (contacto_id, tipo, resultado, nota, proximo_asignado)
             VALUES (:contacto_id, 'consulta', 'sin_dato', :nota, :proximo_asignado)"
        );
        $follow->execute(['contacto_id' => $contactoId, 'nota' => $values['consulta'], 'proximo_asignado' => $values['proximo']]);

        if ($duplicateId === 0 || $tags !== []) {
            contacto_sincronizar_etiquetas($db, $contactoId, $tags);
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }

    json_response([
        'ok' => true,
        'agregado_a_existente' => $duplicateId !== 0,
        'data' => contacto_buscar($db, $contactoId),
    ], $duplicateId === 0 ? 201 : 200);
}

function contactos_listar(PDO $db): never
{
    $where = [];
    $params = [];
    $texto = api_string($_GET, 'texto', 120);
    $estado = api_string($_GET, 'estado', 20);
    $origen = api_string($_GET, 'origen', 20);
    $provincia = api_string($_GET, 'provincia', 30);
    $etiqueta = api_integer($_GET['etiqueta'] ?? null);
    $desde = api_string($_GET, 'desde', 10);
    $hasta = api_string($_GET, 'hasta', 10);

    if ($texto !== '') {
        $where[] = "(c.nombre LIKE :texto_nombre OR c.celular LIKE :texto_celular OR c.celular_norm LIKE :texto_norm
            OR c.producto_interes LIKE :texto OR EXISTS (
                SELECT 1 FROM seguimientos sx WHERE sx.contacto_id = c.id AND sx.tipo = 'consulta' AND sx.nota LIKE :texto_consulta
            ))";
        $like = '%' . $texto . '%';
        $params['texto_nombre'] = $like;
        $params['texto_celular'] = $like;
        $params['texto_norm'] = $like;
        $params['texto'] = $like;
        $params['texto_consulta'] = $like;
    }
    if (in_array($estado, ['pendiente', 'seguimiento', 'cerrada'], true)) {
        $where[] = 'c.estado = :estado';
        $params['estado'] = $estado;
    } elseif ($estado === 'abiertos') {
        $where[] = "c.estado <> 'cerrada'";
    } elseif ($estado === 'concreto') {
        $where[] = "c.estado = 'cerrada' AND c.motivo_cierre = 'concreto'";
    } elseif ($estado === 'cerrados') {
        // "Cerrados" en los filtros de Contactos = cerrada sin concretar
        // (no_interesa/sin_respuesta); "Concretaron" es su propio filtro.
        $where[] = "c.estado = 'cerrada' AND (c.motivo_cierre IS NULL OR c.motivo_cierre <> 'concreto')";
    }
    if (in_array($origen, ['whatsapp', 'instagram', 'facebook', 'llamada', 'local', 'referido', 'otro'], true)) {
        $where[] = 'c.origen = :origen';
        $params['origen'] = $origen;
    }
    if (in_array($provincia, ['Tucumán', 'Santiago del Estero', 'Catamarca', 'Otra'], true)) {
        $where[] = 'c.provincia = :provincia';
        $params['provincia'] = $provincia;
    }
    if ($etiqueta > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM contacto_etiqueta ce WHERE ce.contacto_id = c.id AND ce.etiqueta_id = :etiqueta)';
        $params['etiqueta'] = $etiqueta;
    }
    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $desde)) {
        $where[] = 'c.creado_en >= :desde';
        $params['desde'] = $desde . ' 00:00:00';
    }
    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $hasta)) {
        $where[] = 'c.creado_en < DATE_ADD(:hasta, INTERVAL 1 DAY)';
        $params['hasta'] = $hasta . ' 00:00:00';
    }

    $page = max(1, api_integer($_GET['pagina'] ?? 1));
    $limit = 30;
    $offset = ($page - 1) * $limit;
    $conditions = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $count = $db->prepare('SELECT COUNT(*) FROM contactos c' . $conditions);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $sql = "SELECT c.*, (
                SELECT s.nota FROM seguimientos s WHERE s.contacto_id = c.id AND s.tipo = 'consulta'
                ORDER BY s.fecha DESC, s.id DESC LIMIT 1
            ) AS consulta
            FROM contactos c {$conditions}
            ORDER BY c.proximo_contacto ASC, c.creado_en DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    // Conteos globales para el subtítulo de la pantalla, sin los filtros
    // actuales: siempre "todos los contactos" / "todos en seguimiento".
    $resumen = $db->query("SELECT COUNT(*) AS total, SUM(estado = 'seguimiento') AS en_seguimiento FROM contactos")->fetch();

    json_response(['ok' => true, 'data' => [
        'items' => $items,
        'pagina' => $page,
        'total' => $total,
        'hay_mas' => $offset + count($items) < $total,
        'resumen' => ['total' => (int) $resumen['total'], 'en_seguimiento' => (int) $resumen['en_seguimiento']],
    ]]);
}

$db = Db::get();
$method = api_method();
$id = api_integer($_GET['id'] ?? null);

if ($method === 'GET') {
    if ($id > 0) {
        $contacto = contacto_buscar($db, $id);
        if ($contacto === null) {
            json_response(['ok' => false, 'error' => 'Contacto no encontrado.'], 404);
        }
        json_response(['ok' => true, 'data' => $contacto]);
    }
    contactos_listar($db);
}

$body = api_body();
api_require_write();
$id = $id ?: api_integer($body['id'] ?? null);

if ($method === 'POST') {
    contacto_crear_o_agregar($db, $body);
}

if ($id === 0 || contacto_buscar($db, $id) === null) {
    json_response(['ok' => false, 'error' => 'Contacto no encontrado.'], 404);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $nombre = api_string($body, 'nombre', 120);
    $celular = api_string($body, 'celular', 30);
    if ($nombre === '' || $celular === '') {
        json_response(['ok' => false, 'error' => 'Nombre y celular son obligatorios.'], 422);
    }
    $celularNorm = normalizar_celular($celular);
    if (!preg_match('/^549\\d{10}$/', $celularNorm)) {
        json_response(['ok' => false, 'error' => 'Ingresá un celular argentino válido.'], 422);
    }
    $origen = api_string($body, 'origen', 20) ?: 'otro';
    $provincia = api_nullable_string($body, 'provincia', 30);
    if (!in_array($origen, ['whatsapp', 'instagram', 'facebook', 'llamada', 'local', 'referido', 'otro'], true)
        || ($provincia !== null && !in_array($provincia, ['Tucumán', 'Santiago del Estero', 'Catamarca', 'Otra'], true))) {
        json_response(['ok' => false, 'error' => 'Revisá el origen o la provincia elegidos.'], 422);
    }
    $stmt = $db->prepare(
        'UPDATE contactos SET nombre = :nombre, celular = :celular, celular_norm = :celular_norm,
         producto_interes = :producto, origen = :origen, localidad = :localidad, provincia = :provincia
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id, 'nombre' => $nombre, 'celular' => $celular, 'celular_norm' => $celularNorm,
        'producto' => api_nullable_string($body, 'producto_interes', 120), 'origen' => $origen,
        'localidad' => api_nullable_string($body, 'localidad', 100), 'provincia' => $provincia,
    ]);
    if (array_key_exists('etiquetas', $body)) {
        contacto_sincronizar_etiquetas($db, $id, api_ids($body['etiquetas']));
    }
    json_response(['ok' => true, 'data' => contacto_buscar($db, $id)]);
}

if ($method === 'DELETE') {
    $stmt = $db->prepare('DELETE FROM contactos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
