<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class Db
{
    private static ?PDO $instancia = null;

    public static function get(): PDO
    {
        if (self::$instancia === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);

            self::$instancia = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            self::$instancia->exec("SET time_zone = '-03:00'");
        }

        return self::$instancia;
    }

    private function __construct()
    {
    }
}
