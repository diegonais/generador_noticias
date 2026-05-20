const detailShell = document.querySelector('.detail-shell');
const detailLoading = document.querySelector('#detail-loading');
const detailError = document.querySelector('#detail-error');
const detailArticle = document.querySelector('#news-detail');
const backToTopButton = document.querySelector('#back-to-top');
const utils = window.NewsPortalUtils;
const THEME_STORAGE_KEY = 'portal_theme';
let imageViewerKeyHandler = null;
let currentDetailItem = null;

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
            return identifiersMatch(entry.guid, requestedId) || identifiersMatch(entry.link, requestedId);
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
            const endpointWithId = appendDetailIdentifier(endpoint, requestedId);
            const response = await fetch(withCacheBuster(endpointWithId), {
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

function identifiersMatch(left, right) {
    const leftValue = String(left || '').trim();
    const rightValue = String(right || '').trim();

    if (!leftValue || !rightValue) {
        return false;
    }

    const leftCandidates = expandIdentifierCandidates(leftValue);
    const rightCandidates = expandIdentifierCandidates(rightValue);

    return leftCandidates.some(function (candidate) {
        return rightCandidates.includes(candidate);
    });
}

function expandIdentifierCandidates(value) {
    const raw = String(value || '').trim();

    if (!raw) {
        return [];
    }

    const decoded = safeDecodeURIComponent(raw);
    const doubleDecoded = safeDecodeURIComponent(decoded);

    return Array.from(new Set([
        normalizeIdentifier(raw),
        normalizeIdentifier(decoded),
        normalizeIdentifier(doubleDecoded),
    ].filter(Boolean)));
}

function safeDecodeURIComponent(value) {
    try {
        return decodeURIComponent(value);
    } catch (error) {
        return value;
    }
}

function normalizeIdentifier(value) {
    const raw = String(value || '').trim();

    if (!raw) {
        return '';
    }

    try {
        const parsed = new URL(raw);
        const path = parsed.pathname !== '/' ? parsed.pathname.replace(/\/+$/, '') : '/';
        return `${parsed.protocol}//${parsed.host.toLowerCase()}${path}${parsed.search}`;
    } catch (error) {
        return raw;
    }
}

function appendDetailIdentifier(endpoint, identifier) {
    const separator = endpoint.indexOf('?') === -1 ? '?' : '&';

    return endpoint + separator + 'id=' + encodeURIComponent(String(identifier || '').trim());
}

function withCacheBuster(endpoint) {
    const separator = endpoint.indexOf('?') === -1 ? '?' : '&';

    return endpoint + separator + '_=' + Date.now();
}

function renderDetail(item) {
    currentDetailItem = item;
    const hasImage = Boolean(item.image);
    const imageCandidates = utils.buildImageSourceCandidates(item.image);
    const imageSrc = imageCandidates.length > 0 ? imageCandidates[0] : '';
    const proxyCandidates = imageCandidates.join('|');
    const shareLinks = buildShareLinks(item);
    const imageMarkup = hasImage
        ? `
            <div class="detail-hero__media">
                <img class="detail-hero__image" src="${utils.escapeAttribute(imageSrc)}" alt="${utils.escapeAttribute(item.title || 'Noticia ABI')}" loading="eager" data-proxy-candidates="${utils.escapeAttribute(proxyCandidates)}" data-proxy-index="0" onerror="window.NewsPortalUtils.handleImageLoadError(this)">
                <button class="detail-image-zoom-btn" type="button" data-image-zoom-trigger aria-label="Ver imagen completa">
                    <svg class="detail-image-zoom-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <circle cx="11" cy="11" r="6.5"></circle>
                        <line x1="16" y1="16" x2="21" y2="21"></line>
                    </svg>
                </button>
            </div>
        `
        : '';
    const imageViewerMarkup = hasImage
        ? `
            <div class="detail-image-viewer is-hidden" data-image-viewer aria-hidden="true">
                <div class="detail-image-viewer__dialog" role="dialog" aria-modal="true" aria-label="Imagen completa de la noticia">
                    <button class="detail-image-viewer__close" type="button" data-image-viewer-close aria-label="Cerrar visor de imagen">&times;</button>
                    <img class="detail-image-viewer__image" src="${utils.escapeAttribute(imageSrc)}" alt="${utils.escapeAttribute(item.title || 'Noticia ABI')}" loading="eager" data-proxy-candidates="${utils.escapeAttribute(proxyCandidates)}" data-proxy-index="0" onerror="window.NewsPortalUtils.handleImageLoadError(this)">
                </div>
            </div>
        `
        : '';

    const paragraphs = utils.buildReadableParagraphs(item.summary || 'Contenido en actualizaci\u00f3n.');
    const bodyMarkup = (paragraphs.length > 0 ? paragraphs : ['Contenido en actualizaci\u00f3n.'])
        .map(function (paragraph) {
            return `<p class="detail-text">${utils.escapeHtml(paragraph)}</p>`;
        })
        .join('');

    detailArticle.innerHTML = `
        <div class="detail-hero">
            ${imageMarkup}
            <div class="detail-hero__body">
                <p class="section-kicker">${utils.escapeHtml(item.source || 'ABI')}</p>
                <h2 class="detail-title">${utils.escapeHtml(item.title || 'Publicaci\u00f3n ABI')}</h2>
                <div class="detail-meta">
                    <span>${utils.formatDate(item.published_at, true)}</span>
                    <span>Agencia Boliviana de Informaci\u00f3n</span>
                </div>
            </div>
        </div>
        <div class="detail-content">
            <div class="detail-main">
                <div class="detail-body">${bodyMarkup}</div>
                <div class="detail-actions">
                    <a class="detail-action" href="/">Volver al sitio</a>
                    <a class="detail-action detail-action--primary" href="${utils.escapeAttribute(item.link || '#')}" target="_blank" rel="noopener noreferrer">Fuente oficial</a>
                    <div class="detail-share" aria-label="Compartir noticia">
                        <button class="detail-share__toggle" type="button" data-share-toggle aria-expanded="false" aria-controls="detail-share-options">
                            <span>Compartir</span>
                            <svg class="detail-share__toggle-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M9 6l6 6l-6 6"></path>
                            </svg>
                        </button>
                        <div class="detail-share__panel" id="detail-share-options" data-share-panel>
                            <button class="detail-share__copy" type="button" data-copy-link>Copiar vinculo</button>
                            <div class="detail-share__actions">
                                <a class="detail-share__button detail-share__button--whatsapp" href="${utils.escapeAttribute(shareLinks.whatsapp)}" target="_blank" rel="noopener noreferrer" aria-label="Compartir en WhatsApp">
                                    <svg class="detail-share__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M12 2.04C6.5 2.04 2.04 6.5 2.04 12c0 1.76.46 3.48 1.33 5L2 22l5.18-1.35A9.93 9.93 0 0 0 12 21.96c5.5 0 9.96-4.46 9.96-9.96S17.5 2.04 12 2.04zm0 18.1c-1.5 0-2.96-.4-4.24-1.14l-.3-.18-3.08.8.82-3-.2-.31A8.13 8.13 0 0 1 3.86 12c0-4.48 3.66-8.14 8.14-8.14s8.14 3.66 8.14 8.14-3.66 8.14-8.14 8.14zm4.46-6.1c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.54.12-.16.24-.62.78-.76.94-.14.16-.28.18-.52.06-.24-.12-1.02-.37-1.94-1.2-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.36.1-.48.1-.1.24-.26.36-.38.12-.12.16-.2.24-.34.08-.14.04-.26-.02-.38-.06-.12-.54-1.3-.74-1.78-.2-.48-.4-.42-.54-.42h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2 0 1.18.86 2.32.98 2.48.12.16 1.7 2.6 4.12 3.64.58.26 1.04.42 1.4.54.58.18 1.1.16 1.52.1.46-.06 1.42-.58 1.62-1.14.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28z"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        ${imageViewerMarkup}
    `;

    document.title = `${item.title || 'Publicaci\u00f3n'} | Portal Noticias ABI`;
    setupImageViewer();
    setupShareToggle();
}

function buildShareLinks(item) {
    const detailUrl = buildShareUrlForItem(item);
    const title = item && item.title ? String(item.title).trim() : '';
    const whatsappText = title ? `${title}\n${detailUrl}` : detailUrl;

    return {
        whatsapp: `https://wa.me/?text=${encodeURIComponent(whatsappText)}`,
    };
}

function buildShareUrlForItem(item) {
    const id = item && (item.guid || item.link || item.title)
        ? String(item.guid || item.link || item.title).trim()
        : '';
    const currentUrl = getCurrentDetailUrl(item);

    if (!id) {
        return currentUrl;
    }

    try {
        const parsed = new URL(currentUrl, window.location.origin);
        parsed.searchParams.set('id', id);
        parsed.hash = '';
        return parsed.toString();
    } catch (error) {
        const separator = currentUrl.indexOf('?') === -1 ? '?' : '&';
        return currentUrl + separator + 'id=' + encodeURIComponent(id);
    }
}

function getCurrentDetailUrl(item) {
    const configuredShareUrl = detailShell && detailShell.dataset
        ? String(detailShell.dataset.shareUrl || '').trim()
        : '';

    if (configuredShareUrl) {
        return configuredShareUrl;
    }

    try {
        const currentUrl = new URL(window.location.href);
        currentUrl.hash = '';
        return currentUrl.toString();
    } catch (error) {
        return window.location.href;
    }
}

function setupImageViewer() {
    if (imageViewerKeyHandler) {
        document.removeEventListener('keydown', imageViewerKeyHandler);
        imageViewerKeyHandler = null;
    }

    document.body.classList.remove('viewer-open');

    const trigger = detailArticle.querySelector('[data-image-zoom-trigger]');
    const viewer = detailArticle.querySelector('[data-image-viewer]');
    const closeButton = detailArticle.querySelector('[data-image-viewer-close]');

    if (!trigger || !viewer || !closeButton) {
        return;
    }

    const closeViewer = function () {
        viewer.classList.add('is-hidden');
        viewer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('viewer-open');
    };

    const openViewer = function () {
        viewer.classList.remove('is-hidden');
        viewer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('viewer-open');
        closeButton.focus();
    };

    trigger.addEventListener('click', openViewer);
    closeButton.addEventListener('click', closeViewer);
    viewer.addEventListener('click', function (event) {
        if (event.target === viewer) {
            closeViewer();
        }
    });

    imageViewerKeyHandler = function (event) {
        if (event.key === 'Escape' && !viewer.classList.contains('is-hidden')) {
            closeViewer();
        }
    };

    document.addEventListener('keydown', imageViewerKeyHandler);
}

function setupShareToggle() {
    const shareToggle = detailArticle.querySelector('[data-share-toggle]');
    const sharePanel = detailArticle.querySelector('[data-share-panel]');
    const copyLinkButton = detailArticle.querySelector('[data-copy-link]');

    if (!shareToggle || !sharePanel) {
        return;
    }

    const setExpanded = function (expanded) {
        shareToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        sharePanel.classList.toggle('is-open', expanded);
    };

    setExpanded(false);

    shareToggle.addEventListener('click', function () {
        const expanded = shareToggle.getAttribute('aria-expanded') === 'true';
        setExpanded(!expanded);
    });

    if (!copyLinkButton) {
        return;
    }

    const defaultCopyLabel = 'Copiar vinculo';
    let copyResetTimer = null;

    const setCopyLabel = function (value, copied) {
        copyLinkButton.textContent = value;
        copyLinkButton.classList.toggle('is-copied', copied);
    };

    copyLinkButton.addEventListener('click', async function () {
        try {
            await copyShareLink(currentDetailItem);

            setCopyLabel('Vinculo copiado', true);
            clearTimeout(copyResetTimer);
            copyResetTimer = setTimeout(function () {
                setCopyLabel(defaultCopyLabel, false);
            }, 1600);
        } catch (error) {
            console.error('No se pudo copiar el vínculo:', error);
            setCopyLabel('No se pudo copiar', false);
            clearTimeout(copyResetTimer);
            copyResetTimer = setTimeout(function () {
                setCopyLabel(defaultCopyLabel, false);
            }, 1600);
        }
    });
}

async function copyTextToClipboard(value) {
    const text = String(value || '');

    if (!text) {
        throw new Error('Valor vacío');
    }

    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const helper = document.createElement('textarea');
    helper.value = text;
    helper.setAttribute('readonly', '');
    helper.style.position = 'fixed';
    helper.style.left = '-9999px';
    document.body.appendChild(helper);
    helper.focus();
    helper.select();

    const wasCopied = document.execCommand('copy');
    document.body.removeChild(helper);

    if (!wasCopied) {
        throw new Error('execCommand copy failed');
    }
}

async function copyShareLink(item) {
    const url = buildShareUrlForItem(item);
    const title = item && item.title ? String(item.title).trim() : url;
    const image = buildShareImageUrlForItem(item, url);

    if (navigator.clipboard && navigator.clipboard.write && window.ClipboardItem && window.isSecureContext) {
        const safeUrl = utils.escapeHtml(url);
        const safeTitle = utils.escapeHtml(title);
        const safeImage = utils.escapeHtml(image);
        const imageMarkup = safeImage
            ? `<img src="${safeImage}" alt="" style="display:block;width:100%;height:auto;max-height:260px;object-fit:cover;">`
            : '';
        const html = `<a href="${safeUrl}" style="display:block;max-width:520px;border:1px solid #ded6cc;border-radius:10px;overflow:hidden;color:#1d242c;text-decoration:none;font-family:Arial,sans-serif;background:#ffffff;">${imageMarkup}<span style="display:block;padding:14px 16px;font-size:18px;font-weight:700;line-height:1.35;">${safeTitle}</span><span style="display:block;padding:0 16px 14px;color:#5d6b78;font-size:13px;line-height:1.4;">${safeUrl}</span></a>`;

        try {
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/plain': new Blob([url], { type: 'text/plain' }),
                    'text/html': new Blob([html], { type: 'text/html' }),
                }),
            ]);
            return;
        } catch (error) {
        }
    }

    await copyTextToClipboard(url);
}

function normalizeShareImageUrl(url) {
    const value = String(url || '').trim();

    if (!value) {
        return '';
    }

    if (/^https?:\/\//i.test(value)) {
        return value;
    }

    if (value.indexOf('//') === 0) {
        return `${window.location.protocol}${value}`;
    }

    try {
        return new URL(value, window.location.origin).href;
    } catch (error) {
        return '';
    }
}

function buildShareImageUrlForItem(item, detailUrl) {
    if (!item || !item.image) {
        return '';
    }

    try {
        const parsed = new URL(detailUrl, window.location.origin);
        const id = item.guid || item.link || item.title || parsed.searchParams.get('id') || '';

        if (!id) {
            return normalizeShareImageUrl(item.image);
        }

        parsed.pathname = parsed.pathname.replace(/\/[^/]*$/, '/share-image.php') || '/share-image.php';
        parsed.search = '';
        parsed.searchParams.set('id', id);
        parsed.hash = '';

        return parsed.toString();
    } catch (error) {
        return normalizeShareImageUrl(item.image);
    }
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
    document.documentElement.classList.toggle('theme-dark', theme === 'dark');
    document.body.classList.toggle('theme-dark', theme === 'dark');
}

function setupBackToTop() {
    utils.setupBackToTop(backToTopButton);
}


