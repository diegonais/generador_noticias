const shell = document.querySelector('.site-shell');
const newsList = document.querySelector('#news-list');
const loadingState = document.querySelector('#loading-state');
const errorState = document.querySelector('#error-state');
const emptyState = document.querySelector('#empty-state');
const lastUpdated = document.querySelector('#last-updated');
const backToTopButton = document.querySelector('#back-to-top');
const AUTO_REFRESH_INTERVAL_MS = 5 * 60 * 1000;

const endpointCandidates = Array.from(new Set([
    shell ? shell.dataset.apiEndpoint : null,
    '../api/news.php',
    '/api/news.php',
    'api/news.php',
].filter(Boolean)));

document.addEventListener('DOMContentLoaded', function () {
    setupBackToTop();
    setupAutoRefresh();
    loadNews();
});

async function loadNews(showLoadingState = true) {
    if (showLoadingState) {
        setState('loading');
    }

    try {
        const payload = await fetchFromAvailableEndpoint();
        const news = Array.isArray(payload && payload.data) ? payload.data : Array.isArray(payload) ? payload : [];

        updateLastUpdated(payload && payload.updated_at ? payload.updated_at : null);

        if (news.length === 0) {
            setState('empty');
            return;
        }

        renderNews(news);
        setState('success');
    } catch (error) {
        console.error('Error loading news:', error);
        setState('error');
    }
}

async function fetchFromAvailableEndpoint() {
    let lastError = new Error('No se pudo obtener la API.');

    for (const endpoint of endpointCandidates) {
        try {
            const response = await fetch(withCacheBuster(endpoint), {
                cache: 'no-store',
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

function renderNews(news) {
    const cards = news.map(createCardMarkup).join('');
    newsList.innerHTML = cards;
}

function createCardMarkup(item) {
    const imageMarkup = item.image
        ? `<img class="news-card__image" src="${escapeAttribute(item.image)}" alt="${escapeAttribute(item.title)}" loading="lazy">`
        : `<div class="news-card__image news-card__image--placeholder" aria-hidden="true">Sin imagen</div>`;

    const detailUrl = buildDetailUrl(item);
    const shortSummary = truncateText(normalizeArticleText(item.summary || 'Sin resumen disponible.'), 280);

    return `
        <article class="news-card">
            <div class="news-card__media">
                ${imageMarkup}
            </div>
            <div class="news-card__body">
                <div class="news-card__meta">
                    <span>${escapeHtml(item.source || 'ABI')}</span>
                    <span>${formatDate(item.published_at)}</span>
                </div>
                <h3 class="news-card__title">${escapeHtml(item.title || 'Sin titulo')}</h3>
                <p class="news-card__summary">${escapeHtml(shortSummary)}</p>
                <a class="news-card__link" href="${escapeAttribute(detailUrl)}">
                    Leer mas
                </a>
            </div>
        </article>
    `;
}

function buildDetailUrl(item) {
    const id = item.guid || item.link || item.title || '';
    return `/news.php?id=${encodeURIComponent(id)}`;
}

function normalizeArticleText(text) {
    return String(text || '')
        .replace(/\s+(?:(?:\/\/?[A-Z]{2,6}\/\/?)|(?:[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*))?\s*Navegaci\S*\s+de\s+entradas[\s\S]*$/iu, '')
        .replace(/\s*\.?\s*(?=[\/A-Za-z]*\/)(?:\/{0,3}[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*\/{0,3})\s*$/u, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function truncateText(text, limit) {
    const normalized = String(text || '').trim();

    if (normalized.length <= limit) {
        return normalized;
    }

    const slice = normalized.slice(0, limit);
    const lastSpace = slice.lastIndexOf(' ');

    return `${(lastSpace > 0 ? slice.slice(0, lastSpace) : slice).trim()}...`;
}

function setState(state) {
    newsList.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');

    loadingState.classList.toggle('is-hidden', state !== 'loading');
    errorState.classList.toggle('is-hidden', state !== 'error');
    emptyState.classList.toggle('is-hidden', state !== 'empty');
    newsList.classList.toggle('is-hidden', state !== 'success');
}

function updateLastUpdated(dateString) {
    if (!lastUpdated) {
        return;
    }

    if (!dateString) {
        lastUpdated.textContent = 'Actualizacion local disponible';
        return;
    }

    lastUpdated.textContent = `Actualizado: ${formatDate(dateString, true)}`;
}

function formatDate(dateString, withTime = true) {
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
        hour12: false,
    } : {})).format(date);
}

function withCacheBuster(endpoint) {
    const separator = endpoint.indexOf('?') === -1 ? '?' : '&';

    return endpoint + separator + '_=' + Date.now();
}
function setupAutoRefresh() {
    window.setInterval(function () {
        loadNews(false);
    }, AUTO_REFRESH_INTERVAL_MS);
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





