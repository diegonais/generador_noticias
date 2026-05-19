<?php

declare(strict_types=1);

namespace PortalNoticias\News\Infrastructure\Persistence;

use PortalNoticias\News\Domain\NewsItem;
use PortalNoticias\News\Domain\NewsRepositoryInterface;
use PortalNoticias\Shared\Config\AppConfig;
use PortalNoticias\Shared\Http\HttpClientInterface;
use RuntimeException;

final class SupabaseNewsRepository implements NewsRepositoryInterface
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function findLatest(int $limit): array
    {
        $this->guardConfiguration();

        $queryParams = [
            'select' => 'guid,title,summary,link,source,published_at,image,created_at,updated_at',
            'order' => 'published_at.desc',
        ];

        if ($limit > 0 && $limit !== PHP_INT_MAX) {
            $queryParams['limit'] = $limit;
        }

        $query = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $response = $this->httpClient->request(
            'GET',
            $this->buildTableUrl() . '?' . $query,
            $this->defaultHeaders(),
            null,
            false,
        );

        if (!$response->isSuccessful()) {
            throw new RuntimeException(
                'No se pudo leer noticias desde Supabase. ' . $this->summarizeResponse($response->statusCode(), $response->error(), $response->body())
            );
        }

        $decoded = $this->decodeJson($response->body());

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'La respuesta de Supabase no tiene el formato esperado. ' . $this->summarizeResponse($response->statusCode(), null, $response->body())
            );
        }

        $items = [];

        foreach ($decoded as $record) {
            if (!is_array($record)) {
                continue;
            }

            $item = NewsItem::fromArray($record, $this->config->timezone());

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function saveAll(array $items): void
    {
        $this->guardConfiguration();

        if (count($items) === 0) {
            return;
        }

        $payload = array_map(static function (NewsItem $item): array {
            return [
                'guid' => $item->guid(),
                'title' => $item->title(),
                'summary' => $item->summary(),
                'link' => $item->link(),
                'source' => $item->source(),
                'published_at' => $item->publishedAt(),
                'image' => $item->image() !== '' ? $item->image() : null,
            ];
        }, $items);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($body)) {
            throw new RuntimeException('No se pudo preparar la carga para Supabase.');
        }

        $query = http_build_query([
            'on_conflict' => 'guid',
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->httpClient->request(
            'POST',
            $this->buildTableUrl() . '?' . $query,
            array_merge(
                $this->defaultHeaders(),
                ['Content-Type: application/json', 'Prefer: resolution=merge-duplicates,return=minimal']
            ),
            $body,
            false,
        );

        if ($response->statusCode() < 200 || $response->statusCode() >= 300) {
            throw new RuntimeException(
                'No se pudo guardar noticias en Supabase. ' . $this->summarizeResponse($response->statusCode(), $response->error(), $response->body())
            );
        }
    }

    public function latestUpdatedAt(): ?string
    {
        $this->guardConfiguration();

        $query = http_build_query([
            'select' => 'updated_at',
            'order' => 'updated_at.desc',
            'limit' => 1,
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->httpClient->request(
            'GET',
            $this->buildTableUrl() . '?' . $query,
            $this->defaultHeaders(),
            null,
            false,
        );

        if (!$response->isSuccessful()) {
            throw new RuntimeException(
                'No se pudo leer la fecha de actualizacion desde Supabase. ' . $this->summarizeResponse($response->statusCode(), $response->error(), $response->body())
            );
        }

        $decoded = $this->decodeJson($response->body());

        if (!is_array($decoded) || !isset($decoded[0]['updated_at'])) {
            return null;
        }

        $updatedAt = trim((string) $decoded[0]['updated_at']);

        return $updatedAt !== '' ? $updatedAt : null;
    }

    public function findByIdentifier(string $identifier): ?NewsItem
    {
        $this->guardConfiguration();

        $candidates = $this->expandIdentifierCandidates($identifier);

        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            $item = $this->findFirstByField('guid', $candidate);

            if ($item instanceof NewsItem) {
                return $item;
            }
        }

        foreach ($candidates as $candidate) {
            $item = $this->findFirstByField('link', $candidate);

            if ($item instanceof NewsItem) {
                return $item;
            }
        }

        return null;
    }

    private function guardConfiguration(): void
    {
        if (!$this->config->isSupabaseConfigured()) {
            throw new RuntimeException('Supabase no esta configurado.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function defaultHeaders(): array
    {
        return [
            'apikey: ' . $this->config->supabaseServiceRoleKey(),
            'Authorization: Bearer ' . $this->config->supabaseServiceRoleKey(),
            'Accept: application/json',
        ];
    }

    private function buildTableUrl(): string
    {
        return $this->config->supabaseUrl() . '/rest/v1/' . rawurlencode($this->config->supabaseTable());
    }

    private function summarizeResponse(int $statusCode, ?string $error, ?string $body): string
    {
        $parts = ['status=' . $statusCode];

        if ($error !== null && trim($error) !== '') {
            $parts[] = 'error="' . $this->compactText($error) . '"';
        }

        if ($body !== null && trim($body) !== '') {
            $parts[] = 'body="' . $this->compactText($body) . '"';
        }

        return implode(' ', $parts);
    }

    private function findFirstByField(string $field, string $value): ?NewsItem
    {
        $query = http_build_query([
            'select' => 'guid,title,summary,link,source,published_at,image,created_at,updated_at',
            $field => 'eq.' . $value,
            'limit' => 1,
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->httpClient->request(
            'GET',
            $this->buildTableUrl() . '?' . $query,
            $this->defaultHeaders(),
            null,
            false,
        );

        if (!$response->isSuccessful()) {
            return null;
        }

        $decoded = $this->decodeJson($response->body());

        if (!is_array($decoded) || !isset($decoded[0]) || !is_array($decoded[0])) {
            return null;
        }

        return NewsItem::fromArray($decoded[0], $this->config->timezone());
    }

    /**
     * @return array<int, string>
     */
    private function expandIdentifierCandidates(string $value): array
    {
        $raw = trim($value);
        $decoded = rawurldecode($raw);
        $doubleDecoded = rawurldecode($decoded);

        return array_values(array_unique(array_values(array_filter([$raw, $decoded, $doubleDecoded]))));
    }

    private function compactText(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if (strlen($value) > 300) {
            $value = substr($value, 0, 297) . '...';
        }

        return str_replace('"', '\"', $value);
    }

    /**
     * @return mixed
     */
    private function decodeJson(?string $payload): mixed
    {
        if (!is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}

