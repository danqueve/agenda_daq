<?php

declare(strict_types=1);

/**
 * Situación de un compromiso, calculada por día (no por hora): uno de hoy
 * sigue "para hoy" hasta que termina el día. Los resueltos conservan su estado.
 * Requiere el alias `c` para la tabla compromisos.
 */
const COMPROMISO_SITUACION_SQL = "CASE
        WHEN c.estado <> 'pendiente' THEN c.estado
        WHEN DATE(c.fecha_compromiso) < CURDATE() THEN 'vencido'
        WHEN DATE(c.fecha_compromiso) = CURDATE() THEN 'hoy'
        ELSE 'proximo'
    END";

/** Columnas que el frontend necesita de cada compromiso (alias `c`). */
const COMPROMISO_COLUMNAS_SQL = 'c.id, c.contacto_id, c.monto, c.concepto, c.fecha_compromiso, c.estado, c.monto_pagado,
        c.fecha_resolucion, c.nota, c.origen_id, c.creado_por_nombre, c.resuelto_por_nombre, c.creado_en';

/**
 * Compromisos de un contacto, los pendientes primero (por fecha) y después
 * los resueltos (más recientes arriba). Los usa la ficha del contacto.
 *
 * @return list<array<string, mixed>>
 */
function compromisos_de_contacto(PDO $db, int $contactoId): array
{
    $sql = 'SELECT ' . COMPROMISO_COLUMNAS_SQL . ', ' . COMPROMISO_SITUACION_SQL . " AS situacion
            FROM compromisos c WHERE c.contacto_id = :id
            ORDER BY c.estado = 'pendiente' DESC,
                     IF(c.estado = 'pendiente', c.fecha_compromiso, NULL) ASC,
                     c.fecha_resolucion DESC, c.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $contactoId]);

    return $stmt->fetchAll();
}

function compromiso_ya_paso(string $fechaSql): bool
{
    return $fechaSql <= date('Y-m-d H:i:s');
}

/** Inserta un compromiso pendiente y devuelve su id. */
function compromiso_insertar(PDO $db, int $contactoId, int $centavos, string $concepto, string $fecha, ?string $nota, ?int $origenId): int
{
    $stmt = $db->prepare(
        'INSERT INTO compromisos
            (contacto_id, monto, concepto, fecha_compromiso, nota, origen_id, recordatorio_enviado, creado_por_usuario_id, creado_por_nombre)
         VALUES
            (:contacto_id, :monto, :concepto, :fecha, :nota, :origen_id, :enviado, :usuario_id, :usuario_nombre)'
    );
    $stmt->execute([
        'contacto_id' => $contactoId, 'monto' => centavos_a_decimal($centavos), 'concepto' => $concepto,
        'fecha' => $fecha, 'nota' => $nota, 'origen_id' => $origenId,
        // Si la fecha ya pasó al cargarlo, no se manda un push de algo viejo.
        'enviado' => compromiso_ya_paso($fecha) ? 1 : 0,
        'usuario_id' => usuario_actual_id(), 'usuario_nombre' => usuario_actual_nombre(),
    ]);

    return (int) $db->lastInsertId();
}

function compromiso_fecha_obligatoria(array $body, string $clave): string
{
    $valor = isset($body[$clave]) ? trim((string) $body[$clave]) : '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        $valor .= 'T09:00';
    }
    $fecha = api_date($valor !== '' ? $valor : null);
    if ($fecha === null) {
        json_response(['ok' => false, 'error' => 'Elegí la fecha del compromiso.'], 422);
    }

    return $fecha;
}

function compromiso_monto_obligatorio(array $body, string $clave, string $mensaje): int
{
    $monto = api_monto($body[$clave] ?? null);
    $centavos = $monto === null ? 0 : api_centavos($monto);
    if ($centavos <= 0) {
        json_response(['ok' => false, 'error' => $mensaje], 422);
    }

    return $centavos;
}

/**
 * Compromiso opcional que viene junto con otra alta (ej. Nueva consulta).
 * Valida antes de abrir la transacción; devuelve null si no se pidió.
 *
 * @return array{centavos: int, fecha: string, concepto: string, nota: ?string}|null
 */
function compromiso_opcional_validar(mixed $datos): ?array
{
    if (!is_array($datos)) {
        return null;
    }

    return [
        'centavos' => compromiso_monto_obligatorio($datos, 'monto', 'Ingresá un monto de pago válido, mayor a cero.'),
        'fecha' => compromiso_fecha_obligatoria($datos, 'fecha_compromiso'),
        'concepto' => api_string($datos, 'concepto', 150),
        'nota' => api_nullable_string($datos, 'nota', 2000),
    ];
}
