<?php

declare(strict_types=1);

$container = require __DIR__ . '/../bootstrap/app.php';
$config = $container->config();
$apiEndpoint = '/api/news.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Portal simple para visualizar noticias recientes de la Agencia Boliviana de Informacion.">
    <link rel="stylesheet" href="./assets/css/styles.css">
</head>
<body>
    <div
        class="site-shell"
        data-api-endpoint="<?php echo htmlspecialchars($apiEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
        data-timezone="<?php echo htmlspecialchars($config->timezone(), ENT_QUOTES, 'UTF-8'); ?>"
    >
        <header class="site-header">

            <div class="brand">
                <div class="brand-mark" aria-hidden="true">ABI</div>
                <div>
                    <h1><?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?></h1>
                </div>
            </div>
            <p class="hero-copy">
                Noticias recientes obtenidas de la Agencia Boliviana de Informacion (ABI).
            </p>
        </header>

        <main class="content">
            <section class="content-head">
                <div>
                    <p class="section-kicker">&Uacute;ltimas publicaciones</p>
                </div>
                <p id="last-updated" class="last-updated" aria-live="polite"></p>
            </section>

            <div class="toolbar-actions">
                <button id="filter-toggle" class="filter-toggle" type="button" aria-expanded="false" aria-controls="filters-panel">
                    Buscar por fecha
                </button>
                <button id="theme-toggle" class="theme-toggle" type="button" aria-label="Cambiar a tema oscuro" aria-pressed="false">&#9790;</button>
            </div>

            <section id="filters-panel" class="filters-card filters-panel filters-panel--collapsed" aria-label="Filtros de noticias" aria-hidden="true">
                <div class="filters-grid">
                    <label class="filter-field" for="filter-year">
                        <span class="filter-field__label">A&ntilde;o</span>
                        <select id="filter-year" class="filter-field__control">
                            <option value="">Sin fechas</option>
                        </select>
                    </label>

                    <label class="filter-field" for="filter-month">
                        <span class="filter-field__label">Mes</span>
                        <select id="filter-month" class="filter-field__control">
                            <option value="">Sin fechas</option>
                        </select>
                    </label>

                    <label class="filter-field" for="filter-day">
                        <span class="filter-field__label">Dia</span>
                        <select id="filter-day" class="filter-field__control">
                            <option value="">Sin fechas</option>
                        </select>
                    </label>
                </div>

                <div class="filters-actions">
                    <button id="filter-today" class="filter-action" type="button">Hoy</button>
                </div>
            </section>

            <div id="loading-state" class="status-card">
                <strong>Cargando noticias...</strong>
                <p>Estamos consultando las noticias almacenadas.</p>
            </div>

            <div id="error-state" class="status-card is-hidden is-error" role="alert">
                <strong>No fue posible cargar las noticias.</strong>
                <p>Intenta nuevamente en unos minutos.</p>
            </div>

            <div id="empty-state" class="status-card is-hidden">
                <strong>Todav&iacute;a no hay noticias disponibles.</strong>
                <p id="empty-state-message">Cuando el sistema vuelva a actualizarse, las publicaciones apareceran aqui.</p>
            </div>

            <section id="news-list" class="news-grid is-hidden" aria-live="polite" aria-busy="true"></section>
        </main>

        <footer class="site-footer">
            <div class="site-footer__inner">
                <div class="site-footer__brand">
                    <p class="site-footer__label">Recopilador Informativo</p>
                    <h2 class="site-footer__title"><?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="site-footer__copy">Cobertura autom&aacute;tica de noticias ABI con visualizaci&oacute;n optimizada para consulta diaria.</p>
                </div>

                <div class="site-footer__meta" aria-label="Informacion del portal">
                    <p><span>Fuente ::</span> ABI RSS oficial</p>
                    <p><span>Frecuencia ::</span> Actualizaci&oacute;n cada 5 minutos</p>
                    <p><span>Desarrollo ::</span> <?php echo htmlspecialchars($config->footerAuthor(), ENT_QUOTES, 'UTF-8'); ?> | <?php echo date('Y'); ?></p>
                </div>
            </div>
        </footer>
    </div>

    <button id="back-to-top" class="back-to-top" type="button" aria-label="Volver arriba">Subir</button>

    <noscript>
        <div class="noscript-message">Este portal necesita JavaScript habilitado para mostrar las noticias.</div>
    </noscript>

    <script src="./assets/js/news-shared.js" defer></script>
    <script src="./assets/js/app.js" defer></script>
</body>
</html>

