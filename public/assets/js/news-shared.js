window.NewsPortalUtils = (function () {
    function normalizeArticleText(text) {
        const cleaned = String(text || '')
            .replace(/\s+(?:(?:\/\/?[A-Z]{2,6}\/\/?)|(?:[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*))?\s*Navegaci\S*\s+de\s+entradas[\s\S]*$/iu, '')
            .replace(/\s*\.?\s*(?=[\/A-Za-z]*\/)(?:\/{0,3}[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*\/{0,3})\s*$/u, '');

        return normalizeReadableWhitespace(fixPastedTextBoundaries(cleaned));
    }

    function normalizeReadableWhitespace(text) {
        return String(text || '')
            .replace(/\r\n?/g, '\n')
            .replace(/[^\S\n]+/g, ' ')
            .replace(/ *\n */g, '\n')
            .replace(/\n{3,}/g, '\n\n')
            .split('\n')
            .map(function (line) {
                return line.trim();
            })
            .join('\n')
            .trim();
    }

    function fixPastedTextBoundaries(text) {
        return String(text || '')
            .replace(/(\p{Ll})((?:En|El|La|Los|Las|Un|Una|Durante|Asimismo|También|Segun|Según|Por|Para|Con|Sin|Este|Esta|Estos|Estas)\b)/gu, '$1. $2')
            .replace(/(\p{Ll})\s+((?:En|El|La|Los|Las|Un|Una|Durante|Asimismo|También|Segun|Según|Por|Para|Con|Sin|Este|Esta|Estos|Estas)\b)/gu, '$1. $2')
            .replace(/(\p{Ll})([\p{Lu}]{2,})(?=\s|$)/gu, '$1. $2')
            .replace(/([.!?])(\p{Lu})/gu, '$1 $2');
    }

    function buildReadableParagraphs(text) {
        const normalized = normalizeArticleText(text);

        if (!normalized) {
            return [];
        }

        const paragraphBlocks = normalized
            .split(/\n{2,}/)
            .map(function (block) {
                return block.replace(/\s+/g, ' ').trim();
            })
            .filter(Boolean);

        const sourceBlocks = paragraphBlocks.length > 0
            ? paragraphBlocks
            : [normalized.replace(/\s+/g, ' ').trim()];

        const paragraphs = [];

        sourceBlocks.forEach(function (block) {
            if (block.length <= 280) {
                paragraphs.push(block);
                return;
            }

            const sentences = block.match(/[^.!?]+(?:[.!?]+|$)/g) || [block];
            let currentParagraph = '';
            let sentenceCounter = 0;

            sentences.forEach(function (sentence) {
                const cleanSentence = sentence.replace(/\s+/g, ' ').trim();

                if (!cleanSentence) {
                    return;
                }

                if (currentParagraph && isLikelySubheading(currentParagraph)) {
                    paragraphs.push(currentParagraph.trim());
                    currentParagraph = cleanSentence;
                    sentenceCounter = 1;
                    return;
                }

                const candidate = currentParagraph
                    ? `${currentParagraph} ${cleanSentence}`
                    : cleanSentence;
                const longEnough = candidate.length >= 260;
                const tooLong = candidate.length >= 420;
                const enoughSentences = sentenceCounter >= 2;

                if (currentParagraph && (tooLong || (longEnough && enoughSentences))) {
                    paragraphs.push(currentParagraph.trim());
                    currentParagraph = cleanSentence;
                    sentenceCounter = 1;
                    return;
                }

                currentParagraph = candidate;
                sentenceCounter += 1;
            });

            if (currentParagraph.trim()) {
                paragraphs.push(currentParagraph.trim());
            }
        });

        return paragraphs.filter(Boolean);
    }

    function isLikelySubheading(text) {
        const clean = String(text || '').replace(/[.!?]+$/g, '').trim();

        if (clean.length < 24 || clean.length > 120) {
            return false;
        }

        if (/[,:;"“”¿?]/.test(clean)) {
            return false;
        }

        return clean.split(/\s+/).length <= 12;
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
            hour12: false,
        } : {})).format(date);
    }

    function setupBackToTop(button) {
        if (!button) {
            return;
        }

        const footer = document.querySelector('.site-footer');

        const toggleVisibility = function () {
            button.classList.toggle('is-visible', window.scrollY > 500);
            updateBackToTopFooterOffset(button, footer);
        };

        button.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth',
            });
        });

        window.addEventListener('scroll', toggleVisibility, { passive: true });
        window.addEventListener('resize', toggleVisibility);
        toggleVisibility();
    }

    function updateBackToTopFooterOffset(button, footer) {
        if (!button || !footer) {
            return;
        }

        const footerRect = footer.getBoundingClientRect();
        const visibleFooterHeight = Math.max(0, window.innerHeight - footerRect.top);

        button.style.setProperty('--back-to-top-footer-offset', `${Math.ceil(visibleFooterHeight)}px`);
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

    function buildImageProxyCandidates(url) {
        const source = String(url || '').trim();

        if (!source) {
            return [];
        }

        const encoded = encodeURIComponent(source);

        return uniqueImageCandidates([
            '/api/image.php?url=' + encoded,
            '../api/image.php?url=' + encoded,
            'api/image.php?url=' + encoded,
        ]);
    }

    function buildImageSourceCandidates(url) {
        const source = String(url || '').trim();

        if (!source) {
            return [];
        }

        return uniqueImageCandidates(buildImageProxyCandidates(source).concat([source]));
    }

    function buildImageDisplayUrl(url) {
        const candidates = buildImageSourceCandidates(url);

        return candidates.length > 0 ? candidates[0] : '';
    }

    function uniqueImageCandidates(candidates) {
        const seen = new Set();

        return candidates.filter(function (candidate) {
            const value = String(candidate || '').trim();

            if (!value) {
                return false;
            }

            let key = value;

            try {
                key = new URL(value, window.location.href).href;
            } catch (error) {
            }

            if (seen.has(key)) {
                return false;
            }

            seen.add(key);
            return true;
        });
    }

    function handleImageLoadError(image) {
        if (!(image instanceof HTMLImageElement)) {
            return;
        }

        const rawCandidates = String(image.dataset.proxyCandidates || '').trim();
        const candidates = rawCandidates
            ? rawCandidates.split('|').map(function (item) {
                return item.trim();
            }).filter(Boolean)
            : [];
        const currentIndex = Number.parseInt(String(image.dataset.proxyIndex || '-1'), 10);
        const nextIndex = Number.isFinite(currentIndex) ? currentIndex + 1 : 0;

        if (nextIndex < candidates.length) {
            image.dataset.proxyIndex = String(nextIndex);
            image.src = candidates[nextIndex] + (candidates[nextIndex].indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
            return;
        }

        image.onerror = null;
        image.removeAttribute('data-proxy-candidates');
        image.removeAttribute('data-proxy-index');
        image.src = './assets/img/logo.png';
    }

    return {
        normalizeArticleText,
        buildReadableParagraphs,
        formatDate,
        setupBackToTop,
        escapeHtml,
        escapeAttribute,
        buildImageProxyCandidates,
        buildImageSourceCandidates,
        buildImageDisplayUrl,
        handleImageLoadError,
    };
})();
