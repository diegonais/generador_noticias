<?php

declare(strict_types=1);

$container = require __DIR__ . '/../bootstrap/app.php';

$source = trim((string) ($_GET['url'] ?? ''));

if ($source === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Parametro url requerido.';
    exit;
}

$decodedSource = rawurldecode($source);
$url = normalizeImageUrl($decodedSource);

if ($url === null || !isAllowedImageUrl($url)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'URL de imagen no permitida.';
    exit;
}

$cachePath = buildImageCachePath($container->config()->cachePath(), $url);
$cacheMetaPath = $cachePath . '.meta';
$freshCachedImage = readCachedImage($cachePath, $cacheMetaPath, 604800);

if ($freshCachedImage !== null) {
    serveImageBytes($freshCachedImage['body'], $freshCachedImage['mime'], 'HIT', 86400);
    exit;
}

$response = $container->httpClient()->request(
    'GET',
    $url,
    [
        'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        'User-Agent: Mozilla/5.0 (compatible; NoticiasBoliviaBot/1.0)',
        'Referer: https://abi.bo/',
    ],
    null,
    true,
    8,
    20,
);

if (!$response->isSuccessful() || $response->body() === null || $response->body() === '') {
    $staleCachedImage = readCachedImage($cachePath, $cacheMetaPath, null);

    if ($staleCachedImage !== null) {
        serveImageBytes($staleCachedImage['body'], $staleCachedImage['mime'], 'STALE', 3600);
        exit;
    }

    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No se pudo recuperar la imagen.';
    exit;
}

$body = (string) $response->body();
$mime = detectImageMime($body, $url);

if ($mime === null) {
    http_response_code(415);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Tipo de archivo no soportado.';
    exit;
}

writeCachedImage($cachePath, $cacheMetaPath, $body, $mime);
serveImageBytes($body, $mime, 'MISS', 86400);

function normalizeImageUrl(string $value): ?string
{
    $candidate = trim($value);

    if ($candidate === '') {
        return null;
    }

    if (str_starts_with($candidate, '//')) {
        $candidate = 'https:' . $candidate;
    }

    if (str_starts_with($candidate, '/')) {
        $candidate = 'https://abi.bo' . $candidate;
    }

    if (!preg_match('#^https?://#i', $candidate)) {
        return null;
    }

    return $candidate;
}

function isAllowedImageUrl(string $url): bool
{
    $parts = parse_url($url);

    if (!is_array($parts)) {
        return false;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $path = (string) ($parts['path'] ?? '');

    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    if (!in_array($host, ['abi.bo', 'www.abi.bo'], true)) {
        return false;
    }

    if ($path === '' || !str_starts_with($path, '/wp-content/uploads/')) {
        return false;
    }

    return true;
}

function detectImageMime(string $body, string $url): ?string
{
    if (function_exists('getimagesizefromstring')) {
        $imageInfo = @getimagesizefromstring($body);

        if (is_array($imageInfo) && isset($imageInfo['mime']) && str_starts_with((string) $imageInfo['mime'], 'image/')) {
            return (string) $imageInfo['mime'];
        }
    }

    $path = strtolower((string) parse_url($url, PHP_URL_PATH));

    foreach ([
        '.jpg' => 'image/jpeg',
        '.jpeg' => 'image/jpeg',
        '.png' => 'image/png',
        '.gif' => 'image/gif',
        '.webp' => 'image/webp',
        '.avif' => 'image/avif',
    ] as $extension => $mime) {
        if (str_ends_with($path, $extension)) {
            return $mime;
        }
    }

    return null;
}

function buildImageCachePath(string $cacheDirectory, string $url): string
{
    return rtrim($cacheDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'image_proxy_' . sha1($url);
}

/**
 * @return array{body: string, mime: string}|null
 */
function readCachedImage(string $cachePath, string $cacheMetaPath, ?int $maxAgeSeconds): ?array
{
    if (!is_file($cachePath) || !is_readable($cachePath)) {
        return null;
    }

    if ($maxAgeSeconds !== null) {
        $modifiedAt = filemtime($cachePath);

        if ($modifiedAt === false || time() - $modifiedAt > $maxAgeSeconds) {
            return null;
        }
    }

    $body = file_get_contents($cachePath);

    if (!is_string($body) || $body === '') {
        return null;
    }

    $storedMime = is_file($cacheMetaPath) ? trim((string) file_get_contents($cacheMetaPath)) : '';
    $mime = normalizeImageMime($storedMime) ?? detectImageMime($body, $cachePath);

    if ($mime === null) {
        return null;
    }

    return [
        'body' => $body,
        'mime' => $mime,
    ];
}

function writeCachedImage(string $cachePath, string $cacheMetaPath, string $body, string $mime): void
{
    $directory = dirname($cachePath);

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    @file_put_contents($cachePath, $body, LOCK_EX);
    @file_put_contents($cacheMetaPath, $mime, LOCK_EX);
}

function serveImageBytes(string $body, string $mime, string $cacheStatus, int $maxAge): void
{
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=' . $maxAge . ', s-maxage=' . $maxAge);
    header('X-Content-Type-Options: nosniff');
    header('X-Image-Proxy-Cache: ' . $cacheStatus);
    echo $body;
}

function normalizeImageMime(string $mime): ?string
{
    $clean = strtolower(trim(explode(';', $mime)[0] ?? ''));

    if ($clean === 'image/jpg') {
        return 'image/jpeg';
    }

    if (in_array($clean, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], true)) {
        return $clean;
    }

    return null;
}
