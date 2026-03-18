<?php

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/DateHelper.php';
require_once __DIR__ . '/../app/helpers/TextHelper.php';
require_once __DIR__ . '/../app/services/NewsService.php';
require_once __DIR__ . '/../app/services/SupabaseService.php';

if (!SUPABASE_ENABLED) {
    fwrite(STDERR, "[ERROR] SUPABASE_ENABLED no esta activo." . PHP_EOL);
    exit(1);
}

$supabaseService = new SupabaseService();

if (!$supabaseService->isConfigured()) {
    fwrite(STDERR, "[ERROR] Faltan variables de configuracion de Supabase." . PHP_EOL);
    exit(1);
}

if (!is_file(NEWS_JSON_PATH) || !is_readable(NEWS_JSON_PATH)) {
    fwrite(STDERR, "[ERROR] No se encontro un archivo local en storage/news.json para migrar." . PHP_EOL);
    exit(1);
}

$content = file_get_contents(NEWS_JSON_PATH);

if (!is_string($content) || trim($content) === '') {
    fwrite(STDERR, "[ERROR] El archivo local de noticias esta vacio." . PHP_EOL);
    exit(1);
}

$decoded = json_decode($content, true);

if (!is_array($decoded)) {
    fwrite(STDERR, "[ERROR] El archivo local de noticias no tiene JSON valido." . PHP_EOL);
    exit(1);
}

$newsService = new NewsService();
$normalized = $newsService->normalizeCollection($decoded);

if (!$supabaseService->upsertNews($normalized)) {
    fwrite(STDERR, "[ERROR] No se pudo migrar el contenido local a Supabase." . PHP_EOL);
    exit(1);
}

fwrite(
    STDOUT,
    sprintf('[OK] noticias_migradas=%d%s', count($normalized), PHP_EOL)
);
