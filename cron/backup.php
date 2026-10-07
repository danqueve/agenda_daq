<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se ejecuta por consola.');
}

require_once __DIR__ . '/../app/config.php';

const DIAS_A_CONSERVAR = 14;

function backup_log(string $mensaje): void
{
    $directorio = __DIR__ . '/../storage/logs';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0750, true);
    }
    file_put_contents(
        $directorio . '/backup.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

$directorioBackups = __DIR__ . '/../storage/backups';
if (!is_dir($directorioBackups)) {
    mkdir($directorioBackups, 0750, true);
}

$mysqldump = getenv('MYSQLDUMP_BIN') ?: 'mysqldump';
$archivoSql = $directorioBackups . '/' . DB_NAME . '_' . date('Y-m-d_His') . '.sql';
$archivo = $archivoSql . '.gz';

// Se vuelca a un .sql plano y se comprime con zlib en PHP (en vez de un pipe
// "| gzip" de shell) para no depender de un binario externo de gzip y evitar
// los problemas de escaping de exec() con pipes en Windows.
$comando = sprintf(
    '%s --host=%s --user=%s %s %s > %s',
    escapeshellcmd($mysqldump),
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '',
    escapeshellarg(DB_NAME),
    escapeshellarg($archivoSql)
);

exec($comando . ' 2>&1', $salida, $codigo);

if ($codigo !== 0 || !is_file($archivoSql) || filesize($archivoSql) === 0) {
    backup_log('ERROR al generar el backup: ' . implode(' | ', $salida));
    if (is_file($archivoSql)) {
        unlink($archivoSql);
    }
    exit(1);
}

file_put_contents($archivo, gzencode(file_get_contents($archivoSql), 9));
unlink($archivoSql);

backup_log('Backup creado: ' . basename($archivo) . ' (' . filesize($archivo) . ' bytes)');

$limite = time() - DIAS_A_CONSERVAR * 86400;
$borrados = 0;

foreach (glob($directorioBackups . '/' . DB_NAME . '_*.sql.gz') as $viejo) {
    if (filemtime($viejo) < $limite) {
        unlink($viejo);
        $borrados++;
    }
}

if ($borrados > 0) {
    backup_log("Borrados {$borrados} backup(s) de más de " . DIAS_A_CONSERVAR . ' días.');
}
