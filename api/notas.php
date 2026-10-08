<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';

const NOTA_COLORES = ['celeste', 'durazno', 'lavanda', 'menta', 'rosa'];

/** @return array<string, mixed>|null */
function nota_buscar(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT n.*, c.nombre AS contacto_nombre, c.estado AS contacto_estado
         FROM notas n LEFT JOIN contactos c ON c.id = n.contacto_id
         WHERE n.id = :id'
    );
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function notas_listar(PDO $db): never
{
    $q = api_string($_GET, 'q', 120);
    $where = '';
    $params = [];
    if ($q !== '') {
        $where = 'WHERE n.titulo LIKE :q_titulo OR n.texto LIKE :q_texto';
        $like = '%' . $q . '%';
        $params['q_titulo'] = $like;
        $params['q_texto'] = $like;
    }

    $stmt = $db->prepare(
        "SELECT n.*, c.nombre AS contacto_nombre, c.estado AS contacto_estado
         FROM notas n LEFT JOIN contactos c ON c.id = n.contacto_id
         {$where}
         ORDER BY n.fijada DESC, n.actualizado_en DESC"
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $resumen = $db->query('SELECT COUNT(*) AS total, SUM(fijada = 1) AS fijadas FROM notas')->fetch();

    json_response(['ok' => true, 'data' => [
        'items' => $items,
        'resumen' => ['total' => (int) $resumen['total'], 'fijadas' => (int) $resumen['fijadas']],
    ]]);
}

/** @return array{titulo: string, texto: ?string, color: string, contacto_id: ?int} */
function nota_validar(PDO $db, array $body): array
{
    $titulo = api_string($body, 'titulo', 150);
    $texto = api_nullable_string($body, 'texto', 20000);
    $color = api_string($body, 'color', 20) ?: 'celeste';
    if (!in_array($color, NOTA_COLORES, true)) {
        json_response(['ok' => false, 'error' => 'Elegí un color válido para la nota.'], 422);
    }
    if ($titulo === '' && $texto === null) {
        json_response(['ok' => false, 'error' => 'La nota necesita un título o un texto.'], 422);
    }

    $contactoId = api_integer($body['contacto_id'] ?? null);
    if ($contactoId > 0) {
        $existe = $db->prepare('SELECT id FROM contactos WHERE id = :id');
        $existe->execute(['id' => $contactoId]);
        if (!$existe->fetchColumn()) {
            $contactoId = 0;
        }
    }

    return [
        'titulo' => $titulo,
        'texto' => $texto,
        'color' => $color,
        'contacto_id' => $contactoId > 0 ? $contactoId : null,
    ];
}

function nota_crear(PDO $db, array $body): never
{
    $values = nota_validar($db, $body);
    $recordarEn = api_date(isset($body['recordar_en']) ? (string) $body['recordar_en'] : null);
    $stmt = $db->prepare(
        'INSERT INTO notas (titulo, texto, color, contacto_id, recordar_en, fijada)
         VALUES (:titulo, :texto, :color, :contacto_id, :recordar_en, :fijada)'
    );
    $stmt->execute([
        'titulo' => $values['titulo'], 'texto' => $values['texto'], 'color' => $values['color'],
        'contacto_id' => $values['contacto_id'], 'recordar_en' => $recordarEn,
        'fijada' => !empty($body['fijada']) ? 1 : 0,
    ]);
    json_response(['ok' => true, 'data' => nota_buscar($db, (int) $db->lastInsertId())], 201);
}

// Edición parcial (fijar o cambiar el recordatorio no deben pisar el
// título/texto/color con blancos): solo se valida y actualiza
// título/texto/color/contacto si el body realmente los incluye.
function nota_editar(PDO $db, int $id, array $body): never
{
    $campos = [];
    $params = ['id' => $id];

    $tocaContenido = array_key_exists('titulo', $body) || array_key_exists('texto', $body)
        || array_key_exists('color', $body) || array_key_exists('contacto_id', $body);
    if ($tocaContenido) {
        $actual = nota_buscar($db, $id);
        $values = nota_validar($db, [
            'titulo' => $body['titulo'] ?? $actual['titulo'],
            'texto' => $body['texto'] ?? $actual['texto'],
            'color' => $body['color'] ?? $actual['color'],
            'contacto_id' => $body['contacto_id'] ?? $actual['contacto_id'],
        ]);
        $campos[] = 'titulo = :titulo';
        $campos[] = 'texto = :texto';
        $campos[] = 'color = :color';
        $campos[] = 'contacto_id = :contacto_id';
        $params['titulo'] = $values['titulo'];
        $params['texto'] = $values['texto'];
        $params['color'] = $values['color'];
        $params['contacto_id'] = $values['contacto_id'];
    }

    if (array_key_exists('fijada', $body)) {
        $campos[] = 'fijada = :fijada';
        $params['fijada'] = !empty($body['fijada']) ? 1 : 0;
    }

    if (array_key_exists('recordar_en', $body)) {
        $recordarEn = api_date((string) ($body['recordar_en'] ?? ''));
        $campos[] = 'recordar_en = :recordar_en';
        $campos[] = 'recordatorio_enviado = 0';
        $params['recordar_en'] = $recordarEn;
    }

    if ($campos !== []) {
        $stmt = $db->prepare('UPDATE notas SET ' . implode(', ', $campos) . ' WHERE id = :id');
        $stmt->execute($params);
    }
    json_response(['ok' => true, 'data' => nota_buscar($db, $id)]);
}

$db = Db::get();
$method = api_method();
$id = api_integer($_GET['id'] ?? null);

if ($method === 'GET') {
    if ($id > 0) {
        $nota = nota_buscar($db, $id);
        if ($nota === null) {
            json_response(['ok' => false, 'error' => 'Nota no encontrada.'], 404);
        }
        json_response(['ok' => true, 'data' => $nota]);
    }
    notas_listar($db);
}

$body = api_body();
api_require_write();
$id = $id ?: api_integer($body['id'] ?? null);

if ($method === 'POST') {
    nota_crear($db, $body);
}

if ($id === 0 || nota_buscar($db, $id) === null) {
    json_response(['ok' => false, 'error' => 'Nota no encontrada.'], 404);
}

if ($method === 'PUT' || $method === 'PATCH') {
    nota_editar($db, $id, $body);
}

if ($method === 'DELETE') {
    $db->prepare('DELETE FROM notas WHERE id = :id')->execute(['id' => $id]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
