<?php
require_once __DIR__ . '/../app/config/config.php';

$apiEndpoint = '/api/news.php';
$newsId = isset($_GET['id']) ? (string) $_GET['id'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de noticia | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Detalle completo de una noticia ABI dentro del portal.">
    <link rel="stylesheet" href="./assets/css/styles.css">
</head>
<body>
    <div class="site-shell detail-shell" data-api-endpoint="<?php echo htmlspecialchars($apiEndpoint, ENT_QUOTES, 'UTF-8'); ?>" data-news-id="<?php echo htmlspecialchars($newsId, ENT_QUOTES, 'UTF-8'); ?>">
        <header class="site-header detail-header">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">ABI</div>
                <div>
                    <p class="eyebrow">Lectura dentro del portal</p>
                    <h1>Detalle de noticia</h1>
                </div>
            </div>
            <p class="hero-copy">Consulta la noticia completa sin salir del portal y usa la fuente original de ABI cuando quieras contrastar el contenido.</p>
        </header>

        <main class="content">
            <nav class="detail-nav">
                <a class="detail-back" href="/">Volver al portal</a>
            </nav>

            <div id="detail-loading" class="status-card">
                <strong>Cargando noticia...</strong>
                <p>Estamos buscando el contenido completo en el archivo local del portal.</p>
            </div>

            <div id="detail-error" class="status-card is-hidden is-error" role="alert">
                <strong>No fue posible abrir esta noticia.</strong>
                <p>La noticia no existe, cambio su identificador o aun no fue sincronizada.</p>
            </div>

            <article id="news-detail" class="detail-article is-hidden"></article>
        </main>

        <footer class="site-footer">
            <p>Desarrollado por <?php echo htmlspecialchars(FOOTER_AUTHOR, ENT_QUOTES, 'UTF-8'); ?> | <?php echo date('Y'); ?></p>
        </footer>
    </div>

    <button id="back-to-top" class="back-to-top" type="button" aria-label="Volver arriba">Subir</button>

    <script src="./assets/js/news-detail.js" defer></script>
</body>
</html>