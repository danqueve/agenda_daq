<?php

declare(strict_types=1);

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Normaliza un celular argentino a formato 549XXXXXXXXXX (sin 0, sin 15).
 * El número nacional significativo (código de área + local) siempre tiene
 * 10 dígitos; a partir de eso se reconstruye el formato internacional con
 * el prefijo de celular "9" que exige WhatsApp.
 */
function normalizar_celular(string $raw): string
{
    $digitos = preg_replace('/\D+/', '', $raw) ?? '';

    if (str_starts_with($digitos, '00')) {
        $digitos = substr($digitos, 2);
    }

    if (str_starts_with($digitos, '54')) {
        $digitos = substr($digitos, 2);
    }

    if (str_starts_with($digitos, '9') && strlen($digitos) === 11) {
        $digitos = substr($digitos, 1);
    }

    if (str_starts_with($digitos, '0')) {
        $digitos = substr($digitos, 1);
    }

    // Discado local de celular: código de área (2 a 4 dígitos) + "15" + número
    // local. El "15" se descarta para quedar con el número nacional de 10 dígitos.
    if (strlen($digitos) === 12) {
        foreach ([2, 3, 4] as $largoArea) {
            if (substr($digitos, $largoArea, 2) === '15') {
                $digitos = substr($digitos, 0, $largoArea) . substr($digitos, $largoArea + 2);
                break;
            }
        }
    }

    return '549' . $digitos;
}
