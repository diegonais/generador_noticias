<?php

declare(strict_types=1);

use PortalNoticias\News\Domain\NewsItem;
use PortalNoticias\Shared\Support\TextHelper;

$container = require __DIR__ . '/../bootstrap/app.php';
$config = $container->config();
$apiEndpoint = '/api/news.php';
$newsId = isset($_GET['id']) ? (string) $_GET['id'] : '';
$publicBaseUrl = trim($config->publicBaseUrl());

$currentUrl = buildCurrentUrl($publicBaseUrl);
$canonicalId = $newsId;
$newsItem = null;

function assetUrl(string $path): string
{
    $normalizedPath = ltrim($path, '/');
    $fullPath = __DIR__ . '/' . $normalizedPath;
    $version = is_file($fullPath) ? (string) filemtime($fullPath) : '1';

    return './' . $normalizedPath . '?v=' . rawurlencode($version);
}

if ($newsId !== '') {
    try {
        $newsItem = $container->jsonNewsRepository()->findByIdentifier($newsId);
    } catch (Throwable $error) {
        $newsItem = null;
    }
}

if ($newsItem instanceof NewsItem) {
    $canonicalId = resolveCanonicalId($newsItem, $newsId);
}

$canonicalUrl = buildCanonicalUrl($currentUrl, $canonicalId);

$metaTitle = $newsItem instanceof NewsItem
    ? $newsItem->title() . ' | ' . $config->appName()
    : 'Detalle de noticia | ' . $config->appName();
$metaDescription = buildMetaDescription($newsItem);
$metaImage = $newsItem instanceof NewsItem ? buildShareImageUrl($currentUrl, $canonicalId, $newsItem) : '';
$metaType = $newsItem instanceof NewsItem ? 'article' : 'website';
$metaTwitterCard = $metaImage !== '' ? 'summary_large_image' : 'summary';
$metaPublishedAt = $newsItem instanceof NewsItem ? trim($newsItem->publishedAt()) : '';

function resolveNewsItem(array $items, string $newsId): ?NewsItem
{
    foreach ($items as $item) {
        if (!$item instanceof NewsItem) {
            continue;
        }

        if (identifiersMatch((string) $item->guid(), $newsId) || identifiersMatch((string) $item->link(), $newsId)) {
            return $item;
        }
    }

    return null;
}

function resolveCanonicalId(NewsItem $item, string $requestedId): string
{
    $guid = trim((string) $item->guid());

    if ($guid !== '') {
        return $guid;
    }

    $link = trim((string) $item->link());

    if ($link !== '') {
        return $link;
    }

    return $requestedId;
}

function resolveNewsItemFromPayloadData(array $records, string $newsId, string $timezone): ?NewsItem
{
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        $candidateGuid = isset($record['guid']) ? (string) $record['guid'] : '';
        $candidateLink = isset($record['link']) ? (string) $record['link'] : '';

        if (!identifiersMatch($candidateGuid, $newsId) && !identifiersMatch($candidateLink, $newsId)) {
            continue;
        }

        $item = NewsItem::fromArray($record, $timezone);

        if ($item instanceof NewsItem) {
            return $item;
        }
    }

    return null;
}

function identifiersMatch(string $left, string $right): bool
{
    if (trim($left) === '' || trim($right) === '') {
        return false;
    }

    $leftCandidates = expandIdentifierCandidates($left);
    $rightCandidates = expandIdentifierCandidates($right);

    foreach ($leftCandidates as $candidate) {
        if (in_array($candidate, $rightCandidates, true)) {
            return true;
        }
    }

    return false;
}

function expandIdentifierCandidates(string $value): array
{
    $raw = trim($value);
    $decoded = rawurldecode($raw);
    $doubleDecoded = rawurldecode($decoded);

    $candidates = array_values(array_filter([
        normalizeIdentifier($raw),
        normalizeIdentifier($decoded),
        normalizeIdentifier($doubleDecoded),
    ]));

    return array_values(array_unique($candidates));
}

function normalizeIdentifier(string $value): string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return '';
    }

    $parsed = parse_url($trimmed);

    if (!is_array($parsed) || !isset($parsed['host'])) {
        return $trimmed;
    }

    $scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : 'https';
    $host = strtolower((string) $parsed['host']);
    $path = isset($parsed['path']) ? (string) $parsed['path'] : '';
    $path = $path !== '/' ? rtrim($path, '/') : '/';
    $query = isset($parsed['query']) ? (string) $parsed['query'] : '';

    return $scheme . '://' . $host . $path . ($query !== '' ? '?' . $query : '');
}

function buildMetaDescription(?NewsItem $item): string
{
    if (!$item instanceof NewsItem) {
        return 'Lectura completa de una noticia ABI dentro del portal informativo.';
    }

    $summary = TextHelper::removeFeedFooter(TextHelper::cleanHtml($item->summary()));
    $summary = TextHelper::fallback($summary, 'Lectura completa de una noticia ABI dentro del portal informativo.');

    return truncateText($summary, 220);
}

function truncateText(string $text, int $maxLength): string
{
    $clean = trim($text);

    if ($clean === '' || $maxLength <= 0) {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr') && function_exists('mb_strrpos')) {
        if (mb_strlen($clean, 'UTF-8') <= $maxLength) {
            return $clean;
        }

        $slice = mb_substr($clean, 0, $maxLength, 'UTF-8');
        $lastSpace = mb_strrpos($slice, ' ', 0, 'UTF-8');

        if ($lastSpace !== false) {
            $slice = mb_substr($slice, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($slice) . '...';
    }

    if (strlen($clean) <= $maxLength) {
        return $clean;
    }

    $slice = substr($clean, 0, $maxLength);
    $lastSpace = strrpos($slice, ' ');

    if ($lastSpace !== false) {
        $slice = substr($slice, 0, $lastSpace);
    }

    return rtrim($slice) . '...';
}

function buildCurrentUrl(string $publicBaseUrl = ''): string
{
    $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/news.php';

    if ($publicBaseUrl !== '') {
        return mergePublicBaseWithRequestUri($publicBaseUrl, $requestUri);
    }

    $forwardedProto = firstForwardedValue($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null);
    $isHttps = ($forwardedProto === 'https')
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    $scheme = $isHttps ? 'https' : 'http';

    $host = firstForwardedValue($_SERVER['HTTP_X_FORWARDED_HOST'] ?? null);

    if ($host === '') {
        $host = isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : '';
    }

    if ($host === '') {
        $host = isset($_SERVER['SERVER_NAME']) ? trim((string) $_SERVER['SERVER_NAME']) : '';
    }

    if ($host === '') {
        return $requestUri;
    }

    return $scheme . '://' . $host . $requestUri;
}

function mergePublicBaseWithRequestUri(string $publicBaseUrl, string $requestUri): string
{
    $parsedBase = parse_url($publicBaseUrl);

    if (!is_array($parsedBase)) {
        return $requestUri;
    }

    $origin = buildOriginFromParsedUrl($parsedBase);

    if ($origin === '') {
        return $requestUri;
    }

    $basePath = isset($parsedBase['path']) ? trim((string) $parsedBase['path']) : '';
    $requestPath = parse_url($requestUri, PHP_URL_PATH);
    $requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/news.php';
    $query = parse_url($requestUri, PHP_URL_QUERY);

    $normalizedBasePath = '/' . trim($basePath, '/');
    $normalizedRequestPath = '/' . ltrim($requestPath, '/');
    $path = ($normalizedBasePath === '/')
        ? $normalizedRequestPath
        : $normalizedBasePath . $normalizedRequestPath;
    $path = preg_replace('#/{2,}#', '/', $path) ?: '/news.php';

    return $origin . $path . (is_string($query) && $query !== '' ? '?' . $query : '');
}

function buildCanonicalUrl(string $currentUrl, string $newsId): string
{
    $parsed = parse_url($currentUrl);

    if (!is_array($parsed)) {
        return $currentUrl;
    }

    $path = isset($parsed['path']) && is_string($parsed['path']) && $parsed['path'] !== ''
        ? $parsed['path']
        : '/news.php';

    $origin = buildOriginFromParsedUrl($parsed);

    if ($origin === '') {
        return $path . ($newsId !== '' ? '?id=' . rawurlencode($newsId) : '');
    }

    return $origin . $path . ($newsId !== '' ? '?id=' . rawurlencode($newsId) : '');
}

function toAbsoluteUrl(string $value, string $currentUrl): string
{
    $url = trim($value);

    if ($url === '') {
        return '';
    }

    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }

    $parsedCurrent = parse_url($currentUrl);

    if (!is_array($parsedCurrent)) {
        return '';
    }

    $origin = buildOriginFromParsedUrl($parsedCurrent);

    if ($origin === '') {
        return '';
    }

    if (strpos($url, '//') === 0) {
        $scheme = isset($parsedCurrent['scheme']) ? (string) $parsedCurrent['scheme'] : 'https';
        return $scheme . ':' . $url;
    }

    if (strpos($url, '/') === 0) {
        return $origin . $url;
    }

    return $origin . '/' . ltrim($url, '/');
}

function buildShareImageUrl(string $currentUrl, string $newsId, ?NewsItem $item): string
{
    if (!$item instanceof NewsItem || trim($item->image()) === '') {
        return '';
    }

    $parsed = parse_url($currentUrl);

    if (!is_array($parsed)) {
        return '';
    }

    $origin = buildOriginFromParsedUrl($parsed);

    if ($origin === '') {
        return '';
    }

    $path = isset($parsed['path']) && is_string($parsed['path']) && $parsed['path'] !== ''
        ? $parsed['path']
        : '/news.php';
    $directory = str_replace('\\', '/', dirname($path));
    $directory = $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');

    return $origin . $directory . '/share-image.php?id=' . rawurlencode($newsId);
}

function buildOriginFromParsedUrl(array $parsed): string
{
    $scheme = isset($parsed['scheme']) ? (string) $parsed['scheme'] : '';
    $host = isset($parsed['host']) ? (string) $parsed['host'] : '';

    if ($scheme === '' || $host === '') {
        return '';
    }

    $port = isset($parsed['port']) ? ':' . (string) $parsed['port'] : '';

    return $scheme . '://' . $host . $port;
}

function firstForwardedValue(?string $value): string
{
    if (!is_string($value)) {
        return '';
    }

    $parts = explode(',', $value);

    if (count($parts) === 0) {
        return '';
    }

    return strtolower(trim((string) $parts[0]));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <script>
        (function () {
            var key = 'portal_theme';
            var storedTheme = null;

            try {
                storedTheme = localStorage.getItem(key);
            } catch (error) {
                storedTheme = null;
            }

            var theme = storedTheme;

            if (theme !== 'dark' && theme !== 'light') {
                var prefersDark = false;

                try {
                    prefersDark = Boolean(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                } catch (error) {
                    prefersDark = false;
                }

                theme = prefersDark ? 'dark' : 'light';
            }

            if (theme === 'dark') {
                document.documentElement.classList.add('theme-dark');
            } else {
                document.documentElement.classList.remove('theme-dark');
            }
        })();
    </script>

    <meta property="og:site_name" content="<?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:locale" content="es_BO">
    <meta property="og:type" content="<?php echo htmlspecialchars($metaType, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
<?php if ($metaImage !== ''): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($metaImage, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($metaImage, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
<?php endif; ?>
<?php if ($metaPublishedAt !== ''): ?>
    <meta property="article:published_time" content="<?php echo htmlspecialchars($metaPublishedAt, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>

    <meta name="twitter:card" content="<?php echo htmlspecialchars($metaTwitterCard, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
<?php if ($metaImage !== ''): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($metaImage, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image:alt" content="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>

    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <header class="site-header detail-header">
        <div class="site-header__main">
            <div class="site-header__main-inner">
                <div class="brand">
                    <div class="brand-mark" aria-hidden="true">
                        <img class="brand-mark__image" src="<?php echo htmlspecialchars(assetUrl('assets/img/df_noticias_logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="">
                    </div>
                    <div class="brand__text">
                        <h1>Lectura completa</h1>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="site-shell detail-shell" data-api-endpoint="<?php echo htmlspecialchars($apiEndpoint, ENT_QUOTES, 'UTF-8'); ?>" data-news-id="<?php echo htmlspecialchars($newsId, ENT_QUOTES, 'UTF-8'); ?>" data-share-url="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">

        <main class="content">
            <!-- <nav class="detail-nav">
                <a class="detail-back" href="/">Volver al portal</a>
            </nav> -->

            <div id="detail-loading" class="status-card">
                <strong>Preparando lectura</strong>
                <p>Buscando la publicaci&oacute;n seleccionada.</p>
            </div>

            <div id="detail-error" class="status-card is-hidden is-error" role="alert">
                <strong>No se pudo abrir esta publicaci&oacute;n.</strong>
                <p>El enlace puede haber cambiado o la noticia a&uacute;n no fue sincronizada.</p>
            </div>

            <article id="news-detail" class="detail-article is-hidden"></article>
        </main>

    </div>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__info">
                <p class="site-footer__label">Centro informativo</p>
                <p class="site-footer__copy">Seguimiento organizado de publicaciones ABI para lectura y consulta diaria.</p>
            </div>
            <p class="site-footer__credit">
                Dise&ntilde;ado y desarrollado por
                <a href="https://diegonais.vercel.app" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($config->footerAuthor(), ENT_QUOTES, 'UTF-8'); ?></a>
                - <?php echo date('Y'); ?>
            </p>
        </div>
    </footer>

    <button id="back-to-top" class="back-to-top" type="button" aria-label="Volver arriba">
        <svg class="back-to-top__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M12 19V5"></path>
            <path d="M5 12l7-7l7 7"></path>
        </svg>
    </button>

    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/news-shared.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/news-detail.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
