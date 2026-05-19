<?php

declare(strict_types=1);

use PortalNoticias\Shared\Support\JsonResponder;

$container = require __DIR__ . '/../bootstrap/app.php';
$config = $container->config();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    JsonResponder::send([
        'success' => false,
        'error' => 'Metodo no permitido. Usa POST.',
    ], 405);
    exit;
}

$configuredToken = trim($config->ingestToken());

if ($configuredToken === '') {
    JsonResponder::send([
        'success' => false,
        'error' => 'INGEST_TOKEN no esta configurado.',
    ], 503);
    exit;
}

$providedToken = requestToken();

if ($providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
    JsonResponder::send([
        'success' => false,
        'error' => 'No autorizado.',
    ], 401);
    exit;
}

try {
    $result = $container->updateNewsUseCase()->execute();

    JsonResponder::send([
        'success' => true,
        'data' => $result,
    ]);
} catch (Throwable $exception) {
    JsonResponder::send([
        'success' => false,
        'error' => $exception->getMessage(),
    ], 500);
}

function requestToken(): string
{
    $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));

    if (stripos($authorization, 'Bearer ') === 0) {
        return trim(substr($authorization, 7));
    }

    $headerToken = trim((string) ($_SERVER['HTTP_X_INGEST_TOKEN'] ?? ''));

    if ($headerToken !== '') {
        return $headerToken;
    }

    return '';
}
