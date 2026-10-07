<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se ejecuta por consola.');
}

require_once __DIR__ . '/../app/helpers.php';

$casos = [
    '381 456-7890' => '5493814567890',
    '0381 15 4567890' => '5493814567890',
    '+54 9 381 456-7890' => '5493814567890',
    '03814567890' => '5493814567890',
    '5493814567890' => '5493814567890',
    '011 15-1234-5678' => '5491112345678',
    '0385 15 4123456' => '5493854123456',
];

$fallas = 0;

foreach ($casos as $entrada => $esperado) {
    $resultado = normalizar_celular((string) $entrada);
    $ok = $resultado === $esperado;
    if (!$ok) {
        $fallas++;
    }

    printf(
        "[%s] \"%s\" => \"%s\" (esperado \"%s\")\n",
        $ok ? 'PASS' : 'FAIL',
        $entrada,
        $resultado,
        $esperado
    );
}

if ($fallas > 0) {
    fwrite(STDERR, "\n{$fallas} caso(s) fallaron.\n");
    exit(1);
}

fwrite(STDOUT, "\nTodos los casos pasaron.\n");
