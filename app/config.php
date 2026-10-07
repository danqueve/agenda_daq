<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'APP_URL', 'APP_TZ']);

define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

define('APP_URL', rtrim($_ENV['APP_URL'], '/'));
define('APP_TZ', $_ENV['APP_TZ']);

define('VAPID_PUBLIC', $_ENV['VAPID_PUBLIC'] ?? '');
define('VAPID_PRIVATE', $_ENV['VAPID_PRIVATE'] ?? '');
define('VAPID_SUBJECT', $_ENV['VAPID_SUBJECT'] ?? '');

date_default_timezone_set(APP_TZ);
