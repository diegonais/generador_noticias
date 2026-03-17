const detailShell = document.querySelector('.detail-shell');
const detailLoading = document.querySelector('#detail-loading');
const detailError = document.querySelector('#detail-error');
const detailArticle = document.querySelector('#news-detail');
const backToTopButton = document.querySelector('#back-to-top');

const detailEndpointCandidates = Array.from(new Set([
    detailShell ? detailShell.dataset.apiEndpoint : null,
    '/api/news.php',
    '../api/news.php',
].filter(Boolean)));

const requestedId = detailShell ? detailShell.dataset.newsId : '';

document.addEventListener('DOMContentLoaded', function () {
    setupBackToTop();
    loadNewsDetail();
});

async function loadNewsDetail() {
    if (!requestedId) {
        setDetailState('error');
        return;
    }

    setDetailState('loading');

    try {
        const payload = await fetchDetailPayload();
        const news = Array.isArray(payload && payload.data) ? payload.data : [];
        const item = news.find(function (entry) {
            return String(entry.guid || '') === requestedId || String(entry.link || '') === requestedId;
        });

        if (!item) {
            setDetailState('error');
            return;
        }

        renderDetail(item);
        setDetailState('success');
    } catch (error) {
        console.error('Error loading detail:', error);
        setDetailState('error');
    }
}

async function fetchDetailPayload() {
    let lastError = new Error('No se pudo obtener la API.');

    for (const endpoint of detailEndpointCandidates) {
        try {
            const response = await fetch(endpoint, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Respuesta ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError;
}

function renderDetail(item) {
    const imageMarkup = item.image
        ? `<img class="detail-hero__image" src="${escapeAttribute(item.image)}" alt="${escapeAttribute(item.title || 'Noticia ABI')}" loading="eager">`
        : '';

    const fullText = normalizeArticleText(item.summary || 'Sin contenido disponible.');

    detailArticle.innerHTML = `
        <div class="detail-hero">
            ${imageMarkup}
            <div class="detail-hero__body">
                <p class="section-kicker">${escapeHtml(item.source || 'ABI')}</p>
                <h2 class="detail-title">${escapeHtml(item.title || 'Sin titulo')}</h2>
                <div class="detail-meta">
                    <span>${formatDate(item.published_at, true)}</span>
                    <span>Fuente oficial ABI</span>
                </div>
            </div>
        </div>
        <div class="detail-content">
            <p class="detail-text">${escapeHtml(fullText)}</p>
            <div class="detail-actions">
                <a class="detail-action" href="/">Volver al portal</a>
                <a class="detail-action detail-action--primary" href="${escapeAttribute(item.link || '#')}" target="_blank" rel="noopener noreferrer">Ver fuente original en ABI</a>
            </div>
        </div>
    `;

    document.title = `${item.title || 'Noticia'} | Portal Noticias ABI`;
}

function normalizeArticleText(text) {
    return String(text || '')
        .replace(/\s*Navegaci[oÃ³]n de entradas.*$/iu, '')
        .replace(/\s+[A-ZÃÃ‰ÃÃ“ÃšÃ‘][A-Za-zÃÃ‰ÃÃ“ÃšÃ‘a-zÃ¡Ã©Ã­Ã³ÃºÃ±]{1,5}(?:\/[A-ZÃÃ‰ÃÃ“ÃšÃ‘][A-Za-zÃÃ‰ÃÃ“ÃšÃ‘a-zÃ¡Ã©Ã­Ã³ÃºÃ±]{1,5})+\s*$/u, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function setDetailState(state) {
    detailLoading.classList.toggle('is-hidden', state !== 'loading');
    detailError.classList.toggle('is-hidden', state !== 'error');
    detailArticle.classList.toggle('is-hidden', state !== 'success');
}

function formatDate(dateString, withTime) {
    if (!dateString) {
        return 'Fecha no disponible';
    }

    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
        return 'Fecha no disponible';
    }

    return new Intl.DateTimeFormat('es-BO', Object.assign({
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }, withTime ? {
        hour: '2-digit',
        minute: '2-digit',
    } : {})).format(date);
}

function setupBackToTop() {
    if (!backToTopButton) {
        return;
    }

    const toggleVisibility = function () {
        backToTopButton.classList.toggle('is-visible', window.scrollY > 500);
    };

    backToTopButton.addEventListener('click', function () {
        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    });

    window.addEventListener('scroll', toggleVisibility, { passive: true });
    toggleVisibility();
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escapeAttribute(value) {
    return escapeHtml(value);
}