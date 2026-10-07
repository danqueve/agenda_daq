<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

iniciar_sesion();
logout();

header('Location: ' . APP_URL . '/login.php');
exit;
