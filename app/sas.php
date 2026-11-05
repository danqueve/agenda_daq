<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/**
 * Conexión de SOLO LECTURA a la base de SAS Imperio. Se usa un usuario MySQL
 * con SELECT únicamente sobre ic_clientes: la Agenda nunca escribe en SAS.
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

        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', SAS_DB_HOST ?: 'localhost', SAS_DB_NAME);
            self::$instancia = new PDO($dsn, SAS_DB_USER, SAS_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 3,
            ]);
        } catch (Throwable $error) {
            error_log('SAS Imperio: no se pudo conectar: ' . $error->getMessage());
            self::$instancia = null;
        }

        return self::$instancia;
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
        $cobrador = trim(((string) ($fila['cobrador_nombre'] ?? '')) . ' ' . ((string) ($fila['cobrador_apellido'] ?? '')));
        $items[] = [
            'id' => (int) $fila['id'],
            'nombre' => trim(((string) $fila['nombres']) . ' ' . ((string) $fila['apellidos'])),
            'dni' => (string) ($fila['dni'] ?? ''),
            'telefono' => $telefono,
            'celular_norm' => preg_match('/^549\d{10}$/', $norm) ? $norm : '',
            'localidad' => (string) ($fila['localidad'] ?? ''),
            'estado' => (string) ($fila['estado'] ?? ''),
            'cobrador' => $cobrador !== '' ? $cobrador : null,
        ];
    }

    return $items;
}
