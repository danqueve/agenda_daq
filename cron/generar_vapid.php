<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

require_once __DIR__ . '/../vendor/autoload.php';

// En WAMP el PHP CLI puede no heredar OPENSSL_CONF aunque Apache sí lo haga.
// OpenSSL lee esa variable al iniciar el proceso, por eso se relanza una vez.
if (PHP_OS_FAMILY === 'Windows' && getenv('OPENSSL_CONF') === false && getenv('AGENDA_VAPID_REEXEC') !== '1') {
    $opensslConf = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
    if (is_file($opensslConf)) {
        putenv('OPENSSL_CONF=' . $opensslConf);
        putenv('AGENDA_VAPID_REEXEC=1');
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__), $exitCode);
        exit($exitCode);
    }
}

$keys = Minishlink\WebPush\VAPID::createVapidKeys();
echo 'VAPID_PUBLIC=' . $keys['publicKey'] . PHP_EOL;
echo 'VAPID_PRIVATE=' . $keys['privateKey'] . PHP_EOL;
