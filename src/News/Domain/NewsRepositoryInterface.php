<?php

declare(strict_types=1);

namespace PortalNoticias\News\Domain;

interface NewsRepositoryInterface
{
    /**
     * @return array<int, NewsItem>
     */
    public function findLatest(int $limit): array;

    /**
     * @param array<int, NewsItem> $items
     */
    public function saveAll(array $items): void;

    public function latestUpdatedAt(): ?string;

    public function findByIdentifier(string $identifier): ?NewsItem;
}
