<?php

require_once __DIR__ . '/SupabaseService.php';

class StorageService
{
    private $supabaseService;

    public function __construct()
    {
        $this->ensureDirectories();
        $this->supabaseService = new SupabaseService();
    }

    public function readNews()
    {
        if ($this->useSupabase()) {
            $news = $this->supabaseService->fetchNews(MAX_NEWS_ITEMS);

            if (is_array($news)) {
                return $news;
            }
        }

        return $this->readLocalNews();
    }

    public function saveNews(array $news)
    {
        $this->ensureDirectories();

        if ($this->useSupabase()) {
            $savedToSupabase = $this->supabaseService->upsertNews($news);
            $savedLocally = $this->saveLocalNews($news);

            return $savedToSupabase && $savedLocally;
        }

        return $this->saveLocalNews($news);
    }

    public function appendLog($message)
    {
        $this->ensureDirectories();
        file_put_contents(LOG_PATH, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function logUpdate($obtainedCount, $newCount, $error = null)
    {
        $line = sprintf(
            '[%s] obtenidas=%d nuevas=%d',
            DateHelper::nowForLog(),
            (int) $obtainedCount,
            (int) $newCount
        );

        if ($error !== null && trim($error) !== '') {
            $line .= ' error="' . TextHelper::normalizeWhitespace($error) . '"';
        }

        $this->appendLog($line);
    }

    public function getLastUpdatedAt()
    {
        if ($this->useSupabase()) {
            $updatedAt = $this->supabaseService->fetchLatestUpdatedAt();

            if ($updatedAt !== null) {
                return $updatedAt;
            }
        }

        return $this->getLocalLastUpdatedAt();
    }

    private function useSupabase()
    {
        return $this->supabaseService instanceof SupabaseService
            && $this->supabaseService->isConfigured();
    }

    private function readLocalNews()
    {
        if (!is_file(NEWS_JSON_PATH) || !is_readable(NEWS_JSON_PATH)) {
            return array();
        }

        $content = file_get_contents(NEWS_JSON_PATH);

        if (!is_string($content) || trim($content) === '') {
            return array();
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : array();
    }

    private function saveLocalNews(array $news)
    {
        $json = json_encode(
            array_values($news),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if (!is_string($json)) {
            return false;
        }

        return file_put_contents(NEWS_JSON_PATH, $json . PHP_EOL, LOCK_EX) !== false;
    }

    private function getLocalLastUpdatedAt()
    {
        if (!is_file(NEWS_JSON_PATH)) {
            return null;
        }

        $timestamp = filemtime(NEWS_JSON_PATH);

        if ($timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone(TIMEZONE))
            ->format(DATE_ATOM);
    }

    private function ensureDirectories()
    {
        $directories = array(
            dirname(NEWS_JSON_PATH),
            dirname(LOG_PATH),
            CACHE_PATH,
        );

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }

        if (!is_file(NEWS_JSON_PATH)) {
            file_put_contents(NEWS_JSON_PATH, "[]\n", LOCK_EX);
        }

        if (!is_file(LOG_PATH)) {
            file_put_contents(LOG_PATH, '', LOCK_EX);
        }
    }
}
