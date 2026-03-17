<?php
require_once __DIR__ . '/../app/config/config.php';

$apiEndpoint = '/api/news.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Portal simple para visualizar noticias recientes de la Agencia Boliviana de Informacion.">
    <link rel="stylesheet" href="./assets/css/styles.css">
</head>
<body>
    <div class="site-shell" data-api-endpoint="<?php echo htmlspecialchars($apiEndpoint, ENT_QUOTES, 'UTF-8'); ?>">
        <header class="site-header">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">ABI</div>
                <div>
                    <p class="eyebrow">Actualizacion automatica cada 6 horas</p>
                    <h1><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></h1>
                </div>
            </div>
            <p class="hero-copy">
                Noticias recientes obtenidas de la Agencia Boliviana de Informacion.
            </p>
        </header>

        <main class="content">
            <section class="content-head">
                <div>
                    <p class="section-kicker">Ultimas publicaciones</p>
                </div>
                <p id="last-updated" class="last-updated" aria-live="polite"></p>
            </section>

            <div id="loading-state" class="status-card">
                <strong>Cargando noticias...</strong>
                <p>Estamos consultando el archivo local generado por el actualizador.</p>
            </div>

            <div id="error-state" class="status-card is-hidden is-error" role="alert">
                <strong>No fue posible cargar las noticias.</strong>
                <p>Intenta nuevamente en unos minutos.</p>
            </div>

            <div id="empty-state" class="status-card is-hidden">
                <strong>Por ahora no hay noticias disponibles.</strong>
                <p>Cuando el sistema vuelva a actualizarse, las publicaciones apareceran aqui.</p>
            </div>

            <section id="news-list" class="news-grid is-hidden" aria-live="polite" aria-busy="true"></section>
        </main>

        <footer class="site-footer">
            <p>Fuente: ABI RSS oficial.</p>
            <p>Desarrollado por <?php echo htmlspecialchars(FOOTER_AUTHOR, ENT_QUOTES, 'UTF-8'); ?> | <?php echo date('Y'); ?></p>
        </footer>
    </div>

    <button id="back-to-top" class="back-to-top" type="button" aria-label="Volver arriba">Subir</button>

    <noscript>
        <div class="noscript-message">Este portal necesita JavaScript habilitado para mostrar las noticias.</div>
    </noscript>

    <script src="./assets/js/app.js" defer></script>
</body>
</html>