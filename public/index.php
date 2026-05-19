<?php

declare(strict_types=1);

$container = require __DIR__ . '/../bootstrap/app.php';
$config = $container->config();
$apiEndpoint = '/api/news.php';

function assetUrl(string $path): string
{
    $normalizedPath = ltrim($path, '/');
    $fullPath = __DIR__ . '/' . $normalizedPath;
    $version = is_file($fullPath) ? (string) filemtime($fullPath) : '1';

    return './' . $normalizedPath . '?v=' . rawurlencode($version);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Portal editorial de seguimiento actualizado con noticias recientes de la Agencia Boliviana de Informacion.">
    <script>
        (function () {
            var key = 'portal_theme';
            var storedTheme = null;

            try {
                storedTheme = localStorage.getItem(key);
            } catch (error) {
                storedTheme = null;
            }

            var theme = storedTheme;

            if (theme !== 'dark' && theme !== 'light') {
                var prefersDark = false;

                try {
                    prefersDark = Boolean(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                } catch (error) {
                    prefersDark = false;
                }

                theme = prefersDark ? 'dark' : 'light';
            }

            if (theme === 'dark') {
                document.documentElement.classList.add('theme-dark');
            } else {
                document.documentElement.classList.remove('theme-dark');
            }
        })();
    </script>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <header class="site-header">
        <div class="site-header__main">
            <div class="site-header__main-inner">
                <div class="brand">
                    <div class="brand-mark" aria-hidden="true">
                        <img class="brand-mark__image" src="<?php echo htmlspecialchars(assetUrl('assets/img/df_noticias_logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="">
                    </div>
                    <div class="brand__text">
                        <h1><?php echo htmlspecialchars($config->appName(), ENT_QUOTES, 'UTF-8'); ?></h1>
                    </div>
                </div>
                <p class="hero-copy">
                    Monitoreo actualizado de la agenda informativa nacional con acceso r&aacute;pido a publicaciones, fechas y fuentes oficiales.
                </p>
            </div>
        </div>
    </header>

    <div
        class="site-shell"
        data-api-endpoint="<?php echo htmlspecialchars($apiEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
        data-timezone="<?php echo htmlspecialchars($config->timezone(), ENT_QUOTES, 'UTF-8'); ?>"
        data-max-news-items="<?php echo (int) $config->maxNewsItems(); ?>"
    >

        <main class="content">
            <section class="content-head">
                <div>
                    <p class="section-kicker">Cobertura reciente</p>
                </div>
                <p id="last-updated" class="last-updated" aria-live="polite"></p>
            </section>

            <div class="toolbar-actions">
                <button id="filter-toggle" class="filter-toggle" type="button" aria-expanded="false" aria-controls="filters-panel">
                    Filtrar fecha
                </button>
                <button id="theme-toggle" class="theme-toggle" type="button" aria-label="Cambiar a tema oscuro" aria-pressed="false">&#9790;</button>
            </div>

            <section id="filters-panel" class="filters-card filters-panel filters-panel--collapsed" aria-label="Filtros de noticias" aria-hidden="true">
                <div class="calendar-filter" aria-label="Calendario de noticias">
                    <div class="calendar-filter__header">
                        <button id="calendar-prev" class="calendar-filter__nav" type="button" aria-label="Mes anterior">
                            <svg class="calendar-filter__nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M15 6l-6 6l6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <button id="calendar-current-month" class="calendar-filter__title-trigger" type="button" aria-label="Seleccionar mes y a&ntilde;o" aria-haspopup="true" aria-expanded="false" aria-controls="calendar-month-year-picker"></button>
                        <button id="calendar-next" class="calendar-filter__nav" type="button" aria-label="Mes siguiente">
                            <svg class="calendar-filter__nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 6l6 6l-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                    <div id="calendar-month-year-picker" class="calendar-filter__picker is-hidden" aria-hidden="true">
                        <label class="calendar-filter__picker-field" for="calendar-year-select">
                            <span>A&ntilde;o</span>
                            <select id="calendar-year-select" class="calendar-filter__picker-select"></select>
                        </label>
                        <label class="calendar-filter__picker-field" for="calendar-month-select">
                            <span>Mes</span>
                            <select id="calendar-month-select" class="calendar-filter__picker-select"></select>
                        </label>
                    </div>
                    <div class="calendar-filter__weekdays" aria-hidden="true">
                        <span>Lun</span>
                        <span>Mar</span>
                        <span>Mie</span>
                        <span>Jue</span>
                        <span>Vie</span>
                        <span>Sab</span>
                        <span>Dom</span>
                    </div>
                    <div id="calendar-grid" class="calendar-filter__grid" role="grid" aria-label="Dias del mes"></div>
                </div>

                <div class="filters-actions">
                    <button id="filter-today" class="filter-action" type="button">Hoy</button>
                </div>
            </section>

            <div id="loading-state" class="status-card">
                <strong>Actualizando cobertura</strong>
                <p>Sincronizando las publicaciones disponibles.</p>
            </div>

            <div id="error-state" class="status-card is-hidden is-error" role="alert">
                <strong>No se pudo actualizar el contenido.</strong>
                <p>Revisa la conexi&oacute;n o intenta nuevamente en unos minutos.</p>
            </div>

            <div id="empty-state" class="status-card is-hidden">
                <strong>No hay publicaciones para mostrar.</strong>
                <p id="empty-state-message">Las nuevas actualizaciones aparecer&aacute;n en este espacio.</p>
            </div>

            <section id="news-list" class="news-grid is-hidden" aria-live="polite" aria-busy="true"></section>
        </main>

    </div>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__info">
                <p class="site-footer__label">Centro informativo</p>
                <p class="site-footer__copy">Seguimiento organizado de publicaciones ABI para lectura y consulta diaria.</p>
            </div>
            <p class="site-footer__credit">
                Dise&ntilde;ado y desarrollado por
                <a href="https://diegonais.vercel.app" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($config->footerAuthor(), ENT_QUOTES, 'UTF-8'); ?></a>
                - <?php echo date('Y'); ?>
            </p>
        </div>
    </footer>

    <button id="back-to-top" class="back-to-top" type="button" aria-label="Volver arriba">
        <svg class="back-to-top__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M12 19V5"></path>
            <path d="M5 12l7-7l7 7"></path>
        </svg>
    </button>

    <noscript>
        <div class="noscript-message">Este portal necesita JavaScript habilitado para mostrar las noticias.</div>
    </noscript>

    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/news-shared.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/app.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>

