<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/** Minutos que se reutiliza el resumen de un cliente de SAS antes de volver a consultarlo. */
const SAS_CACHE_MINUTOS = 10;

/** Si SAS no responde, segundos que no se vuelve a intentar (para no esperar el timeout en cada pantalla). */
const SAS_PAUSA_SEGUNDOS = 60;

/** Cuotas que SAS considera impagas (mismo criterio que su agente de cobranza). */
const SAS_CUOTAS_IMPAGAS = "('PENDIENTE','VENCIDA','CAP_PAGADA','PARCIAL')";

/**
 * Conexión de SOLO LECTURA a la base de SAS Imperio. Se usa un usuario MySQL con SELECT
 * únicamente sobre las columnas que lee la Agenda (sql/sas_usuario_solo_lectura.sql) y,
 * además, la sesión se abre en READ ONLY: aunque el usuario tuviera permisos de escritura,
 * la Agenda nunca escribe en SAS.
 */
final class SasDb
{
    private static ?PDO $instancia = null;
    private static bool $intentado = false;

    public static function habilitado(): bool
    {
        return SAS_DB_NAME !== '' && SAS_DB_USER !== '';
    }

    /** Devuelve null si la integración está apagada o SAS no responde. */
    public static function get(): ?PDO
    {
        if (!self::habilitado()) {
            return null;
        }
        if (self::$intentado) {
            return self::$instancia;
        }
        self::$intentado = true;
        if (self::enPausa()) {
            return null;
        }

        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', SAS_DB_HOST ?: 'localhost', SAS_DB_NAME);
            $pdo = new PDO($dsn, SAS_DB_USER, SAS_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 3,
            ]);
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            self::$instancia = $pdo;
            self::marcarPausa(false);
        } catch (Throwable $error) {
            error_log('SAS Imperio: no se pudo conectar: ' . $error->getMessage());
            self::$instancia = null;
            self::marcarPausa(true);
        }

        return self::$instancia;
    }

    private static function archivoPausa(): string
    {
        return dirname(__DIR__) . '/storage/sas_no_disponible';
    }

    private static function enPausa(): bool
    {
        $archivo = self::archivoPausa();

        return is_file($archivo) && time() - (int) @filemtime($archivo) < SAS_PAUSA_SEGUNDOS;
    }

    private static function marcarPausa(bool $caido): void
    {
        $archivo = self::archivoPausa();
        if ($caido) {
            @touch($archivo);
        } elseif (is_file($archivo)) {
            @unlink($archivo);
        }
    }

    private function __construct()
    {
    }
}

/**
 * Busca clientes de SAS por celular, DNI o apellido/nombre (máx. 8).
 *
 * @return list<array<string, mixed>>
 */
function sas_buscar_clientes(PDO $sas, string $texto): array
{
    $texto = trim($texto);
    $digitos = preg_replace('/\D+/', '', $texto) ?? '';
    $soloNumeros = $digitos !== '' && preg_match('/^[\d\s().+-]+$/', $texto) === 1;

    $where = [];
    $params = [];
    if ($soloNumeros) {
        if (strlen($digitos) < 6) {
            return [];
        }
        // DNI por prefijo; teléfono por los últimos dígitos del número
        // nacional, sin importar cómo lo haya tipeado cada sistema.
        $nacional = preg_replace('/^549/', '', normalizar_celular($texto)) ?? $digitos;
        $cola = substr($nacional, -min(10, strlen($nacional)));
        $limpio = "REGEXP_REPLACE(%s, '[^0-9]', '')";
        $where[] = '(c.dni LIKE :dni OR ' . sprintf($limpio, 'c.telefono') . ' LIKE :tel1 OR ' . sprintf($limpio, 'c.telefono_alt') . ' LIKE :tel2)';
        $params['dni'] = $digitos . '%';
        $params['tel1'] = '%' . $cola . '%';
        $params['tel2'] = '%' . $cola . '%';
    } else {
        $palabras = array_slice(preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 4);
        if ($palabras === []) {
            return [];
        }
        foreach ($palabras as $i => $palabra) {
            $where[] = "CONCAT(c.apellidos, ' ', c.nombres) LIKE :p{$i}";
            $params["p{$i}"] = '%' . addcslashes($palabra, '%_\\') . '%';
        }
    }

    $stmt = $sas->prepare(
        'SELECT c.id, c.nombres, c.apellidos, c.dni, c.telefono, c.telefono_alt, c.localidad, c.estado,
                u.nombre AS cobrador_nombre, u.apellido AS cobrador_apellido
         FROM ic_clientes c
         LEFT JOIN ic_usuarios u ON u.id = c.cobrador_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.apellidos, c.nombres LIMIT 8'
    );
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $fila) {
        $telefono = trim((string) ($fila['telefono'] ?? '')) ?: trim((string) ($fila['telefono_alt'] ?? ''));
        $norm = $telefono === '' ? '' : normalizar_celular($telefono);
        $items[] = [
            'id' => (int) $fila['id'],
            'nombre' => trim(((string) $fila['nombres']) . ' ' . ((string) $fila['apellidos'])),
            'dni' => (string) ($fila['dni'] ?? ''),
            'telefono' => $telefono,
            'celular_norm' => preg_match('/^549\d{10}$/', $norm) ? $norm : '',
            'localidad' => (string) ($fila['localidad'] ?? ''),
            'estado' => (string) ($fila['estado'] ?? ''),
            'cobrador' => sas_nombre_cobrador($fila),
        ];
    }

    return $items;
}

/**
 * Nombre del cobrador que SAS le asignó al cliente (ic_clientes.cobrador_id → ic_usuarios), a partir de una fila
 * que trae `cobrador_nombre` y `cobrador_apellido` del LEFT JOIN. Null si no tiene.
 */
function sas_nombre_cobrador(array $fila): ?string
{
    $nombre = trim(((string) ($fila['cobrador_nombre'] ?? '')) . ' ' . ((string) ($fila['cobrador_apellido'] ?? '')));

    return $nombre !== '' ? mb_substr($nombre, 0, 100) : null;
}

/**
 * Días hábiles de atraso, como los cuenta SAS (config/funciones.php): de lunes a sábado, sin
 * feriados, desde el día siguiente al vencimiento hasta hoy. 0 si todavía no venció.
 */
function sas_dias_habiles_atraso(string $fechaVencimiento, ?string $hoy = null): int
{
    $hasta = new DateTimeImmutable($hoy ?? 'today');
    $vencimiento = new DateTimeImmutable($fechaVencimiento);
    if ($hasta <= $vencimiento) {
        return 0;
    }
    $dias = 0;
    for ($dia = $vencimiento->modify('+1 day'); $dia <= $hasta; $dia = $dia->modify('+1 day')) {
        if ((int) $dia->format('N') !== 7) {
            $dias++;
        }
    }

    return $dias;
}

function sas_decimal(mixed $valor): string
{
    return number_format((float) $valor, 2, '.', '');
}

/**
 * Resumen de varios clientes de SAS con tres consultas agrupadas (sea cual sea la cantidad
 * de clientes). Los montos van SIN mora: la mora la calcula SAS con su propia regla.
 * Lo que falta de una cuota es monto_cuota − saldo_pagado − pagos informados todavía sin
 * aprobar (SAS los resta porque «ya fueron cobrados»).
 *
 * @param list<int> $clienteIds
 * @return array<int, array<string, mixed>> por id de cliente de SAS
 */
function sas_resumen_clientes(PDO $sas, array $clienteIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $clienteIds), static fn (int $id): bool => $id > 0)));
    if ($ids === []) {
        return [];
    }
    $marcas = [];
    $params = [];
    foreach ($ids as $i => $id) {
        $marcas[] = ':c' . $i;
        $params['c' . $i] = $id;
    }
    $en = implode(',', $marcas);
    $hoy = date('Y-m-d');
    $falta = 'GREATEST(cu.monto_cuota - cu.saldo_pagado - COALESCE(t.pendiente, 0), 0)';

    // 1) El cliente, sus créditos activos y sus cuotas impagas.
    $stmt = $sas->prepare(
        "SELECT cl.id AS cliente_id, cl.estado AS estado_cliente,
                COUNT(DISTINCT cr.id) AS creditos_activos,
                COALESCE(SUM(CASE WHEN cu.id IS NOT NULL THEN {$falta} ELSE 0 END), 0) AS saldo,
                COALESCE(SUM(CASE WHEN cu.fecha_vencimiento < :hoy1 THEN 1 ELSE 0 END), 0) AS cuotas_vencidas,
                COALESCE(SUM(CASE WHEN cu.fecha_vencimiento < :hoy2 THEN {$falta} ELSE 0 END), 0) AS monto_vencido,
                MIN(CASE WHEN cu.fecha_vencimiento < :hoy3 THEN cu.fecha_vencimiento END) AS vencida_mas_vieja
         FROM ic_clientes cl
         LEFT JOIN ic_creditos cr ON cr.cliente_id = cl.id AND cr.estado IN ('EN_CURSO','MOROSO')
         LEFT JOIN ic_cuotas cu ON cu.credito_id = cr.id AND cu.estado IN " . SAS_CUOTAS_IMPAGAS . "
         LEFT JOIN (SELECT cuota_id, SUM(monto_total) AS pendiente FROM ic_pagos_temporales
                    WHERE estado = 'PENDIENTE' GROUP BY cuota_id) t ON t.cuota_id = cu.id
         WHERE cl.id IN ({$en})
         GROUP BY cl.id, cl.estado"
    );
    $stmt->execute($params + ['hoy1' => $hoy, 'hoy2' => $hoy, 'hoy3' => $hoy]);

    $resumen = [];
    foreach ($stmt->fetchAll() as $fila) {
        $masVieja = $fila['vencida_mas_vieja'] === null ? null : (string) $fila['vencida_mas_vieja'];
        $resumen[(int) $fila['cliente_id']] = [
            'existe' => true,
            'estado_cliente' => (string) $fila['estado_cliente'],
            'creditos_activos' => (int) $fila['creditos_activos'],
            'saldo' => sas_decimal($fila['saldo']),
            'cuotas_vencidas' => (int) $fila['cuotas_vencidas'],
            'monto_vencido' => sas_decimal($fila['monto_vencido']),
            'vencida_mas_vieja' => $masVieja,
            'dias_atraso' => $masVieja === null ? 0 : sas_dias_habiles_atraso($masVieja, $hoy),
            'ultimo_pago' => null,
            'informados' => null,
        ];
    }

    // 2) Último pago confirmado: todos los de la última jornada, sin los revertidos.
    $stmt = $sas->prepare(
        "SELECT x.cliente_id, x.fecha, SUM(x.monto) AS monto
         FROM (SELECT cr.cliente_id, pc.fecha_jornada AS fecha, pc.monto_total AS monto,
                      DENSE_RANK() OVER (PARTITION BY cr.cliente_id ORDER BY pc.fecha_jornada DESC) AS posicion
               FROM ic_pagos_confirmados pc
               JOIN ic_cuotas cu ON cu.id = pc.cuota_id
               JOIN ic_creditos cr ON cr.id = cu.credito_id
               WHERE cr.cliente_id IN ({$en}) AND pc.revertido = 0 AND pc.fecha_jornada IS NOT NULL) x
         WHERE x.posicion = 1
         GROUP BY x.cliente_id, x.fecha"
    );
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $fila) {
        if (isset($resumen[(int) $fila['cliente_id']])) {
            $resumen[(int) $fila['cliente_id']]['ultimo_pago'] = ['fecha' => (string) $fila['fecha'], 'monto' => sas_decimal($fila['monto'])];
        }
    }

    // 3) Pagos que el cobrador informó y todavía esperan aprobación.
    $stmt = $sas->prepare(
        "SELECT cr.cliente_id, COUNT(*) AS cantidad, SUM(pt.monto_total) AS monto,
                MAX(COALESCE(NULLIF(pt.fecha_jornada, '2000-01-01'), DATE(pt.fecha_registro))) AS fecha
         FROM ic_pagos_temporales pt
         JOIN ic_cuotas cu ON cu.id = pt.cuota_id
         JOIN ic_creditos cr ON cr.id = cu.credito_id
         WHERE cr.cliente_id IN ({$en}) AND pt.estado = 'PENDIENTE'
         GROUP BY cr.cliente_id"
    );
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $fila) {
        if (isset($resumen[(int) $fila['cliente_id']])) {
            $resumen[(int) $fila['cliente_id']]['informados'] = [
                'cantidad' => (int) $fila['cantidad'], 'monto' => sas_decimal($fila['monto']), 'fecha' => (string) $fila['fecha'],
            ];
        }
    }

    foreach ($ids as $id) {
        $resumen[$id] ??= ['existe' => false];
    }

    return $resumen;
}

/** @return array<string, mixed> Resumen de un cliente de SAS (['existe' => false] si no está). */
function sas_resumen_cliente(PDO $sas, int $clienteId): array
{
    return sas_resumen_clientes($sas, [$clienteId])[$clienteId] ?? ['existe' => false];
}

/**
 * Resumen de SAS de varios contactos de la Agenda. Reutiliza el caché de la Agenda
 * (SAS_CACHE_MINUTOS) y consulta SAS solo por los clientes que faltan, todos juntos. Si SAS
 * no responde se devuelve lo último guardado, marcado como desactualizado; sin nada guardado,
 * ese contacto queda afuera y `disponible` en false. Nunca se devuelven detalles técnicos.
 *
 * @param list<int> $contactoIds
 * @return array{habilitado: bool, disponible: bool, items: array<int, array<string, mixed>>}
 */
function sas_resumenes_de_contactos(PDO $db, array $contactoIds, bool $forzar = false): array
{
    if (!SasDb::habilitado()) {
        return ['habilitado' => false, 'disponible' => true, 'items' => []];
    }
    $contactoIds = array_values(array_unique(array_filter(array_map('intval', $contactoIds), static fn (int $id): bool => $id > 0)));
    if ($contactoIds === []) {
        return ['habilitado' => true, 'disponible' => true, 'items' => []];
    }

    $marcas = implode(',', array_fill(0, count($contactoIds), '?'));
    $stmt = $db->prepare("SELECT id, sas_cliente_id FROM contactos WHERE sas_cliente_id IS NOT NULL AND id IN ({$marcas})");
    $stmt->execute($contactoIds);
    $vinculos = [];
    foreach ($stmt->fetchAll() as $fila) {
        $vinculos[(int) $fila['id']] = (int) $fila['sas_cliente_id'];
    }
    if ($vinculos === []) {
        return ['habilitado' => true, 'disponible' => true, 'items' => []];
    }

    $sasIds = array_values(array_unique($vinculos));
    $marcas = implode(',', array_fill(0, count($sasIds), '?'));
    $stmt = $db->prepare(
        "SELECT sas_cliente_id, datos, consultado_en, consultado_en >= (NOW() - INTERVAL " . SAS_CACHE_MINUTOS . " MINUTE) AS vigente
         FROM sas_resumen_cache WHERE sas_cliente_id IN ({$marcas})"
    );
    $stmt->execute($sasIds);
    $guardados = [];
    foreach ($stmt->fetchAll() as $fila) {
        $guardados[(int) $fila['sas_cliente_id']] = [
            'datos' => json_decode((string) $fila['datos'], true) ?: [], 'consultado_en' => (string) $fila['consultado_en'], 'vigente' => (bool) $fila['vigente'],
        ];
    }

    $faltan = array_values(array_filter($sasIds, static fn (int $id): bool => $forzar || !($guardados[$id]['vigente'] ?? false)));
    $fallo = false;
    $desactualizados = [];
    if ($faltan !== []) {
        $sas = SasDb::get();
        $nuevos = [];
        if ($sas !== null) {
            try {
                $nuevos = sas_resumen_clientes($sas, $faltan);
            } catch (Throwable $error) {
                error_log('SAS Imperio: error al armar el resumen: ' . $error->getMessage());
            }
        }
        if ($nuevos === []) {
            $fallo = true;
            $desactualizados = $faltan;
        } else {
            $guardar = $db->prepare(
                'INSERT INTO sas_resumen_cache (sas_cliente_id, datos, consultado_en) VALUES (:id, :datos, NOW())
                 ON DUPLICATE KEY UPDATE datos = VALUES(datos), consultado_en = VALUES(consultado_en)'
            );
            foreach ($nuevos as $sasId => $datos) {
                $guardar->execute(['id' => $sasId, 'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE)]);
                $guardados[$sasId] = ['datos' => $datos, 'consultado_en' => date('Y-m-d H:i:s'), 'vigente' => true];
            }
            $db->exec('DELETE FROM sas_resumen_cache WHERE consultado_en < (NOW() - INTERVAL 7 DAY)');
        }
    }

    $items = [];
    foreach ($vinculos as $contactoId => $sasId) {
        if (!isset($guardados[$sasId])) {
            continue;
        }
        $items[$contactoId] = $guardados[$sasId]['datos'] + [
            'sas_cliente_id' => $sasId,
            'actualizado_en' => $guardados[$sasId]['consultado_en'],
            'desactualizado' => in_array($sasId, $desactualizados, true),
        ];
    }

    return ['habilitado' => true, 'disponible' => !$fallo, 'items' => $items];
}

// ---------- Vincular contactos con clientes de SAS ----------

/**
 * Últimos 10 dígitos de un celular argentino, sea cual sea el formato en que se cargó
 * (mismo criterio de normalizar_celular() que usa la Agenda); null si no es un celular válido.
 */
function sas_cola_celular(?string $telefono): ?string
{
    $telefono = trim((string) $telefono);
    if ($telefono === '') {
        return null;
    }

    return preg_match('/^549(\d{10})$/', normalizar_celular($telefono), $m) === 1 ? $m[1] : null;
}

/** DNI solo con dígitos; null si es demasiado corto para comparar. */
function sas_dni_digitos(?string $dni): ?string
{
    $digitos = preg_replace('/\D+/', '', (string) $dni) ?? '';

    return strlen($digitos) >= 6 ? $digitos : null;
}

/**
 * Carga una sola vez los clientes de SAS y los indexa por DNI y por celular, para comparar
 * muchos contactos sin una consulta por cada uno.
 *
 * @return array{dni: array<string, list<int>>, celular: array<string, list<int>>, datos: array<int, array{nombre: string, localidad: string, dni: string, cobrador: ?string}>}
 */
function sas_indice_clientes(PDO $sas): array
{
    $indice = ['dni' => [], 'celular' => [], 'datos' => []];
    $stmt = $sas->query(
        'SELECT c.id, c.nombres, c.apellidos, c.dni, c.telefono, c.telefono_alt, c.localidad,
                u.nombre AS cobrador_nombre, u.apellido AS cobrador_apellido
         FROM ic_clientes c
         LEFT JOIN ic_usuarios u ON u.id = c.cobrador_id'
    );
    foreach ($stmt as $fila) {
        $id = (int) $fila['id'];
        $indice['datos'][$id] = [
            'nombre' => trim(((string) $fila['nombres']) . ' ' . ((string) $fila['apellidos'])),
            'localidad' => (string) ($fila['localidad'] ?? ''),
            'dni' => sas_dni_digitos((string) ($fila['dni'] ?? '')) ?? '',
            'cobrador' => sas_nombre_cobrador($fila),
        ];
        if (($dni = sas_dni_digitos((string) ($fila['dni'] ?? ''))) !== null) {
            $indice['dni'][$dni][] = $id;
        }
        foreach ([$fila['telefono'] ?? null, $fila['telefono_alt'] ?? null] as $telefono) {
            if (($cola = sas_cola_celular($telefono)) !== null && !in_array($id, $indice['celular'][$cola] ?? [], true)) {
                $indice['celular'][$cola][] = $id;
            }
        }
    }

    return $indice;
}

/**
 * Clientes de SAS que coinciden con un contacto: primero DNI exacto y, si no hay, celular.
 *
 * @param array{dni: array<string, list<int>>, celular: array<string, list<int>>} $indice
 * @return array{0: ?string, 1: list<int>} método ('dni' | 'celular' | null) e ids candidatos
 */
function sas_coincidencias(array $indice, ?string $dni, ?string $celular): array
{
    $dni = sas_dni_digitos($dni);
    if ($dni !== null && ($indice['dni'][$dni] ?? []) !== []) {
        return ['dni', $indice['dni'][$dni]];
    }
    $cola = sas_cola_celular($celular);
    if ($cola !== null && ($indice['celular'][$cola] ?? []) !== []) {
        return ['celular', $indice['celular'][$cola]];
    }

    return [null, []];
}
