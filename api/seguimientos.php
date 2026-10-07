<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';

/** @return array<string, mixed>|null */
function seguimiento_contacto(PDO $db, int $contactoId): ?array
{
    $stmt = $db->prepare('SELECT id, nombre, estado, proximo_contacto FROM contactos WHERE id = :id');
    $stmt->execute(['id' => $contactoId]);
    return $stmt->fetch() ?: null;
}

function seguimiento_fecha_posponer(string $opcion): ?string
{
    $ahora = new DateTimeImmutable('now');
    return match ($opcion) {
        'hora' => $ahora->modify('+1 hour')->format('Y-m-d H:i:s'),
        'manana' => $ahora->modify('tomorrow')->setTime(9, 0)->format('Y-m-d H:i:s'),
        'tres_dias' => $ahora->modify('+3 days')->setTime(9, 0)->format('Y-m-d H:i:s'),
        default => null,
    };
}

/** @param array<string, mixed> $body */
function seguimiento_registrar(PDO $db, array $body): never
{
    $contactoId = api_integer($body['contacto_id'] ?? null);
    $contacto = seguimiento_contacto($db, $contactoId);
    if ($contacto === null) {
        json_response(['ok' => false, 'error' => 'Contacto no encontrado.'], 404);
    }
    if ($contacto['estado'] === 'cerrada') {
        json_response(['ok' => false, 'error' => 'Reabrí el contacto antes de registrar un recontacto.'], 422);
    }

    $resultado = api_string($body, 'resultado', 30);
    $accion = api_string($body, 'desenlace', 20);
    $nota = api_nullable_string($body, 'nota', 4000);
    if (!in_array($resultado, ['atendio', 'no_atendio', 'mensaje_enviado'], true)) {
        json_response(['ok' => false, 'error' => 'Elegí cómo salió el recontacto.'], 422);
    }
    if (!in_array($accion, ['reagendar', 'cerrar'], true)) {
        json_response(['ok' => false, 'error' => 'Elegí si querés reagendar o cerrar la consulta.'], 422);
    }

    $proximo = null;
    $motivo = null;
    if ($accion === 'reagendar') {
        $proximo = api_date(isset($body['proximo_contacto']) ? (string) $body['proximo_contacto'] : null);
        if ($proximo === null) {
            json_response(['ok' => false, 'error' => 'Elegí la próxima fecha de contacto.'], 422);
        }
    } else {
        $motivo = api_string($body, 'motivo_cierre', 30);
        if (!in_array($motivo, ['concreto', 'no_interesa', 'sin_respuesta'], true)) {
            json_response(['ok' => false, 'error' => 'Indicá el motivo de cierre.'], 422);
        }
    }

    $db->beginTransaction();
    try {
        $insert = $db->prepare(
            "INSERT INTO seguimientos
                (contacto_id, tipo, resultado, nota, proximo_anterior, proximo_asignado)
             VALUES (:contacto_id, 'recontacto', :resultado, :nota, :proximo_anterior, :proximo_asignado)"
        );
        $insert->execute([
            'contacto_id' => $contactoId, 'resultado' => $resultado, 'nota' => $nota,
            'proximo_anterior' => $contacto['proximo_contacto'], 'proximo_asignado' => $proximo,
        ]);
        $update = $db->prepare(
            'UPDATE contactos SET estado = :estado, motivo_cierre = :motivo_cierre, proximo_contacto = :proximo_contacto WHERE id = :id'
        );
        $update->execute([
            'id' => $contactoId, 'estado' => $accion === 'cerrar' ? 'cerrada' : 'seguimiento',
            'motivo_cierre' => $motivo, 'proximo_contacto' => $proximo,
        ]);
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }

    json_response(['ok' => true, 'data' => seguimiento_contacto($db, $contactoId)]);
}

/** @param array<string, mixed> $body */
function seguimiento_nota(PDO $db, array $body): never
{
    $contactoId = api_integer($body['contacto_id'] ?? null);
    if (seguimiento_contacto($db, $contactoId) === null) {
        json_response(['ok' => false, 'error' => 'Contacto no encontrado.'], 404);
    }
    $nota = api_string($body, 'nota', 4000);
    if ($nota === '') {
        json_response(['ok' => false, 'error' => 'Escribí una nota antes de guardarla.'], 422);
    }
    $stmt = $db->prepare("INSERT INTO seguimientos (contacto_id, tipo, resultado, nota) VALUES (:contacto_id, 'nota', 'sin_dato', :nota)");
    $stmt->execute(['contacto_id' => $contactoId, 'nota' => $nota]);
    json_response(['ok' => true, 'data' => seguimiento_contacto($db, $contactoId)], 201);
}

/** @param array<string, mixed> $body */
function seguimiento_reabrir(PDO $db, array $body): never
{
    $contactoId = api_integer($body['contacto_id'] ?? null);
    $contacto = seguimiento_contacto($db, $contactoId);
    if ($contacto === null) {
        json_response(['ok' => false, 'error' => 'Contacto no encontrado.'], 404);
    }
    if ($contacto['estado'] !== 'cerrada') {
        json_response(['ok' => false, 'error' => 'Este contacto ya está abierto.'], 422);
    }
    $proximo = api_date(isset($body['proximo_contacto']) ? (string) $body['proximo_contacto'] : null);
    if ($proximo === null) {
        json_response(['ok' => false, 'error' => 'Elegí la fecha para reabrir el contacto.'], 422);
    }
    $nota = api_nullable_string($body, 'nota', 4000) ?? 'Contacto reabierto.';

    $db->beginTransaction();
    try {
        $update = $db->prepare("UPDATE contactos SET estado = 'seguimiento', motivo_cierre = NULL, proximo_contacto = :proximo_contacto WHERE id = :id");
        $update->execute(['id' => $contactoId, 'proximo_contacto' => $proximo]);
        $insert = $db->prepare(
            "INSERT INTO seguimientos (contacto_id, tipo, resultado, nota, proximo_asignado)
             VALUES (:contacto_id, 'cambio_estado', 'sin_dato', :nota, :proximo_asignado)"
        );
        $insert->execute(['contacto_id' => $contactoId, 'nota' => $nota, 'proximo_asignado' => $proximo]);
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
    json_response(['ok' => true, 'data' => seguimiento_contacto($db, $contactoId)]);
}

/** @param array<string, mixed> $body */
function seguimiento_posponer(PDO $db, array $body): never
{
    $contactoId = api_integer($body['contacto_id'] ?? null);
    $contacto = seguimiento_contacto($db, $contactoId);
    if ($contacto === null || $contacto['estado'] === 'cerrada') {
        json_response(['ok' => false, 'error' => 'No se puede posponer este contacto.'], 422);
    }
    $opcion = api_string($body, 'opcion', 20);
    $proximo = seguimiento_fecha_posponer($opcion);
    if ($proximo === null) {
        json_response(['ok' => false, 'error' => 'Elegí una opción para posponer.'], 422);
    }
    $etiqueta = ['hora' => 'una hora', 'manana' => 'mañana a las 9:00', 'tres_dias' => 'dentro de 3 días'][$opcion];
    $db->beginTransaction();
    try {
        $insert = $db->prepare(
            "INSERT INTO seguimientos (contacto_id, tipo, resultado, nota, proximo_anterior, proximo_asignado)
             VALUES (:contacto_id, 'nota', 'sin_dato', :nota, :proximo_anterior, :proximo_asignado)"
        );
        $insert->execute([
            'contacto_id' => $contactoId, 'nota' => 'Pospuesto para ' . $etiqueta . '.',
            'proximo_anterior' => $contacto['proximo_contacto'], 'proximo_asignado' => $proximo,
        ]);
        $update = $db->prepare("UPDATE contactos SET estado = 'seguimiento', proximo_contacto = :proximo_contacto WHERE id = :id");
        $update->execute(['id' => $contactoId, 'proximo_contacto' => $proximo]);
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
    json_response(['ok' => true, 'mensaje' => 'Pospuesto para ' . $etiqueta . '.', 'data' => seguimiento_contacto($db, $contactoId)]);
}

if (api_method() !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$body = api_body();
api_require_write();
$db = Db::get();
$accion = api_string($body, 'accion', 20);

match ($accion) {
    'recontacto' => seguimiento_registrar($db, $body),
    'nota' => seguimiento_nota($db, $body),
    'reabrir' => seguimiento_reabrir($db, $body),
    'posponer' => seguimiento_posponer($db, $body),
    default => json_response(['ok' => false, 'error' => 'Acción de seguimiento no válida.'], 422),
};
