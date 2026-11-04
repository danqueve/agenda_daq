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
