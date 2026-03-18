<?php

if (!function_exists('loadEnvFile')) {
    function loadEnvFile($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || substr($line, 0, 1) === '#' || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");

            if ($key === '') {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('envValue')) {
    function envValue($key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);

        return $value !== false ? $value : $default;
    }
}

if (!function_exists('envFlag')) {
    function envFlag($key, $default = false)
    {
        $value = strtolower((string) envValue($key, $default ? 'true' : 'false'));

        return in_array($value, array('1', 'true', 'yes', 'on'), true);
    }
}

$basePath = dirname(__DIR__, 2);

loadEnvFile($basePath . DIRECTORY_SEPARATOR . '.env');

defined('BASE_PATH') || define('BASE_PATH', $basePath);
defined('APP_NAME') || define('APP_NAME', envValue('APP_NAME', 'Portal Noticias ABI'));
defined('TIMEZONE') || define('TIMEZONE', envValue('TIMEZONE', 'America/La_Paz'));
defined('ABI_RSS_URL') || define('ABI_RSS_URL', envValue('ABI_RSS_URL', 'https://abi.bo/feed/'));
defined('NEWS_JSON_PATH') || define('NEWS_JSON_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'news.json');
defined('LOG_PATH') || define('LOG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'update.log');
defined('CACHE_PATH') || define('CACHE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache');
defined('MAX_NEWS_ITEMS') || define('MAX_NEWS_ITEMS', (int) envValue('MAX_NEWS_ITEMS', 60));
defined('FOOTER_AUTHOR') || define('FOOTER_AUTHOR', envValue('FOOTER_AUTHOR', 'Diego'));
defined('SUPABASE_ENABLED') || define('SUPABASE_ENABLED', envFlag('SUPABASE_ENABLED', false));
defined('SUPABASE_URL') || define('SUPABASE_URL', rtrim((string) envValue('SUPABASE_URL', ''), '/'));
defined('SUPABASE_SERVICE_ROLE_KEY') || define('SUPABASE_SERVICE_ROLE_KEY', (string) envValue('SUPABASE_SERVICE_ROLE_KEY', ''));
defined('SUPABASE_TABLE') || define('SUPABASE_TABLE', (string) envValue('SUPABASE_TABLE', 'news'));

date_default_timezone_set(TIMEZONE);
