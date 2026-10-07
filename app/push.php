<?php

declare(strict_types=1);

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

require_once __DIR__ . '/config.php';

function push_configurado(): bool
{
    return VAPID_PUBLIC !== '' && VAPID_PRIVATE !== '' && VAPID_SUBJECT !== '';
}

/** @return array{0: int, 1: list<string>} envíos correctos y endpoints expirados */
function push_enviar_a_dispositivos(array $dispositivos, array $payload): array
{
    if ($dispositivos === [] || !push_configurado()) {
        return [0, []];
    }
    $webPush = new WebPush(['VAPID' => [
        'subject' => VAPID_SUBJECT,
        'publicKey' => VAPID_PUBLIC,
        'privateKey' => VAPID_PRIVATE,
    ]]);
    $webPush->setReuseVAPIDHeaders(true);
    $byEndpoint = [];
    foreach ($dispositivos as $dispositivo) {
        $endpoint = (string) $dispositivo['endpoint'];
        $byEndpoint[$endpoint] = true;
        $webPush->queueNotification(Subscription::create([
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => (string) $dispositivo['p256dh'], 'auth' => (string) $dispositivo['auth']],
        ]), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ['TTL' => 3600]);
    }

    $correctos = 0;
    $expirados = [];
    foreach ($webPush->flush() as $report) {
        if ($report->isSuccess()) {
            $correctos++;
        } elseif ($report->isSubscriptionExpired()) {
            $expirados[] = $report->getEndpoint();
        }
    }
    return [$correctos, array_values(array_unique($expirados))];
}

function push_eliminar_endpoints(PDO $db, array $endpoints): void
{
    if ($endpoints === []) {
        return;
    }
    $placeholders = implode(', ', array_fill(0, count($endpoints), '?'));
    $stmt = $db->prepare("DELETE FROM push_suscripciones WHERE endpoint IN ({$placeholders})");
    $stmt->execute($endpoints);
}
