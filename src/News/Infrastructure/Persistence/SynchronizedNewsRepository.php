<?php

declare(strict_types=1);

namespace PortalNoticias\News\Infrastructure\Persistence;

use PortalNoticias\News\Domain\NewsItem;
use PortalNoticias\News\Domain\NewsRepositoryInterface;
use PortalNoticias\News\Domain\UpdateLoggerInterface;
use RuntimeException;
use Throwable;

final class SynchronizedNewsRepository implements NewsRepositoryInterface
{
    public function __construct(
        private readonly NewsRepositoryInterface $primaryRepository,
        private readonly NewsRepositoryInterface $fallbackRepository,
        private readonly ?UpdateLoggerInterface $logger = null,
    ) {
    }

    public function findLatest(int $limit): array
    {
        try {
            $this->logger?->logMessage('INFO', 'Leyendo noticias desde Supabase.', [
                'limite' => $limit,
            ]);
            $primaryItems = $this->primaryRepository->findLatest($limit);
            $this->logger?->logMessage('INFO', 'Lectura desde Supabase completada.', [
                'items' => count($primaryItems),
            ]);

            if ($primaryItems !== []) {
                return $primaryItems;
            }

            $this->logger?->logMessage('WARN', 'Supabase respondio sin noticias; se usara el respaldo local.');
        } catch (Throwable $exception) {
            $this->logger?->logMessage('ERROR', 'Fallo la lectura desde Supabase; se usara el respaldo local.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $fallbackItems = $this->fallbackRepository->findLatest($limit);
        $this->logger?->logMessage('INFO', 'Lectura desde JSON local completada.', [
            'items' => count($fallbackItems),
        ]);

        return $fallbackItems;
    }

    public function saveAll(array $items): void
    {
        $errors = [];

        try {
            $this->logger?->logMessage('INFO', 'Guardando noticias en Supabase.', [
                'items' => count($items),
            ]);
            $this->primaryRepository->saveAll($items);
            $this->logger?->logMessage('INFO', 'Guardado en Supabase completado.', [
                'items' => count($items),
            ]);
        } catch (Throwable $exception) {
            $this->logger?->logMessage('ERROR', 'Fallo el guardado en Supabase.', [
                'error' => $exception->getMessage(),
            ]);
            $errors[] = $exception->getMessage();
        }

        try {
            $this->logger?->logMessage('INFO', 'Guardando noticias en JSON local.', [
                'items' => count($items),
            ]);
            $this->fallbackRepository->saveAll($items);
            $this->logger?->logMessage('INFO', 'Guardado en JSON local completado.', [
                'items' => count($items),
            ]);
        } catch (Throwable $exception) {
            $this->logger?->logMessage('ERROR', 'Fallo el guardado en JSON local.', [
                'error' => $exception->getMessage(),
            ]);
            $errors[] = $exception->getMessage();
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(' | ', $errors));
        }
    }

    public function latestUpdatedAt(): ?string
    {
        try {
            $updatedAt = $this->primaryRepository->latestUpdatedAt();

            if ($updatedAt !== null) {
                return $updatedAt;
            }
        } catch (Throwable) {
        }

        return $this->fallbackRepository->latestUpdatedAt();
    }

    public function findByIdentifier(string $identifier): ?NewsItem
    {
        try {
            $this->logger?->logMessage('INFO', 'Buscando noticia por identificador en Supabase.');
            $primaryItem = $this->primaryRepository->findByIdentifier($identifier);

            if ($primaryItem instanceof NewsItem) {
                return $primaryItem;
            }

            $this->logger?->logMessage('WARN', 'Supabase no encontro noticia por identificador; se usara respaldo local.');
        } catch (Throwable $exception) {
            $this->logger?->logMessage('ERROR', 'Fallo la busqueda por identificador en Supabase; se usara respaldo local.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return $this->fallbackRepository->findByIdentifier($identifier);
    }
}
