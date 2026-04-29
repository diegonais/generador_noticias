const detailShell = document.querySelector('.detail-shell');
const detailLoading = document.querySelector('#detail-loading');
const detailError = document.querySelector('#detail-error');
const detailArticle = document.querySelector('#news-detail');
const backToTopButton = document.querySelector('#back-to-top');
const utils = window.NewsPortalUtils;
const THEME_STORAGE_KEY = 'portal_theme';

const detailEndpointCandidates = Array.from(new Set([
    detailShell ? detailShell.dataset.apiEndpoint : null,
    '/api/news.php',
    '../api/news.php',
].filter(Boolean)));

const requestedId = detailShell ? detailShell.dataset.newsId : '';

document.addEventListener('DOMContentLoaded', function () {
    initializeTheme();
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
            const response = await fetch(endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now(), {
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

function renderDetail(item) {
    const imageMarkup = item.image
        ? `<img class="detail-hero__image" src="${utils.escapeAttribute(item.image)}" alt="${utils.escapeAttribute(item.title || 'Noticia ABI')}" loading="eager">`
        : '';

    const paragraphs = utils.buildReadableParagraphs(item.summary || 'Sin contenido disponible.');
    const bodyMarkup = (paragraphs.length > 0 ? paragraphs : ['Sin contenido disponible.'])
        .map(function (paragraph) {
            return `<p class="detail-text">${utils.escapeHtml(paragraph)}</p>`;
        })
        .join('');

    detailArticle.innerHTML = `
        <div class="detail-hero">
            ${imageMarkup}
            <div class="detail-hero__body">
                <p class="section-kicker">${utils.escapeHtml(item.source || 'ABI')}</p>
                <h2 class="detail-title">${utils.escapeHtml(item.title || 'Sin titulo')}</h2>
                <div class="detail-meta">
                    <span>${utils.formatDate(item.published_at, true)}</span>
                    <span>Fuente oficial ABI</span>
                </div>
            </div>
        </div>
        <div class="detail-content">
            <div class="detail-body">${bodyMarkup}</div>
            <div class="detail-actions">
                <a class="detail-action" href="/">Volver al portal</a>
                <a class="detail-action detail-action--primary" href="${utils.escapeAttribute(item.link || '#')}" target="_blank" rel="noopener noreferrer">Ver fuente original en ABI</a>
            </div>
        </div>
    `;

    document.title = `${item.title || 'Noticia'} | Portal Noticias ABI`;
}

function setDetailState(state) {
    detailLoading.classList.toggle('is-hidden', state !== 'loading');
    detailError.classList.toggle('is-hidden', state !== 'error');
    detailArticle.classList.toggle('is-hidden', state !== 'success');
}

function initializeTheme() {
    const storedTheme = readStoredTheme();

    if (storedTheme === 'dark' || storedTheme === 'light') {
        applyTheme(storedTheme);
        return;
    }

    let preferredTheme = 'light';

    try {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            preferredTheme = 'dark';
        }
    } catch (error) {
    }

    applyTheme(preferredTheme);
}

function readStoredTheme() {
    try {
        return localStorage.getItem(THEME_STORAGE_KEY);
    } catch (error) {
        return null;
    }
}

function applyTheme(theme) {
    document.body.classList.toggle('theme-dark', theme === 'dark');
}
function setupBackToTop() {
    utils.setupBackToTop(backToTopButton);
}

