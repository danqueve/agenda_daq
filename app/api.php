<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';

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

/** @return list<int> */
function api_ids(mixed $ids): array
{
    if (!is_array($ids)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('api_integer', $ids))));
}
