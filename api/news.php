<?php

declare(strict_types=1);

use PortalNoticias\Shared\Support\JsonResponder;

$container = require __DIR__ . '/../bootstrap/app.php';

$identifier = toOptionalString('id');
$limit = toOptionalInt('limit', 0, 10000);
$year = toOptionalInt('year', 1900, 2100);
$month = toOptionalInt('month', 1, 12);
$day = toOptionalInt('day', 1, 31);

$hasDateFilter = $year !== null || $month !== null || $day !== null;

if ($identifier !== null && $identifier !== '' && !$hasDateFilter) {
    $item = $container->jsonNewsRepository()->findByIdentifier($identifier);

    if ($item === null) {
        $item = $container->newsRepository()->findByIdentifier($identifier);
    }

    JsonResponder::send([
        'success' => true,
        'count' => $item !== null ? 1 : 0,
        'updated_at' => $container->jsonNewsRepository()->latestUpdatedAt(),
        'data' => $item !== null ? [$item->toArray()] : [],
    ]);
    exit;
}

$effectiveLimit = $limit ?? $container->config()->maxNewsItems();
$localQuickLimit = $container->config()->maxNewsItems();

if (!$hasDateFilter) {
    if ($effectiveLimit <= 0) {
        $effectiveLimit = $localQuickLimit;
    }

    // Mantiene la respuesta rapida local para portada (limites cortos),
    // pero permite pedir historial amplio desde Supabase cuando se solicita un limite mayor.
    if ($effectiveLimit <= $localQuickLimit) {
        $localItems = $container->jsonNewsRepository()->findLatest($effectiveLimit);

        if ($localItems !== []) {
            JsonResponder::send([
                'success' => true,
                'count' => count($localItems),
                'updated_at' => $container->jsonNewsRepository()->latestUpdatedAt(),
                'data' => array_map(
                    static fn ($item): array => $item->toArray(),
                    $localItems,
                ),
            ]);
            exit;
        }
    }
}

JsonResponder::send(
    $container->listNewsUseCase()->execute(
        $effectiveLimit,
        $year,
        $month,
        $day,
    )
);

function toOptionalInt(string $key, int $min, int $max): ?int
{
    if (!isset($_GET[$key])) {
        return null;
    }

    $value = filter_var(
        $_GET[$key],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => $min, 'max_range' => $max]]
    );

    return is_int($value) ? $value : null;
}

function toOptionalString(string $key): ?string
{
    if (!isset($_GET[$key])) {
        return null;
    }

    $value = trim((string) $_GET[$key]);

    return $value !== '' ? $value : null;
}
