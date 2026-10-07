<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se ejecuta por consola.');
}

require_once __DIR__ . '/../app/db.php';

fwrite(STDOUT, "Usuario: ");
$usuario = trim((string) fgets(STDIN));

fwrite(STDOUT, "Contraseña: ");
$password = trim((string) fgets(STDIN));

if ($usuario === '' || $password === '') {
    fwrite(STDERR, "Usuario y contraseña no pueden estar vacíos.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
    exit(1);
}

$stmt = Db::get()->prepare('SELECT id FROM usuarios WHERE usuario = :usuario');
$stmt->execute(['usuario' => $usuario]);

if ($stmt->fetch()) {
    fwrite(STDERR, "Ya existe un usuario con ese nombre.\n");
    exit(1);
}

$stmt = Db::get()->prepare(
    'INSERT INTO usuarios (usuario, password_hash) VALUES (:usuario, :password_hash)'
);
$stmt->execute([
    'usuario' => $usuario,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);

fwrite(STDOUT, "Usuario '{$usuario}' creado.\n");
