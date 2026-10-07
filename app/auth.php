<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

const SESION_NOMBRE = 'agenda_daq_sesion';
const RECORDAR_COOKIE = 'agenda_daq_recordar';
const RECORDAR_DIAS = 90;
const INTENTOS_MAXIMOS = 5;
const BLOQUEO_MINUTOS = 15;

function cliente_ip(): string
{
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
    if ($xff) {
        return trim(explode(',', $xff)[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_name(SESION_NOMBRE);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (usuario_actual_id() === null) {
        intentar_recordar_sesion();
    }
}

function usuario_actual_id(): ?int
{
    return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
}

function requireLogin(): void
{
    if (usuario_actual_id() !== null) {
        return;
    }

    if (preg_match('#(?:^|/)api/#', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '')) {
        json_response(['ok' => false, 'error' => 'No autenticado'], 401);
    }

    header('Location: ' . APP_URL . '/login.php');
    exit;
}

function intento_login_bloqueado(string $ip): bool
{
    $stmt = Db::get()->prepare(
        'SELECT COUNT(*) FROM intentos_login WHERE ip = :ip AND fecha > :desde'
    );
    $stmt->execute([
        'ip' => $ip,
        'desde' => date('Y-m-d H:i:s', time() - BLOQUEO_MINUTOS * 60),
    ]);

    return (int) $stmt->fetchColumn() >= INTENTOS_MAXIMOS;
}

function registrar_intento_login(string $ip, string $usuario): void
{
    $stmt = Db::get()->prepare('INSERT INTO intentos_login (ip, usuario) VALUES (:ip, :usuario)');
    $stmt->execute(['ip' => $ip, 'usuario' => $usuario]);
}

function limpiar_intentos_login(string $ip): void
{
    $stmt = Db::get()->prepare('DELETE FROM intentos_login WHERE ip = :ip');
    $stmt->execute(['ip' => $ip]);
}

/**
 * @return string 'ok' | 'bloqueado' | 'invalido'
 */
function login(string $usuario, string $password, bool $recordar, string $ip): string
{
    if (intento_login_bloqueado($ip)) {
        return 'bloqueado';
    }

    $stmt = Db::get()->prepare('SELECT id, password_hash FROM usuarios WHERE usuario = :usuario');
    $stmt->execute(['usuario' => $usuario]);
    $fila = $stmt->fetch();

    if (!$fila || !password_verify($password, $fila['password_hash'])) {
        registrar_intento_login($ip, $usuario);
        return 'invalido';
    }

    limpiar_intentos_login($ip);

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $fila['id'];

    if ($recordar) {
        crear_token_recordar((int) $fila['id']);
    }

    return 'ok';
}

function crear_token_recordar(int $usuarioId): void
{
    $selector = bin2hex(random_bytes(12));
    $validador = bin2hex(random_bytes(32));
    $expiraEn = date('Y-m-d H:i:s', time() + RECORDAR_DIAS * 86400);

    $stmt = Db::get()->prepare(
        'INSERT INTO tokens_recordar (usuario_id, selector, validador_hash, expira_en)
         VALUES (:usuario_id, :selector, :validador_hash, :expira_en)'
    );
    $stmt->execute([
        'usuario_id' => $usuarioId,
        'selector' => $selector,
        'validador_hash' => hash('sha256', $validador),
        'expira_en' => $expiraEn,
    ]);

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(
        RECORDAR_COOKIE,
        $selector . ':' . $validador,
        [
            'expires' => time() + RECORDAR_DIAS * 86400,
            'path' => '/',
            'httponly' => true,
            'secure' => $https,
            'samesite' => 'Lax',
        ]
    );
}

function intentar_recordar_sesion(): void
{
    $cookie = $_COOKIE[RECORDAR_COOKIE] ?? null;
    if (!$cookie || !str_contains($cookie, ':')) {
        return;
    }

    [$selector, $validador] = explode(':', $cookie, 2);

    $stmt = Db::get()->prepare(
        'SELECT id, usuario_id, validador_hash FROM tokens_recordar
         WHERE selector = :selector AND expira_en > NOW()'
    );
    $stmt->execute(['selector' => $selector]);
    $fila = $stmt->fetch();

    if (!$fila || !hash_equals($fila['validador_hash'], hash('sha256', $validador))) {
        borrar_cookie_recordar();
        return;
    }

    // Rotar el token en cada uso: si una cookie robada se usa primero, la
    // próxima verificación del dueño real ya no encontrará el selector.
    $stmt = Db::get()->prepare('DELETE FROM tokens_recordar WHERE id = :id');
    $stmt->execute(['id' => $fila['id']]);

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $fila['usuario_id'];
    crear_token_recordar((int) $fila['usuario_id']);
}

function borrar_cookie_recordar(): void
{
    setcookie(RECORDAR_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
    unset($_COOKIE[RECORDAR_COOKIE]);
}

function revocar_tokens_recordar(int $usuarioId): void
{
    $stmt = Db::get()->prepare('DELETE FROM tokens_recordar WHERE usuario_id = :usuario_id');
    $stmt->execute(['usuario_id' => $usuarioId]);
}

function logout(): void
{
    $cookie = $_COOKIE[RECORDAR_COOKIE] ?? null;
    if ($cookie && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        $stmt = Db::get()->prepare('DELETE FROM tokens_recordar WHERE selector = :selector');
        $stmt->execute(['selector' => $selector]);
    }

    borrar_cookie_recordar();
    $_SESSION = [];
    session_destroy();
}
