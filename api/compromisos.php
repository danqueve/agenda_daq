<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/api.php';
require_once __DIR__ . '/../app/compromisos.php';

const COMPROMISOS_POR_PAGINA = 30;
const COMPROMISOS_CSV_MAXIMO = 5000;

// Orden permitido para el listado: el valor recibido solo elige una clave de
// este array, nunca se concatena al SQL.
const COMPROMISOS_ORDEN = [
    'fecha' => 'c.fecha_compromiso',
    'monto' => 'c.monto',
    'cliente' => 'k.nombre',
    'atraso' => 'dias_atraso',
    'creado_por' => 'c.creado_por_nombre',
];

/** @return array<string, mixed>|null */
function compromiso_buscar(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT ' . COMPROMISO_COLUMNAS_SQL . ', ' . COMPROMISO_SITUACION_SQL . ' AS situacion,
                k.nombre AS contacto_nombre, k.celular AS contacto_celular, k.celular_norm AS contacto_celular_norm, k.sas_cliente_id AS contacto_sas_cliente_id,
                k.estado AS contacto_estado, k.cobrador_nombre
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
    $cobrador = api_nullable_string($nuevo, 'cobrador_nombre', 100);

    $buscar = $db->prepare('SELECT id FROM contactos WHERE celular_norm = :celular_norm LIMIT 1');
    $buscar->execute(['celular_norm' => $celularNorm]);
    $existente = (int) $buscar->fetchColumn();
    if ($existente !== 0) {
        if ($dni !== null || $sasId !== null || $cobrador !== null) {
            $db->prepare(
                'UPDATE contactos SET dni = COALESCE(dni, :dni), sas_cliente_id = COALESCE(sas_cliente_id, :sas_id), cobrador_nombre = COALESCE(:cobrador, cobrador_nombre) WHERE id = :id'
            )->execute(['dni' => $dni, 'sas_id' => $sasId, 'cobrador' => $cobrador, 'id' => $existente]);
        }
        return $existente;
    }

    $insert = $db->prepare(
        "INSERT INTO contactos (nombre, celular, celular_norm, dni, sas_cliente_id, cobrador_nombre, origen, estado, motivo_cierre, creado_por_usuario_id, creado_por_nombre)
         VALUES (:nombre, :celular, :celular_norm, :dni, :sas_id, :cobrador, 'otro', 'cerrada', 'concreto', :usuario_id, :usuario_nombre)"
    );
    $insert->execute([
        'nombre' => $nombre, 'celular' => $celular, 'celular_norm' => $celularNorm,
        'dni' => $dni, 'sas_id' => $sasId, 'cobrador' => $cobrador,
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

/**
 * Reprogramar: el cliente avisó que paga otro día. Cierra el compromiso como
 * 'reprogramado' (no es un incumplimiento y no entra en el cumplimiento) y agenda
 * uno nuevo enlazado por origen_id, que sigue a nombre de quien cargó el original.
 * Todo en una transacción; el segundo usuario que lo intente recibe un 409.
 */
function compromiso_reprogramar(PDO $db, int $id, array $actual, array $body): never
{
    $nuevaFecha = compromiso_fecha_obligatoria($body, 'fecha');
    $dia = substr($nuevaFecha, 0, 10);
    if ($dia < date('Y-m-d')) {
        json_response(['ok' => false, 'error' => 'La nueva fecha tiene que ser hoy o posterior.'], 422);
    }
    if ($dia === substr((string) $actual['fecha_compromiso'], 0, 10)) {
        json_response(['ok' => false, 'error' => 'Elegí una fecha distinta a la actual.'], 422);
    }
    $nuevoMonto = trim((string) ($body['monto'] ?? '')) !== ''
        ? compromiso_monto_obligatorio($body, 'monto', 'Ingresá un monto válido para el nuevo compromiso.')
        : api_centavos($actual['monto']);

    $autorId = $db->prepare('SELECT creado_por_usuario_id FROM compromisos WHERE id = :id');
    $autorId->execute(['id' => $id]);
    $autorId = $autorId->fetchColumn();
    $autor = ['id' => $autorId === false || $autorId === null ? null : (int) $autorId, 'nombre' => (string) $actual['creado_por_nombre']];

    $db->beginTransaction();
    try {
        $update = $db->prepare(
            "UPDATE compromisos
             SET estado = 'reprogramado', monto_pagado = NULL, fecha_resolucion = NOW(),
                 resuelto_por_usuario_id = :usuario_id, resuelto_por_nombre = :usuario_nombre,
                 nota = COALESCE(:nota, nota)
             WHERE id = :id AND estado = 'pendiente'"
        );
        $update->execute([
            'usuario_id' => usuario_actual_id(), 'usuario_nombre' => usuario_actual_nombre(),
            'nota' => api_nullable_string($body, 'nota', 2000), 'id' => $id,
        ]);
        if ($update->rowCount() === 0) {
            $db->rollBack();
            json_response(['ok' => false, 'error' => 'Este compromiso ya fue registrado por otra persona.'], 409);
        }
        $nuevoId = compromiso_insertar($db, (int) $actual['contacto_id'], $nuevoMonto, (string) $actual['concepto'], $nuevaFecha, null, $id, $autor);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    json_response(['ok' => true, 'data' => compromiso_buscar($db, $id), 'nuevo' => compromiso_buscar($db, $nuevoId)]);
}

/**
 * Se tocó el botón de WhatsApp de un compromiso pendiente: queda una nota en el
 * historial del contacto y el compromiso guarda el último aviso (quién y cuándo),
 * para que el resto del equipo vea que ya se avisó. Dos toques seguidos del mismo
 * usuario cuentan como uno.
 */
function compromiso_aviso(PDO $db, int $id, array $actual): never
{
    if ($actual['estado'] !== 'pendiente') {
        json_response(['ok' => false, 'error' => 'Solo se avisa por compromisos pendientes.'], 422);
    }
    $repetido = $actual['ultimo_aviso_en'] !== null
        && $actual['ultimo_aviso_por_nombre'] === usuario_actual_nombre()
        && strtotime((string) $actual['ultimo_aviso_en']) > time() - 60;

    if (!$repetido) {
        $centavos = api_centavos($actual['monto']);
        $monto = '$' . ($centavos % 100 === 0 ? number_format($centavos / 100, 0, ',', '.') : number_format($centavos / 100, 2, ',', '.'));
        $db->beginTransaction();
        try {
            $db->prepare(
                "INSERT INTO seguimientos (contacto_id, tipo, resultado, nota)
                 VALUES (:contacto_id, 'nota', 'mensaje_enviado', :nota)"
            )->execute([
                'contacto_id' => (int) $actual['contacto_id'],
                'nota' => "WhatsApp: recordatorio de pago {$monto} · " . usuario_actual_nombre(),
            ]);
            $db->prepare(
                'UPDATE compromisos
                 SET ultimo_aviso_en = NOW(), ultimo_aviso_por_usuario_id = :usuario_id, ultimo_aviso_por_nombre = :usuario_nombre
                 WHERE id = :id'
            )->execute(['usuario_id' => usuario_actual_id(), 'usuario_nombre' => usuario_actual_nombre(), 'id' => $id]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    json_response(['ok' => true, 'data' => compromiso_buscar($db, $id)]);
}

/**
 * Opciones del campo Concepto y del filtro de Pagos. Los frecuentes no repiten lo que
 * ya cubren las opciones fijas ("Cuota 3" ya está en "Cuota"); los grupos son la
 * primera palabra de cada concepto.
 */
function compromisos_conceptos(PDO $db): never
{
    $fijos = array_map('mb_strtolower', COMPROMISO_CONCEPTOS_FIJOS);
    $filas = $db->query(
        "SELECT MIN(TRIM(concepto)) AS texto, COUNT(*) AS usos
         FROM compromisos WHERE TRIM(concepto) <> ''
         GROUP BY TRIM(concepto) ORDER BY usos DESC, texto ASC LIMIT 60"
    );
    $frecuentes = [];
    foreach ($filas as $fila) {
        if (in_array(mb_strtolower(explode(' ', (string) $fila['texto'])[0]), $fijos, true)) {
            continue;
        }
        $frecuentes[] = (string) $fila['texto'];
        if (count($frecuentes) === 10) {
            break;
        }
    }

    $grupos = $db->query(
        "SELECT MIN(SUBSTRING_INDEX(TRIM(concepto), ' ', 1)) AS valor, COUNT(*) AS cantidad
         FROM compromisos WHERE TRIM(concepto) <> ''
         GROUP BY SUBSTRING_INDEX(TRIM(concepto), ' ', 1) ORDER BY cantidad DESC, valor ASC LIMIT 30"
    )->fetchAll();
    foreach ($grupos as &$grupo) {
        // MIN() sobre una colación _ci devuelve cualquier variante ("cuota"/"Cuota"): se normaliza la inicial.
        $grupo['valor'] = mb_strtoupper(mb_substr((string) $grupo['valor'], 0, 1)) . mb_substr((string) $grupo['valor'], 1);
        $grupo['cantidad'] = (int) $grupo['cantidad'];
    }
    unset($grupo);
    $sin = (int) $db->query("SELECT COUNT(*) FROM compromisos WHERE TRIM(concepto) = ''")->fetchColumn();

    json_response(['ok' => true, 'data' => [
        'fijos' => COMPROMISO_CONCEPTOS_FIJOS, 'frecuentes' => $frecuentes, 'grupos' => $grupos, 'sin_concepto' => $sin,
    ]]);
}

/** Fecha YYYY-MM-DD del query string, o null si no vino. 422 si no es una fecha real. */
function compromiso_fecha_filtro(string $clave): ?string
{
    $valor = api_string($_GET, $clave, 10);
    if ($valor === '') {
        return null;
    }
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    $errores = DateTimeImmutable::getLastErrors();
    if (!$fecha || ($errores !== false && ($errores['warning_count'] || $errores['error_count']))) {
        json_response(['ok' => false, 'error' => "La fecha «{$clave}» no es válida."], 422);
    }

    return $fecha->format('Y-m-d');
}

/**
 * Filtros que también acotan los totales: rango de fechas y quién lo cargó.
 *
 * @return array{0: list<string>, 1: array<string, mixed>}
 */
function compromisos_filtros_base(): array
{
    $where = [];
    $params = [];

    $desde = compromiso_fecha_filtro('desde');
    $hasta = compromiso_fecha_filtro('hasta');
    if ($desde !== null && $hasta !== null && $hasta < $desde) {
        json_response(['ok' => false, 'error' => 'El rango no es válido: «hasta» es anterior a «desde».'], 422);
    }
    if ($desde !== null) {
        $where[] = 'DATE(c.fecha_compromiso) >= :f_desde';
        $params['f_desde'] = $desde;
    }
    if ($hasta !== null) {
        $where[] = 'DATE(c.fecha_compromiso) <= :f_hasta';
        $params['f_hasta'] = $hasta;
    }

    // "yo", un id de usuario, o "ex:<nombre>" para quien cargó con una cuenta ya borrada.
    $creadoPor = api_string($_GET, 'creado_por', 110);
    if ($creadoPor !== '') {
        if ($creadoPor === 'yo') {
            $where[] = 'c.creado_por_usuario_id = :f_usuario';
            $params['f_usuario'] = usuario_actual_id() ?? 0;
        } elseif (ctype_digit($creadoPor)) {
            $where[] = 'c.creado_por_usuario_id = :f_usuario';
            $params['f_usuario'] = (int) $creadoPor;
        } elseif (str_starts_with($creadoPor, 'ex:') && mb_strlen($creadoPor) > 3) {
            $where[] = '(c.creado_por_usuario_id IS NULL AND c.creado_por_nombre = :f_ex)';
            $params['f_ex'] = mb_substr($creadoPor, 3, 100);
        } else {
            json_response(['ok' => false, 'error' => 'El filtro «creado_por» no es válido.'], 422);
        }
    }

    // Concepto agrupado por su primera palabra ("Cuota" incluye "Cuota 3"); "@sin" = sin concepto.
    $concepto = api_string($_GET, 'concepto', 150);
    if ($concepto === '@sin') {
        $where[] = "TRIM(c.concepto) = ''";
    } elseif ($concepto !== '') {
        $where[] = "SUBSTRING_INDEX(TRIM(c.concepto), ' ', 1) = :f_concepto";
        $params['f_concepto'] = explode(' ', trim($concepto))[0];
    }

    return [$where, $params];
}

/** Evita que Excel interprete como fórmula un texto cargado por un usuario. */
function compromiso_csv_celda(string $valor): string
{
    return $valor !== '' && strpbrk($valor[0], "=+-@\t\r") !== false ? "'" . $valor : $valor;
}

/**
 * CSV de lo que se está viendo (mismos filtros y orden): separador ";", UTF-8 con
 * BOM, fechas dd/mm/aaaa y coma decimal, como lo espera Excel en español.
 *
 * @param array<string, mixed> $params
 */
function compromisos_exportar_csv(PDO $db, string $from, array $params, string $orden): never
{
    $stmt = $db->prepare(
        'SELECT ' . COMPROMISO_COLUMNAS_SQL . ', ' . COMPROMISO_SITUACION_SQL . ' AS situacion, '
        . COMPROMISO_ATRASO_SQL . ' AS dias_atraso, k.nombre AS contacto_nombre, k.celular AS contacto_celular'
        . $from . ' ORDER BY ' . $orden . ' LIMIT ' . COMPROMISOS_CSV_MAXIMO
    );
    $stmt->execute($params);

    $estados = [
        'vencido' => 'Vencido', 'hoy' => 'Para hoy', 'proximo' => 'Próximo',
        'pagado' => 'Pagado', 'parcial' => 'Pago parcial', 'incumplido' => 'Incumplido', 'reprogramado' => 'Reprogramado',
    ];
    $fecha = static fn (?string $valor): string => $valor ? date('d/m/Y', strtotime($valor)) : '';
    $monto = static fn (?string $valor): string => $valor === null ? '' : number_format((float) $valor, 2, ',', '');

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="pagos-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";
    $salida = fopen('php://output', 'w');
    fputcsv($salida, ['Fecha', 'Cliente', 'Celular', 'Concepto', 'Monto', 'Pagado', 'Estado', 'Atraso (días)', 'Cargó', 'Resolvió', 'Fecha de registro', 'Nota'], ';', '"', '');
    foreach ($stmt as $f) {
        fputcsv($salida, array_map('compromiso_csv_celda', [
            $fecha($f['fecha_compromiso']), (string) $f['contacto_nombre'], (string) $f['contacto_celular'], (string) $f['concepto'],
            $monto($f['monto']), $monto($f['monto_pagado']), $estados[$f['situacion']] ?? '', (string) $f['dias_atraso'],
            (string) $f['creado_por_nombre'], (string) $f['resuelto_por_nombre'], $fecha($f['fecha_resolucion']),
            str_replace(["\r", "\n"], ' ', (string) $f['nota']),
        ]), ';', '"', '');
    }
    fclose($salida);
    exit;
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
        case 'reprogramados': $where[] = "c.estado = 'reprogramado'"; break;
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

    [$whereBase, $paramsBase] = compromisos_filtros_base();
    $where = array_merge($where, $whereBase);
    $params = array_merge($params, $paramsBase);
    $condiciones = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    // Sin ?orden= se mantiene el orden de siempre: pendientes primero, por fecha.
    $ordenSql = "c.estado = 'pendiente' DESC,
                  IF(c.estado = 'pendiente', c.fecha_compromiso, NULL) ASC,
                  c.fecha_resolucion DESC, c.id DESC";
    $orden = api_string($_GET, 'orden', 12);
    if ($orden !== '') {
        if (!isset(COMPROMISOS_ORDEN[$orden])) {
            json_response(['ok' => false, 'error' => 'El orden pedido no es válido.'], 422);
        }
        $dir = match (strtolower(api_string($_GET, 'dir', 4)) ?: 'asc') {
            'asc' => 'ASC',
            'desc' => 'DESC',
            default => json_response(['ok' => false, 'error' => 'La dirección del orden no es válida.'], 422),
        };
        $ordenSql = COMPROMISOS_ORDEN[$orden] . " {$dir}, c.id {$dir}";
    }

    $from = ' FROM compromisos c INNER JOIN contactos k ON k.id = c.contacto_id' . $condiciones;

    $formato = api_string($_GET, 'formato', 5);
    if ($formato === 'csv') {
        compromisos_exportar_csv($db, $from, $params, $ordenSql);
    }
    if ($formato !== '') {
        json_response(['ok' => false, 'error' => 'El formato pedido no es válido.'], 422);
    }

    $pagina = max(1, api_integer($_GET['pagina'] ?? 1));
    $offset = ($pagina - 1) * COMPROMISOS_POR_PAGINA;

    // Totales de TODO lo que cumple los filtros (no solo de la página): la fila de
    // totales de la tabla y los subtotales por día tienen que ser exactos.
    $totales = $db->prepare('SELECT COUNT(*) AS cantidad, COALESCE(SUM(c.monto), 0) AS monto, COALESCE(SUM(c.monto_pagado), 0) AS pagado' . $from);
    $totales->execute($params);
    $totales = $totales->fetch();
    $total = (int) $totales['cantidad'];

    $porDia = $db->prepare(
        'SELECT DATE(c.fecha_compromiso) AS dia, COUNT(*) AS cantidad, SUM(c.monto) AS monto,
                COALESCE(SUM(IF(c.estado = \'pendiente\', c.monto, 0)), 0) AS pendiente' . $from . '
         GROUP BY dia ORDER BY dia ASC LIMIT 400'
    );
    $porDia->execute($params);

    $stmt = $db->prepare(
        'SELECT ' . COMPROMISO_COLUMNAS_SQL . ', ' . COMPROMISO_SITUACION_SQL . ' AS situacion, '
        . COMPROMISO_ATRASO_SQL . ' AS dias_atraso,
                k.nombre AS contacto_nombre, k.celular AS contacto_celular, k.celular_norm AS contacto_celular_norm, k.sas_cliente_id AS contacto_sas_cliente_id,
                k.estado AS contacto_estado, k.cobrador_nombre' . $from . '
         ORDER BY ' . $ordenSql . '
         LIMIT :limite OFFSET :offset'
    );
    foreach ($params as $clave => $valor) {
        $stmt->bindValue(':' . $clave, $valor);
    }
    $stmt->bindValue(':limite', COMPROMISOS_POR_PAGINA, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    json_response(['ok' => true, 'data' => [
        'items' => $items,
        'pagina' => $pagina,
        'total' => $total,
        'hay_mas' => $offset + count($items) < $total,
        'totales' => ['cantidad' => $total, 'monto' => $totales['monto'], 'pagado' => $totales['pagado']],
        'por_dia' => $porDia->fetchAll(),
        // Global: sin filtros. Filtrado: respeta rango de fechas y quién lo cargó.
        'resumen' => compromisos_resumen($db, [], []),
        'resumen_filtrado' => compromisos_resumen($db, $whereBase, $paramsBase),
        'filtros_activos' => $whereBase !== [],
        'por_usuario' => compromisos_por_usuario($db),
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
    if (api_string($_GET, 'lista', 20) === 'conceptos') {
        compromisos_conceptos($db);
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
    if ($accion === 'reprogramar') {
        compromiso_reprogramar($db, $id, $actual, $body);
    }
    if ($accion === 'aviso') {
        compromiso_aviso($db, $id, $actual);
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
