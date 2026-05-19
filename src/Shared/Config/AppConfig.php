<?php

declare(strict_types=1);

namespace PortalNoticias\Shared\Config;

final class AppConfig
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $appName,
        private readonly string $publicBaseUrl,
        private readonly string $timezone,
        private readonly string $abiRssUrl,
        private readonly int $maxNewsItems,
        private readonly string $footerAuthor,
        private readonly bool $supabaseEnabled,
        private readonly string $supabaseUrl,
        private readonly string $supabaseServiceRoleKey,
        private readonly string $supabaseTable,
        private readonly string $ingestToken,
    ) {
    }

    public static function fromBasePath(string $basePath): self
    {
        EnvironmentLoader::load($basePath . DIRECTORY_SEPARATOR . '.env');

        $config = new self(
            basePath: $basePath,
            appName: self::envValue('APP_NAME', 'Portal Noticias ABI'),
            publicBaseUrl: rtrim(self::envValue('APP_PUBLIC_URL', self::envValue('APP_URL', '')), '/'),
            timezone: self::envValue('TIMEZONE', 'America/La_Paz'),
            abiRssUrl: self::envValue('ABI_RSS_URL', 'https://abi.bo/feed/'),
            maxNewsItems: max(1, (int) self::envValue('MAX_NEWS_ITEMS', '60')),
            footerAuthor: self::envValue('FOOTER_AUTHOR', 'Diego'),
            supabaseEnabled: self::envFlag('SUPABASE_ENABLED', false),
            supabaseUrl: rtrim(self::envValue('SUPABASE_URL', ''), '/'),
            supabaseServiceRoleKey: self::envValue('SUPABASE_SERVICE_ROLE_KEY', ''),
            supabaseTable: self::envValue('SUPABASE_TABLE', 'news'),
            ingestToken: self::envValue('INGEST_TOKEN', ''),
        );

        date_default_timezone_set($config->timezone());

        return $config;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function appName(): string
    {
        return $this->appName;
    }

    public function publicBaseUrl(): string
    {
        return $this->publicBaseUrl;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function abiRssUrl(): string
    {
        return $this->abiRssUrl;
    }

    public function maxNewsItems(): int
    {
        return $this->maxNewsItems;
    }

    public function footerAuthor(): string
    {
        return $this->footerAuthor;
    }

    public function isSupabaseEnabled(): bool
    {
        return $this->supabaseEnabled;
    }

    public function isSupabaseConfigured(): bool
    {
        return $this->supabaseEnabled
            && $this->supabaseUrl !== ''
            && $this->supabaseServiceRoleKey !== ''
            && $this->supabaseTable !== '';
    }

    public function supabaseUrl(): string
    {
        return $this->supabaseUrl;
    }

    public function supabaseServiceRoleKey(): string
    {
        return $this->supabaseServiceRoleKey;
    }

    public function supabaseTable(): string
    {
        return $this->supabaseTable;
    }

    public function ingestToken(): string
    {
        return $this->ingestToken;
    }

    public function newsJsonPath(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'news.json';
    }

    public function logPath(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'update.log';
    }

    public function cachePath(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
    }

    private static function envValue(string $key, string $default = ''): string
    {
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }

        $value = getenv($key);

        return $value !== false ? (string) $value : $default;
    }

    private static function envFlag(string $key, bool $default = false): bool
    {
        $fallback = $default ? 'true' : 'false';
        $value = strtolower(self::envValue($key, $fallback));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
