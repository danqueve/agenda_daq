<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';

// Cualquier excepción no capturada en un endpoint responde JSON (el frontend
// espera {ok, error}) y deja el detalle en el log de errores de PHP.
set_exception_handler(static function (Throwable $error): void {
    error_log(sprintf('Agenda API %s: %s en %s:%d', $_SERVER['SCRIPT_NAME'] ?? '', $error->getMessage(), $error->getFile(), $error->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => 'Error del servidor. Probá de nuevo; si sigue, revisá el log de PHP.'], JSON_UNESCAPED_UNICODE);
});

iniciar_sesion();
requireLogin();

/** @return array<string, mixed> */
function api_body(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) {
            json_response(['ok' => false, 'error' => 'El contenido enviado no es válido.'], 400);
        }

        return $body;
    }

    return $_POST;
}

function api_require_write(): void
{
    csrf_require();
}

function api_require_admin(): void
{
    if (!es_admin()) {
        json_response(['ok' => false, 'error' => 'No tenés permisos de administrador.'], 403);
    }
}

function api_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function api_string(array $data, string $key, int $max = 0): string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($max > 0) {
        $value = mb_substr($value, 0, $max);
    }

    return $value;
}

function api_nullable_string(array $data, string $key, int $max = 0): ?string
{
    $value = api_string($data, $key, $max);
    return $value === '' ? null : $value;
}

function api_integer(mixed $value): int
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
}

function api_date(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', trim($value))
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($value));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
        json_response(['ok' => false, 'error' => 'La fecha indicada no es válida.'], 422);
    }

    return $date->format('Y-m-d H:i:s');
}

/**
 * Normaliza un monto positivo a DECIMAL(12,2), sin pasar por floats.
 * Acepta punto o coma decimal y redondea los excedentes a dos decimales.
 */
function api_monto(mixed $value): ?string
{
    $texto = trim((string) $value);
    if ($texto === '' || !preg_match('/^\d+(?:[.,]\d+)?$/', $texto)) {
        return null;
    }

    [$enteros, $decimales] = array_pad(preg_split('/[.,]/', $texto), 2, '');
    $enteros = ltrim($enteros, '0') ?: '0';
    $decimales = str_pad($decimales, 3, '0');
    $centavos = (int) substr($decimales, 0, 2);
    if ((int) $decimales[2] >= 5) {
        $centavos++;
    }
    if ($centavos === 100) {
        $enteros = (string) ((int) $enteros + 1);
        $centavos = 0;
    }

    if (strlen($enteros) > 10 || ($enteros === '0' && $centavos === 0)) {
        return null;
    }

    return $enteros . '.' . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT);
}

/**
 * Convierte un monto normalizado a centavos enteros para comparaciones exactas.
 */
function api_centavos(mixed $value): int
{
    $monto = api_monto($value);
    if ($monto === null) {
        return 0;
    }

    [$enteros, $decimales] = explode('.', $monto);
    return ((int) $enteros * 100) + (int) $decimales;
}

/** Centavos enteros a "1500.50", el formato que entiende DECIMAL(12,2). */
function centavos_a_decimal(int $centavos): string
{
    return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
}

/** @return list<int> */
function api_ids(mixed $ids): array
{
    if (!is_array($ids)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('api_integer', $ids))));
}
