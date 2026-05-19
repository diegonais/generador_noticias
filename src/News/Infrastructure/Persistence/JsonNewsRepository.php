<?php

declare(strict_types=1);

namespace PortalNoticias\News\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PortalNoticias\News\Domain\NewsItem;
use PortalNoticias\News\Domain\NewsRepositoryInterface;
use PortalNoticias\Shared\Config\AppConfig;
use RuntimeException;

final class JsonNewsRepository implements NewsRepositoryInterface
{
    public function __construct(private readonly AppConfig $config)
    {
        $this->ensureDirectories();
    }

    public function findLatest(int $limit): array
    {
        $this->ensureDirectories();
        $path = $this->config->newsJsonPath();

        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $content = file_get_contents($path);

        if (!is_string($content) || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            return [];
        }

        $items = [];

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $newsItem = NewsItem::fromArray($item, $this->config->timezone());

            if ($newsItem !== null) {
                $items[] = $newsItem;
            }
        }

        if ($limit <= 0 || $limit === PHP_INT_MAX) {
            return $items;
        }

        return array_slice($items, 0, $limit);
    }

    public function saveAll(array $items): void
    {
        $this->ensureDirectories();

        $json = json_encode(
            array_map(
                static fn (NewsItem $item): array => $item->toArray(),
                array_values($items),
            ),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        if (!is_string($json)) {
            throw new RuntimeException('No se pudo serializar el archivo local de noticias.');
        }

        if (file_put_contents($this->config->newsJsonPath(), $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el archivo local de noticias.');
        }
    }

    public function latestUpdatedAt(): ?string
    {
        $path = $this->config->newsJsonPath();

        if (!is_file($path)) {
            return null;
        }

        $timestamp = filemtime($path);

        if ($timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone($this->config->timezone()))
            ->format(DATE_ATOM);
    }

    public function findByIdentifier(string $identifier): ?NewsItem
    {
        $candidate = trim($identifier);

        if ($candidate === '') {
            return null;
        }

        foreach ($this->findLatest(PHP_INT_MAX) as $item) {
            if (!$item instanceof NewsItem) {
                continue;
            }

            if (
                $this->identifiersMatch((string) $item->guid(), $candidate)
                || $this->identifiersMatch((string) $item->link(), $candidate)
            ) {
                return $item;
            }
        }

        return null;
    }

    private function ensureDirectories(): void
    {
        $directories = [
            dirname($this->config->newsJsonPath()),
            dirname($this->config->logPath()),
            $this->config->cachePath(),
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }

        if (!is_file($this->config->newsJsonPath())) {
            file_put_contents($this->config->newsJsonPath(), "[]\n", LOCK_EX);
        }

        if (!is_file($this->config->logPath())) {
            file_put_contents($this->config->logPath(), '', LOCK_EX);
        }
    }

    private function identifiersMatch(string $left, string $right): bool
    {
        if (trim($left) === '' || trim($right) === '') {
            return false;
        }

        $leftCandidates = $this->expandIdentifierCandidates($left);
        $rightCandidates = $this->expandIdentifierCandidates($right);

        foreach ($leftCandidates as $candidate) {
            if (in_array($candidate, $rightCandidates, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function expandIdentifierCandidates(string $value): array
    {
        $raw = trim($value);
        $decoded = rawurldecode($raw);
        $doubleDecoded = rawurldecode($decoded);

        $candidates = array_values(array_filter([
            $this->normalizeIdentifier($raw),
            $this->normalizeIdentifier($decoded),
            $this->normalizeIdentifier($doubleDecoded),
        ]));

        return array_values(array_unique($candidates));
    }

    private function normalizeIdentifier(string $value): string
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
}
