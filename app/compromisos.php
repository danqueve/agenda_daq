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

/**
 * Días de atraso, por día: un pendiente cuenta hasta hoy; uno ya registrado, los
 * que pasaron entre la fecha prometida y el registro. Nunca es negativo.
 * Requiere el alias `c`.
 */
const COMPROMISO_ATRASO_SQL = "CASE
        WHEN c.estado = 'pendiente' THEN GREATEST(DATEDIFF(CURDATE(), DATE(c.fecha_compromiso)), 0)
        WHEN c.fecha_resolucion IS NULL THEN 0
        ELSE GREATEST(DATEDIFF(DATE(c.fecha_resolucion), DATE(c.fecha_compromiso)), 0)
    END";

/**
 * Fechas del mes que ya llegaron (del día 1 a hoy): la base del cumplimiento del mes. Requiere el alias `c`.
 */
const COMPROMISO_MES_LLEGADO_SQL = "DATE(c.fecha_compromiso) BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND CURDATE()";

/**
 * Lo prometido por un compromiso, sin contar dos veces lo que se movió a otra fecha: un parcial con el saldo ya
 * agendado entra solo por lo que se pagó (el saldo cuenta en el compromiso nuevo). Requiere el alias `c`.
 */
const COMPROMISO_PROMETIDO_SQL = "IF(c.estado = 'parcial' AND EXISTS (SELECT 1 FROM compromisos h WHERE h.origen_id = c.id), c.monto_pagado, c.monto)";

/** Columnas que el frontend necesita de cada compromiso (alias `c`). */
const COMPROMISO_COLUMNAS_SQL = 'c.id, c.contacto_id, c.monto, c.concepto, c.fecha_compromiso, c.estado, c.monto_pagado,
        c.fecha_resolucion, c.nota, c.origen_id, c.creado_por_nombre, c.resuelto_por_nombre, c.creado_en,
        c.ultimo_aviso_en, c.ultimo_aviso_por_nombre';

/** Opciones fijas del campo Concepto; se suman los conceptos más usados de la base. */
const COMPROMISO_CONCEPTOS_FIJOS = ['Cuota', 'Saldo', 'Refinanciación', 'Entrega', 'Otro'];

/** Variables que se pueden usar en las plantillas de WhatsApp. */
const COMPROMISO_PLANTILLA_VARIABLES = ['nombre', 'monto', 'fecha', 'concepto', 'dias_atraso'];

/**
 * Plantillas por defecto del recordatorio por WhatsApp. Lo que edita el admin se
 * guarda en config (clave wa_plantilla_<tipo>); sin fila, se usa este texto.
 */
const COMPROMISO_PLANTILLAS_WHATSAPP = [
    'antes' => 'Hola {nombre}, ¿cómo andás? Te escribo para recordarte que el {fecha} vence tu pago de {monto} ({concepto}). Cualquier cosa avisanos. ¡Gracias!',
    'hoy' => 'Hola {nombre}, ¿todo bien? Hoy vence tu pago de {monto} ({concepto}). Si ya lo hiciste, avisanos así lo registramos. ¡Gracias!',
    'vencido' => 'Hola {nombre}, ¿cómo estás? Tenés pendiente un pago de {monto} ({concepto}) que vencía el {fecha}, con {dias_atraso} de atraso. ¿Cuándo podrías regularizarlo? Cualquier cosa, contanos.',
];

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

/**
 * Plantillas de WhatsApp vigentes: lo guardado en config o, si no hay, el texto por defecto.
 *
 * @return array<string, string>
 */
function compromiso_plantillas_whatsapp(PDO $db): array
{
    $plantillas = COMPROMISO_PLANTILLAS_WHATSAPP;
    $claves = array_map(static fn (string $tipo): string => 'wa_plantilla_' . $tipo, array_keys($plantillas));
    $stmt = $db->prepare('SELECT clave, valor FROM config WHERE clave IN (' . implode(',', array_fill(0, count($claves), '?')) . ')');
    $stmt->execute($claves);
    foreach ($stmt as $fila) {
        $tipo = substr((string) $fila['clave'], strlen('wa_plantilla_'));
        if (trim((string) $fila['valor']) !== '') {
            $plantillas[$tipo] = (string) $fila['valor'];
        }
    }

    return $plantillas;
}

/**
 * Inserta un compromiso pendiente y devuelve su id. $autor ({id, nombre}) sirve para
 * que un compromiso reprogramado siga a nombre de quien cargó el original; sin él,
 * queda a nombre del usuario actual.
 *
 * @param array{id: ?int, nombre: string}|null $autor
 */
function compromiso_insertar(PDO $db, int $contactoId, int $centavos, string $concepto, string $fecha, ?string $nota, ?int $origenId, ?array $autor = null): int
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
        'usuario_id' => $autor ? $autor['id'] : usuario_actual_id(),
        'usuario_nombre' => $autor ? $autor['nombre'] : usuario_actual_nombre(),
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

/**
 * Totales por situación sobre los compromisos que cumplen $where (alias `c`).
 *
 * @param list<string> $where
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function compromisos_resumen(PDO $db, array $where, array $params): array
{
    $condiciones = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare(
        "SELECT
            COALESCE(SUM(c.estado = 'pendiente'), 0) AS pendientes,
            COALESCE(SUM(IF(c.estado = 'pendiente' AND DATE(c.fecha_compromiso) < CURDATE(), c.monto, 0)), 0) AS vencidos_monto,
            COALESCE(SUM(c.estado = 'pendiente' AND DATE(c.fecha_compromiso) < CURDATE()), 0) AS vencidos_cantidad,
            COALESCE(SUM(IF(c.estado = 'pendiente' AND DATE(c.fecha_compromiso) = CURDATE(), c.monto, 0)), 0) AS hoy_monto,
            COALESCE(SUM(c.estado = 'pendiente' AND DATE(c.fecha_compromiso) = CURDATE()), 0) AS hoy_cantidad,
            COALESCE(SUM(IF(c.estado = 'pendiente' AND DATE(c.fecha_compromiso) > CURDATE() AND DATE(c.fecha_compromiso) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY), c.monto, 0)), 0) AS proximos_monto,
            COALESCE(SUM(c.estado = 'pendiente' AND DATE(c.fecha_compromiso) > CURDATE() AND DATE(c.fecha_compromiso) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)), 0) AS proximos_cantidad,
            COALESCE(SUM(IF(c.estado IN ('pagado','parcial') AND c.fecha_resolucion >= DATE_FORMAT(CURDATE(), '%Y-%m-01'), c.monto_pagado, 0)), 0) AS cobrado_mes
         FROM compromisos c{$condiciones}"
    );
    $stmt->execute($params);
    $r = $stmt->fetch();

    return [
        'pendientes' => (int) $r['pendientes'],
        'vencidos' => ['monto' => $r['vencidos_monto'], 'cantidad' => (int) $r['vencidos_cantidad']],
        'hoy' => ['monto' => $r['hoy_monto'], 'cantidad' => (int) $r['hoy_cantidad']],
        'proximos' => ['monto' => $r['proximos_monto'], 'cantidad' => (int) $r['proximos_cantidad']],
        'cobrado_mes' => $r['cobrado_mes'],
    ];
}

/**
 * Una fila por quien cargó compromisos. Cumplimiento del mes: del $ prometido
 * para fechas del mes que ya llegaron (día 1 a hoy), el % que se cobró.
 * Lo prometido no cuenta dos veces lo que se movió a otra fecha: un compromiso
 * 'reprogramado' no entra (cuenta el nuevo, en su fecha) y un pago parcial con el
 * saldo ya agendado entra solo por lo que se pagó (el saldo cuenta en el nuevo).
 *
 * @return list<array<string, mixed>>
 */
function compromisos_por_usuario(PDO $db): array
{
    $mes = COMPROMISO_MES_LLEGADO_SQL;
    $prometido = COMPROMISO_PROMETIDO_SQL;
    $filas = $db->query(
        "SELECT c.creado_por_usuario_id AS usuario_id,
                COALESCE(MAX(u.nombre), NULLIF(MAX(c.creado_por_nombre), ''), 'Sin registrar') AS nombre,
                COUNT(*) AS total,
                SUM(c.estado = 'pendiente') AS pendientes_cantidad,
                COALESCE(SUM(IF(c.estado = 'pendiente', c.monto, 0)), 0) AS pendientes_monto,
                SUM(c.estado = 'pendiente' AND DATE(c.fecha_compromiso) < CURDATE()) AS vencidos_cantidad,
                COALESCE(SUM(IF(c.estado = 'pendiente' AND DATE(c.fecha_compromiso) < CURDATE(), c.monto, 0)), 0) AS vencidos_monto,
                COALESCE(SUM(IF(c.estado IN ('pagado','parcial') AND c.fecha_resolucion >= DATE_FORMAT(CURDATE(), '%Y-%m-01'), c.monto_pagado, 0)), 0) AS cobrado_mes,
                COALESCE(SUM(IF({$mes} AND c.estado <> 'reprogramado', {$prometido}, 0)), 0) AS prometido_mes,
                COALESCE(SUM(IF({$mes} AND c.estado IN ('pagado','parcial'), c.monto_pagado, 0)), 0) AS cobrado_prometido_mes,
                MAX(c.creado_por_nombre) AS nombre_copiado
         FROM compromisos c LEFT JOIN usuarios u ON u.id = c.creado_por_usuario_id
         GROUP BY c.creado_por_usuario_id, IF(c.creado_por_usuario_id IS NULL, c.creado_por_nombre, NULL)
         ORDER BY nombre ASC"
    )->fetchAll();

    $salida = [];
    foreach ($filas as $f) {
        $prometido = api_centavos($f['prometido_mes']);
        $cobrado = api_centavos($f['cobrado_prometido_mes']);
        $usuarioId = $f['usuario_id'] === null ? null : (int) $f['usuario_id'];
        $salida[] = [
            'usuario_id' => $usuarioId,
            // Valor que se manda tal cual en ?creado_por= para filtrar por esta fila.
            'filtro' => $usuarioId !== null ? (string) $usuarioId : 'ex:' . $f['nombre_copiado'],
            'nombre' => $f['nombre'],
            'total' => (int) $f['total'],
            'pendientes' => ['cantidad' => (int) $f['pendientes_cantidad'], 'monto' => $f['pendientes_monto']],
            'vencidos' => ['cantidad' => (int) $f['vencidos_cantidad'], 'monto' => $f['vencidos_monto']],
            'cobrado_mes' => $f['cobrado_mes'],
            'cumplimiento_mes' => $prometido > 0 ? (int) round($cobrado * 100 / $prometido) : null,
        ];
    }

    return $salida;
}

/**
 * Cumplimiento del mes sobre los compromisos que cumplen $where (alias `c`): de lo prometido para fechas del mes que
 * ya llegaron, cuánto se cobró. Misma regla que compromisos_por_usuario(): un reprogramado no cuenta (cuenta el nuevo,
 * en su fecha) y un parcial con el saldo agendado cuenta por lo que se pagó.
 *
 * @param list<string> $where
 * @param array<string, mixed> $params
 * @return array{prometido: string, cobrado: string, porcentaje: ?int} porcentaje es null si no hay nada prometido
 */
function compromisos_cumplimiento(PDO $db, array $where, array $params): array
{
    $condiciones = implode(' AND ', array_merge([COMPROMISO_MES_LLEGADO_SQL], $where));
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(IF(c.estado <> 'reprogramado', " . COMPROMISO_PROMETIDO_SQL . ", 0)), 0) AS prometido,
                COALESCE(SUM(IF(c.estado IN ('pagado','parcial'), c.monto_pagado, 0)), 0) AS cobrado
         FROM compromisos c WHERE {$condiciones}"
    );
    $stmt->execute($params);
    $fila = $stmt->fetch();
    $prometido = api_centavos($fila['prometido']);

    return [
        'prometido' => $fila['prometido'],
        'cobrado' => $fila['cobrado'],
        'porcentaje' => $prometido > 0 ? (int) round(api_centavos($fila['cobrado']) * 100 / $prometido) : null,
    ];
}

/**
 * Lo que falta cobrar y lo ya cobrado en cada uno de los 14 días del tablero de Hoy: esta semana y la siguiente, de
 * lunes a domingo. $where y $params acotan como en compromisos_resumen() (alias `c`).
 *
 * Estado de cada día: «vencido» (pendientes en un día pasado), «hoy» o «proximo» (pendientes hoy o más adelante),
 * «cobrado» (nada pendiente y algo cobrado), «resuelto» (solo incumplidos o reprogramados) o null (sin compromisos).
 * `cantidad` y `monto` son lo pendiente; `cobrado`, lo recibido en pagados y parciales con fecha ese día.
 *
 * @param list<string> $where
 * @param array<string, mixed> $params
 * @return list<array{iso: string, estado: ?string, cantidad: int, monto: string, cobrado: string}>
 */
function compromisos_tablero_dias(PDO $db, array $where, array $params): array
{
    $hoy = date('Y-m-d');
    $lunes = date('Y-m-d', strtotime('-' . ((int) date('N') - 1) . ' days'));
    $condiciones = implode(' AND ', array_merge(['DATE(c.fecha_compromiso) BETWEEN :tablero_desde AND :tablero_hasta'], $where));
    $stmt = $db->prepare(
        "SELECT DATE(c.fecha_compromiso) AS dia, COUNT(*) AS total, SUM(c.estado = 'pendiente') AS pendientes,
                COALESCE(SUM(IF(c.estado = 'pendiente', c.monto, 0)), 0) AS pendiente_monto,
                COALESCE(SUM(IF(c.estado IN ('pagado','parcial'), c.monto_pagado, 0)), 0) AS cobrado
         FROM compromisos c WHERE {$condiciones} GROUP BY dia"
    );
    $stmt->execute($params + ['tablero_desde' => $lunes, 'tablero_hasta' => date('Y-m-d', strtotime("{$lunes} +13 days"))]);
    $porDia = [];
    foreach ($stmt->fetchAll() as $fila) {
        $porDia[$fila['dia']] = $fila;
    }

    $dias = [];
    for ($i = 0; $i < 14; $i++) {
        $iso = date('Y-m-d', strtotime("{$lunes} +{$i} days"));
        $fila = $porDia[$iso] ?? null;
        $pendientes = $fila === null ? 0 : (int) $fila['pendientes'];
        $cobrado = $fila === null ? '0.00' : $fila['cobrado'];
        $estado = null;
        if ($pendientes > 0) {
            $estado = $iso < $hoy ? 'vencido' : ($iso === $hoy ? 'hoy' : 'proximo');
        } elseif ($fila !== null) {
            $estado = api_centavos($cobrado) > 0 ? 'cobrado' : 'resuelto';
        }
        $dias[] = [
            'iso' => $iso, 'estado' => $estado, 'cantidad' => $pendientes,
            'monto' => $fila === null ? '0.00' : $fila['pendiente_monto'], 'cobrado' => $cobrado,
        ];
    }

    return $dias;
}
