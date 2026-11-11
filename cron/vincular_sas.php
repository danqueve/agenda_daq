<?php

declare(strict_types=1);

/*
 * Vincula contactos de la Agenda con clientes de SAS Imperio.
 *
 * Para los contactos que todavía no tienen sas_cliente_id busca una coincidencia exacta por DNI
 * y, si no hay, por celular (últimos 10 dígitos, el mismo criterio que el buscador de Nueva consulta).
 * Solo vincula si hay UNA coincidencia. Si hay varias, o el cliente de SAS ya está vinculado a
 * otro contacto, no hace nada y lo deja en el reporte para revisión manual.
 *
 * Por defecto SIMULA: muestra qué haría y no escribe. Con --aplicar guarda sas_cliente_id (y el DNI,
 * si el contacto no lo tenía) únicamente en la base de la Agenda. De SAS solo lee: nunca escribe.
 *
 * Usar el PHP 8.x explícito (el CLI por defecto del VPS es 5.6):
 *   /ruta/al/php8 cron/vincular_sas.php             # simula
 *   /ruta/al/php8 cron/vincular_sas.php --aplicar   # escribe
 *   /ruta/al/php8 cron/vincular_sas.php --solo=12,15 --aplicar   # solo esos contactos de la Agenda
 * El reporte también queda en storage/logs/vincular_sas-AAAA-MM-DD_HHMMSS.txt.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/sas.php';

$opciones = getopt('', ['aplicar', 'simular', 'solo:']);
if (isset($opciones['aplicar'], $opciones['simular'])) {
    fwrite(STDERR, "Elegí --simular o --aplicar, no los dos.\n");
    exit(1);
}
$aplicar = isset($opciones['aplicar']);
$solo = isset($opciones['solo'])
    ? array_values(array_filter(array_map('intval', explode(',', (string) $opciones['solo'])), static fn (int $id): bool => $id > 0))
    : [];

if (!SasDb::habilitado()) {
    fwrite(STDERR, "La integración con SAS Imperio está apagada (SAS_DB_NAME vacío en .env).\n");
    exit(1);
}
$sas = SasDb::get();
if ($sas === null) {
    fwrite(STDERR, "No se pudo conectar a SAS Imperio (el detalle quedó en el log de errores de PHP).\n");
    exit(1);
}

$db = Db::get();
$indice = sas_indice_clientes($sas);
$filtro = $solo === [] ? '' : ' AND id IN (' . implode(',', array_fill(0, count($solo), '?')) . ')';
$stmt = $db->prepare('SELECT id, nombre, dni, celular, celular_norm FROM contactos WHERE sas_cliente_id IS NULL' . $filtro . ' ORDER BY id');
$stmt->execute($solo);
$contactos = $stmt->fetchAll();
$yaVinculados = [];
foreach ($db->query('SELECT sas_cliente_id, nombre FROM contactos WHERE sas_cliente_id IS NOT NULL')->fetchAll() as $fila) {
    $yaVinculados[(int) $fila['sas_cliente_id']] = (string) $fila['nombre'];
}

$propuestas = [];   // contacto => [sas_id, método]
$ambiguos = [];
$sinCoincidencia = 0;
foreach ($contactos as $contacto) {
    [$metodo, $candidatos] = sas_coincidencias($indice, $contacto['dni'] ?? null, ($contacto['celular_norm'] ?: $contacto['celular']) ?? null);
    if ($candidatos === []) {
        $sinCoincidencia++;
    } elseif (count($candidatos) > 1) {
        $ambiguos[] = ['contacto' => $contacto, 'metodo' => $metodo, 'candidatos' => $candidatos];
    } else {
        $propuestas[(int) $contacto['id']] = ['contacto' => $contacto, 'sas_id' => $candidatos[0], 'metodo' => $metodo];
    }
}

// Un cliente de SAS ya vinculado, o propuesto para dos contactos a la vez, no se vincula solo.
$usos = array_count_values(array_map(static fn (array $p): int => $p['sas_id'], $propuestas));
$conflictos = [];
$vinculables = [];
foreach ($propuestas as $contactoId => $propuesta) {
    if (isset($yaVinculados[$propuesta['sas_id']])) {
        $conflictos[] = $propuesta + ['motivo' => 'ya está vinculado al contacto «' . $yaVinculados[$propuesta['sas_id']] . '»'];
    } elseif ($usos[$propuesta['sas_id']] > 1) {
        $conflictos[] = $propuesta + ['motivo' => 'lo proponen varios contactos de la Agenda a la vez'];
    } else {
        $vinculables[$contactoId] = $propuesta;
    }
}

$nombreSas = static fn (int $id): string => ($indice['datos'][$id]['nombre'] ?? '?') . (($indice['datos'][$id]['localidad'] ?? '') !== '' ? ' (' . $indice['datos'][$id]['localidad'] . ')' : '');
$etiqueta = static fn (?string $metodo): string => $metodo === 'dni' ? 'DNI' : 'celular';

$lineas = [];
$lineas[] = ($aplicar ? 'APLICAR' : 'SIMULACIÓN (no se escribe nada; usar --aplicar para vincular)') . ' · ' . date('Y-m-d H:i:s');
$lineas[] = sprintf(
    'Contactos sin vincular: %d · se vincularían: %d · para revisar a mano: %d · sin coincidencia: %d',
    count($contactos), count($vinculables), count($ambiguos) + count($conflictos), $sinCoincidencia
);
$lineas[] = '';
if ($vinculables !== []) {
    $lineas[] = '== Se vinculan (una sola coincidencia) ==';
    foreach ($vinculables as $id => $v) {
        $lineas[] = sprintf('  contacto #%d %s → SAS #%d %s  [por %s]', $id, $v['contacto']['nombre'], $v['sas_id'], $nombreSas($v['sas_id']), $etiqueta($v['metodo']));
    }
    $lineas[] = '';
}
if ($ambiguos !== []) {
    $lineas[] = '== Revisión manual: hay varias coincidencias ==';
    foreach ($ambiguos as $a) {
        $lineas[] = sprintf('  contacto #%d %s  [por %s]', $a['contacto']['id'], $a['contacto']['nombre'], $etiqueta($a['metodo']));
        foreach ($a['candidatos'] as $candidato) {
            $lineas[] = sprintf('      · SAS #%d %s', $candidato, $nombreSas($candidato));
        }
    }
    $lineas[] = '';
}
if ($conflictos !== []) {
    $lineas[] = '== Revisión manual: el cliente de SAS ya tiene otro vínculo ==';
    foreach ($conflictos as $c) {
        $lineas[] = sprintf('  contacto #%d %s → SAS #%d %s: %s', $c['contacto']['id'], $c['contacto']['nombre'], $c['sas_id'], $nombreSas($c['sas_id']), $c['motivo']);
    }
    $lineas[] = '';
}

if ($aplicar) {
    $vincular = $db->prepare(
        "UPDATE contactos SET sas_cliente_id = :sas, dni = COALESCE(NULLIF(dni, ''), NULLIF(:dni, ''))
         WHERE id = :id AND sas_cliente_id IS NULL"
    );
    $hechos = 0;
    $db->beginTransaction();
    try {
        foreach ($vinculables as $id => $v) {
            $vincular->execute(['sas' => $v['sas_id'], 'dni' => substr($indice['datos'][$v['sas_id']]['dni'] ?? '', 0, 15), 'id' => $id]);
            $hechos += $vincular->rowCount();
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        error_log('vincular_sas: ' . $error->getMessage());
        fwrite(STDERR, "No se pudo guardar; no se vinculó nada (detalle en el log de errores de PHP).\n");
        exit(1);
    }
    $lineas[] = "Contactos vinculados: {$hechos}";
}

$reporte = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $reporte;
$carpeta = __DIR__ . '/../storage/logs';
if (!is_dir($carpeta)) {
    mkdir($carpeta, 0750, true);
}
file_put_contents($carpeta . '/vincular_sas-' . date('Y-m-d_His') . '.txt', $reporte);
