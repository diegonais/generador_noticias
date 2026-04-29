window.NewsPortalUtils = (function () {
    function normalizeArticleText(text) {
        return String(text || '')
            .replace(/\s+(?:(?:\/\/?[A-Z]{2,6}\/\/?)|(?:[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*))?\s*Navegaci\S*\s+de\s+entradas[\s\S]*$/iu, '')
            .replace(/\s*\.?\s*(?=[\/A-Za-z]*\/)(?:\/{0,3}[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*\/{0,3})\s*$/u, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function buildReadableParagraphs(text) {
        const normalized = normalizeArticleText(text)
            .replace(/\r\n?/g, '\n')
            .trim();

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

        const toggleVisibility = function () {
            button.classList.toggle('is-visible', window.scrollY > 500);
        };

        button.addEventListener('click', function () {
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

    return {
        normalizeArticleText,
        buildReadableParagraphs,
        formatDate,
        setupBackToTop,
        escapeHtml,
        escapeAttribute,
    };
})();
