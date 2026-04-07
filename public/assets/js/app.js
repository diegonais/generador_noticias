const shell = document.querySelector('.site-shell');
const newsList = document.querySelector('#news-list');
const loadingState = document.querySelector('#loading-state');
const errorState = document.querySelector('#error-state');
const emptyState = document.querySelector('#empty-state');
const emptyStateMessage = document.querySelector('#empty-state-message');
const lastUpdated = document.querySelector('#last-updated');
const backToTopButton = document.querySelector('#back-to-top');
const calendarGrid = document.querySelector('#calendar-grid');
const calendarPrev = document.querySelector('#calendar-prev');
const calendarNext = document.querySelector('#calendar-next');
const calendarCurrentMonth = document.querySelector('#calendar-current-month');
const calendarPicker = document.querySelector('#calendar-month-year-picker');
const calendarYearSelect = document.querySelector('#calendar-year-select');
const calendarMonthSelect = document.querySelector('#calendar-month-select');
const filterToday = document.querySelector('#filter-today');
const filterToggle = document.querySelector('#filter-toggle');
const filtersPanel = document.querySelector('#filters-panel');
const themeToggle = document.querySelector('#theme-toggle');

const AUTO_REFRESH_INTERVAL_MS = 5 * 60 * 1000;
const utils = window.NewsPortalUtils;
const timezone = shell && shell.dataset.timezone ? shell.dataset.timezone : 'America/La_Paz';

const endpointCandidates = Array.from(new Set([
    shell ? shell.dataset.apiEndpoint : null,
    '../api/news.php',
    '/api/news.php',
    'api/news.php',
].filter(Boolean)));

let allNews = [];
let hasInitializedDefaultFilter = false;
let selectedFilterDate = null;
let calendarView = null;
let availableNewsDates = new Set();

const DEFAULT_EMPTY_MESSAGE = 'Cuando el sistema vuelva a actualizarse, las publicaciones apareceran aqui.';
const THEME_STORAGE_KEY = 'portal_theme';
const MONTH_LABELS = {
    1: 'Enero',
    2: 'Febrero',
    3: 'Marzo',
    4: 'Abril',
    5: 'Mayo',
    6: 'Junio',
    7: 'Julio',
    8: 'Agosto',
    9: 'Septiembre',
    10: 'Octubre',
    11: 'Noviembre',
    12: 'Diciembre',
};

initializeTheme();

document.addEventListener('DOMContentLoaded', function () {
    setupThemeToggle();
    setupBackToTop();
    setupFilters();
    setupFilterToggle();
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

        allNews = news;
        rebuildAvailableNewsDates();
        updateLastUpdated(payload && payload.updated_at ? payload.updated_at : null);
        applyDefaultFilterIfNeeded();
        refreshFilterOptions();
        applyFiltersAndRender();
    } catch (error) {
        console.error('Error loading news:', error);
        setState('error');
    }
}

async function fetchFromAvailableEndpoint() {
    let lastError = new Error('No se pudo obtener la API.');

    for (const endpoint of endpointCandidates) {
        try {
            const endpointWithAllNews = appendAllNewsLimit(endpoint);
            const response = await fetch(withCacheBuster(endpointWithAllNews), {
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


function setupThemeToggle() {
    if (!themeToggle) {
        return;
    }

    themeToggle.addEventListener('click', function () {
        const currentTheme = document.body.classList.contains('theme-dark') ? 'dark' : 'light';
        const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

        applyTheme(nextTheme);

        try {
            localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
        } catch (error) {
        }
    });
}

function initializeTheme() {
    const storedTheme = readStoredTheme();

    if (storedTheme === 'light' || storedTheme === 'dark') {
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
    const isDark = theme === 'dark';

    document.body.classList.toggle('theme-dark', isDark);

    if (!themeToggle) {
        return;
    }

    themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    themeToggle.setAttribute('aria-label', isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
    themeToggle.textContent = isDark ? '\u2600' : '\u263D';
}
function setupFilters() {
    setupCalendarNavigation();
    setupMonthYearPicker();
    refreshFilterOptions();

    if (filterToday) {
        filterToday.addEventListener('click', function () {
            applyTodayFilter();
            applyFiltersAndRender();
            renderCalendar();
        });
    }
}

function setupFilterToggle() {
    if (!filterToggle || !filtersPanel) {
        return;
    }

    const setExpanded = function (expanded) {
        filtersPanel.classList.toggle('filters-panel--collapsed', !expanded);
        filtersPanel.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        filterToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        filterToggle.textContent = expanded ? 'Ocultar busqueda' : 'Buscar por fecha';

        if (!expanded) {
            toggleMonthYearPicker(false);
        }
    };

    setExpanded(!filtersPanel.classList.contains('filters-panel--collapsed'));

    filterToggle.addEventListener('click', function () {
        const expanded = filterToggle.getAttribute('aria-expanded') === 'true';
        setExpanded(!expanded);
    });
}
function setupMonthYearPicker() {
    if (!calendarCurrentMonth || !calendarPicker || !calendarYearSelect || !calendarMonthSelect) {
        return;
    }

    calendarCurrentMonth.addEventListener('click', function (event) {
        event.stopPropagation();
        toggleMonthYearPicker();
    });

    calendarYearSelect.addEventListener('change', function () {
        const selectedYear = parseInteger(calendarYearSelect.value);

        if (selectedYear === null) {
            return;
        }

        populateMonthPickerOptions(selectedYear);
        applyMonthYearPickerSelection(false);
    });

    calendarMonthSelect.addEventListener('change', function () {
        applyMonthYearPickerSelection(true);
    });

    document.addEventListener('click', function (event) {
        if (calendarPicker.classList.contains('is-hidden')) {
            return;
        }

        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const insidePicker = target.closest('#calendar-month-year-picker');
        const insideTrigger = target.closest('#calendar-current-month');

        if (!insidePicker && !insideTrigger) {
            toggleMonthYearPicker(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            toggleMonthYearPicker(false);
        }
    });
}

function toggleMonthYearPicker(forceOpen) {
    if (!calendarCurrentMonth || !calendarPicker) {
        return;
    }

    const shouldOpen = typeof forceOpen === 'boolean'
        ? forceOpen
        : calendarPicker.classList.contains('is-hidden');

    calendarPicker.classList.toggle('is-hidden', !shouldOpen);
    calendarPicker.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
    calendarCurrentMonth.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

    if (shouldOpen) {
        syncMonthYearPickerState();
    }
}

function applyMonthYearPickerSelection(closePicker) {
    const year = parseInteger(calendarYearSelect ? calendarYearSelect.value : '');
    const month = parseInteger(calendarMonthSelect ? calendarMonthSelect.value : '');

    if (year === null || month === null) {
        return;
    }

    calendarView = clampCalendarView({ year: year, month: month });
    renderCalendar();

    if (closePicker) {
        toggleMonthYearPicker(false);
    }
}

function syncMonthYearPickerState() {
    if (!calendarYearSelect || !calendarMonthSelect || !calendarView) {
        return;
    }

    const minView = getMinimumCalendarView();
    const maxView = getMaximumCalendarView();
    const yearOptions = [];

    for (let year = maxView.year; year >= minView.year; year -= 1) {
        yearOptions.push({ value: String(year), label: String(year) });
    }

    setSimpleSelectOptions(calendarYearSelect, yearOptions, String(calendarView.year));
    populateMonthPickerOptions(calendarView.year);
    calendarMonthSelect.value = String(calendarView.month);
}

function populateMonthPickerOptions(selectedYear) {
    if (!calendarMonthSelect) {
        return;
    }

    const minView = getMinimumCalendarView();
    const maxView = getMaximumCalendarView();
    let minMonth = 1;
    let maxMonth = 12;

    if (selectedYear === minView.year) {
        minMonth = minView.month;
    }

    if (selectedYear === maxView.year) {
        maxMonth = maxView.month;
    }

    const monthOptions = [];

    for (let month = minMonth; month <= maxMonth; month += 1) {
        monthOptions.push({ value: String(month), label: MONTH_LABELS[month] || String(month) });
    }

    const preferredMonth = calendarView && calendarView.year === selectedYear ? calendarView.month : maxMonth;
    setSimpleSelectOptions(calendarMonthSelect, monthOptions, String(preferredMonth));
}

function setSimpleSelectOptions(selectElement, options, selectedValue) {
    if (!selectElement) {
        return;
    }

    selectElement.innerHTML = '';

    options.forEach(function (optionData) {
        const option = document.createElement('option');
        option.value = optionData.value;
        option.textContent = optionData.label;
        selectElement.appendChild(option);
    });

    if (options.length === 0) {
        selectElement.disabled = true;
        return;
    }

    selectElement.disabled = false;

    const hasSelectedValue = options.some(function (optionData) {
        return optionData.value === selectedValue;
    });

    selectElement.value = hasSelectedValue ? selectedValue : options[0].value;
}
function setupCalendarNavigation() {
    if (calendarPrev) {
        calendarPrev.addEventListener('click', function () {
            shiftCalendarView(-1);
        });
    }

    if (calendarNext) {
        calendarNext.addEventListener('click', function () {
            shiftCalendarView(1);
        });
    }
}

function shiftCalendarView(step) {
    if (!calendarView || !Number.isFinite(step) || step === 0) {
        return;
    }

    const targetMonth = new Date(calendarView.year, calendarView.month - 1 + step, 1);
    const nextView = clampCalendarView({
        year: targetMonth.getFullYear(),
        month: targetMonth.getMonth() + 1,
    });

    if (compareCalendarView(nextView, calendarView) === 0) {
        return;
    }

    calendarView = nextView;
    renderCalendar();
}

function refreshFilterOptions() {
    ensureCalendarView();
    renderCalendar();
}

function ensureCalendarView() {
    const selectedParts = getFilterSelection();

    if (selectedParts.year !== null && selectedParts.month !== null) {
        calendarView = clampCalendarView({
            year: selectedParts.year,
            month: selectedParts.month,
        });
        return;
    }

    if (!calendarView) {
        const initial = getInitialCalendarViewParts();
        calendarView = {
            year: initial.year,
            month: initial.month,
        };
    }

    calendarView = clampCalendarView(calendarView);
}

function getInitialCalendarViewParts() {
    const today = getTodayParts();

    if (today) {
        return today;
    }

    const latestDate = getLatestAvailableDateParts();

    if (latestDate) {
        return latestDate;
    }

    const fallback = new Date();

    return {
        year: fallback.getFullYear(),
        month: fallback.getMonth() + 1,
        day: fallback.getDate(),
    };
}

function renderCalendar() {
    if (!calendarGrid || !calendarCurrentMonth || !calendarView) {
        return;
    }

    const year = calendarView.year;
    const month = calendarView.month;
    const daysInMonth = new Date(year, month, 0).getDate();
    const firstDayOffset = getCalendarOffset(year, month);
    const cells = [];

    calendarCurrentMonth.textContent = `${MONTH_LABELS[month] || month} ${year}`;

    for (let index = 0; index < firstDayOffset; index += 1) {
        cells.push('<span class="calendar-filter__cell calendar-filter__cell--empty" aria-hidden="true"></span>');
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const parts = { year: year, month: month, day: day };
        const dateKey = toDateKey(parts);
        const hasNews = availableNewsDates.has(dateKey);
        const selected = selectedFilterDate === dateKey;
        const isToday = isTodayDateKey(dateKey);
        const future = isFutureDateParts(parts);
        const classNames = ['calendar-filter__day'];

        if (hasNews) {
            classNames.push('calendar-filter__day--has-news');
        }

        if (selected) {
            classNames.push('calendar-filter__day--selected');
        }

        if (isToday) {
            classNames.push('calendar-filter__day--today');
        }

        if (future) {
            classNames.push('calendar-filter__day--future');
        }

        const disabledAttribute = future ? ' disabled aria-disabled="true"' : '';

        cells.push(
            `<button class="${classNames.join(' ')}" type="button" data-date="${dateKey}"${disabledAttribute}>${day}</button>`
        );
    }

    while (cells.length % 7 !== 0) {
        cells.push('<span class="calendar-filter__cell calendar-filter__cell--empty" aria-hidden="true"></span>');
    }

    calendarGrid.innerHTML = cells.join('');

    Array.from(calendarGrid.querySelectorAll('.calendar-filter__day')).forEach(function (button) {
        if (button.disabled) {
            return;
        }

        button.addEventListener('click', function () {
            const dateKey = button.dataset.date || '';
            const parts = parseDateKey(dateKey);

            if (!parts) {
                return;
            }

            setFilterSelection(parts);
            applyFiltersAndRender();
            renderCalendar();
        });
    });

    updateCalendarNavigationState();
    syncMonthYearPickerState();
}

function updateCalendarNavigationState() {
    if (!calendarPrev || !calendarNext || !calendarView) {
        return;
    }

    const minView = getMinimumCalendarView();
    const maxView = getMaximumCalendarView();
    const prevMonthDate = new Date(calendarView.year, calendarView.month - 2, 1);
    const nextMonthDate = new Date(calendarView.year, calendarView.month, 1);
    const prevMonthView = {
        year: prevMonthDate.getFullYear(),
        month: prevMonthDate.getMonth() + 1,
    };
    const nextMonthView = {
        year: nextMonthDate.getFullYear(),
        month: nextMonthDate.getMonth() + 1,
    };

    calendarPrev.disabled = compareCalendarView(prevMonthView, minView) < 0;
    calendarNext.disabled = compareCalendarView(nextMonthView, maxView) > 0;
}

function getMinimumCalendarView() {
    const earliest = getEarliestAvailableDateParts();

    if (earliest) {
        return {
            year: earliest.year,
            month: earliest.month,
        };
    }

    const today = getTodayParts();

    if (today) {
        return {
            year: today.year,
            month: today.month,
        };
    }

    const fallback = new Date();

    return {
        year: fallback.getFullYear(),
        month: fallback.getMonth() + 1,
    };
}

function getMaximumCalendarView() {
    const today = getTodayParts();

    if (today) {
        return {
            year: today.year,
            month: today.month,
        };
    }

    const fallback = new Date();

    return {
        year: fallback.getFullYear(),
        month: fallback.getMonth() + 1,
    };
}

function clampCalendarView(view) {
    if (!view) {
        return getMaximumCalendarView();
    }

    const minView = getMinimumCalendarView();
    const maxView = getMaximumCalendarView();

    if (compareCalendarView(view, minView) < 0) {
        return minView;
    }

    if (compareCalendarView(view, maxView) > 0) {
        return maxView;
    }

    return {
        year: view.year,
        month: view.month,
    };
}

function compareCalendarView(left, right) {
    return (left.year * 100 + left.month) - (right.year * 100 + right.month);
}
function getCalendarOffset(year, month) {
    const nativeDay = new Date(year, month - 1, 1).getDay();

    return (nativeDay + 6) % 7;
}

function applyTodayFilter() {
    const today = getTodayParts();

    if (!today) {
        return;
    }

    setFilterSelection(today);
}

function clearFilters() {
    selectedFilterDate = null;
}

function rebuildAvailableNewsDates() {
    availableNewsDates = new Set();

    allNews.forEach(function (item) {
        const parts = getDatePartsFromItem(item);

        if (!parts) {
            return;
        }

        availableNewsDates.add(toDateKey(parts));
    });
}

function applyDefaultFilterIfNeeded() {
    if (hasInitializedDefaultFilter && hasAnyFilterSelection()) {
        return;
    }

    hasInitializedDefaultFilter = true;

    const today = getTodayParts();

    if (today) {
        setFilterSelection(today);
        return;
    }

    const latestAvailableDate = getLatestAvailableDateParts();

    if (latestAvailableDate) {
        setFilterSelection(latestAvailableDate);
        return;
    }

    clearFilters();
}

function hasAnyFilterSelection() {
    return selectedFilterDate !== null;
}

function setFilterSelection(parts) {
    if (!parts) {
        return;
    }

    selectedFilterDate = toDateKey(parts);
    calendarView = {
        year: parts.year,
        month: parts.month,
    };
}

function hasNewsForDate(parts) {
    if (!parts) {
        return false;
    }

    return availableNewsDates.has(toDateKey(parts));
}

function getEarliestAvailableDateParts() {
    let earliestDate = null;
    let earliestDateKey = Number.POSITIVE_INFINITY;

    allNews.forEach(function (item) {
        const parts = getDatePartsFromItem(item);

        if (!parts) {
            return;
        }

        const dateKey = getNumericDateKey(parts);

        if (dateKey < earliestDateKey) {
            earliestDateKey = dateKey;
            earliestDate = parts;
        }
    });

    return earliestDate;
}
function getLatestAvailableDateParts() {
    let latestDate = null;
    let latestDateKey = 0;

    allNews.forEach(function (item) {
        const parts = getDatePartsFromItem(item);

        if (!parts) {
            return;
        }

        const dateKey = getNumericDateKey(parts);

        if (dateKey > latestDateKey) {
            latestDateKey = dateKey;
            latestDate = parts;
        }
    });

    return latestDate;
}

function applyFiltersAndRender() {
    if (allNews.length === 0) {
        setEmptyMessage(buildNoResultsMessage(getFilterSelection()));
        setState('empty');
        return;
    }

    const filter = getFilterSelection();
    const filteredNews = allNews.filter(function (item) {
        return matchesDateFilter(item, filter);
    });

    if (filteredNews.length === 0) {
        setEmptyMessage(buildNoResultsMessage(filter));
        setState('empty');
        return;
    }

    renderNews(filteredNews);
    setState('success');
}

function matchesDateFilter(item, filter) {
    const parts = getDatePartsFromItem(item);

    if (!parts) {
        return false;
    }

    if (filter.year === null || filter.month === null || filter.day === null) {
        return true;
    }

    return parts.year === filter.year && parts.month === filter.month && parts.day === filter.day;
}

function getFilterSelection() {
    const parts = parseDateKey(selectedFilterDate);

    if (!parts) {
        return {
            year: null,
            month: null,
            day: null,
        };
    }

    return parts;
}

function getTodayParts() {
    return getDatePartsByTimezone(new Date());
}

function isFutureFilterSelection(filter) {
    const today = getTodayParts();

    if (!today || filter.year === null || filter.month === null || filter.day === null) {
        return false;
    }

    return getNumericDateKey(filter) > getNumericDateKey(today);
}

function getNumericDateKey(parts) {
    return (parts.year * 10000) + (parts.month * 100) + parts.day;
}

function toDateKey(parts) {
    const year = String(parts.year).padStart(4, '0');
    const month = String(parts.month).padStart(2, '0');
    const day = String(parts.day).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function parseDateKey(value) {
    if (!value) {
        return null;
    }

    const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) {
        return null;
    }

    return {
        year: Number.parseInt(match[1], 10),
        month: Number.parseInt(match[2], 10),
        day: Number.parseInt(match[3], 10),
    };
}

function isFutureDateParts(parts) {
    const today = getTodayParts();

    if (!today) {
        return false;
    }

    return getNumericDateKey(parts) > getNumericDateKey(today);
}

function isFutureMonth(view) {
    const today = getTodayParts();

    if (!today || !view) {
        return false;
    }

    return (view.year * 100 + view.month) > (today.year * 100 + today.month);
}

function isTodayDateKey(dateKey) {
    const today = getTodayParts();

    if (!today) {
        return false;
    }

    return toDateKey(today) === dateKey;
}
function parseInteger(value) {
    const parsed = Number.parseInt(String(value || '').trim(), 10);

    return Number.isFinite(parsed) ? parsed : null;
}

function getDatePartsFromItem(item) {
    if (!item || !item.published_at) {
        return null;
    }

    const date = new Date(item.published_at);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return getDatePartsByTimezone(date);
}

function getDatePartsByTimezone(date) {
    try {
        const formatter = new Intl.DateTimeFormat('en-CA', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        });

        const parts = formatter.formatToParts(date);
        const partMap = {};

        parts.forEach(function (part) {
            if (part.type === 'year' || part.type === 'month' || part.type === 'day') {
                partMap[part.type] = part.value;
            }
        });

        return {
            year: Number.parseInt(partMap.year, 10),
            month: Number.parseInt(partMap.month, 10),
            day: Number.parseInt(partMap.day, 10),
        };
    } catch (error) {
        return {
            year: date.getFullYear(),
            month: date.getMonth() + 1,
            day: date.getDate(),
        };
    }
}

function buildNoResultsMessage(filter) {
    if (isFutureFilterSelection(filter)) {
        return 'No se pueden buscar noticias en fechas futuras.';
    }

    if (filter.year !== null && filter.month !== null && filter.day !== null) {
        const day = String(filter.day).padStart(2, '0');
        const month = String(filter.month).padStart(2, '0');

        return `No hay noticias para la fecha ${day}/${month}/${filter.year}.`;
    }

    if (filter.year !== null || filter.month !== null || filter.day !== null) {
        return 'No hay noticias que coincidan con los filtros seleccionados.';
    }

    return DEFAULT_EMPTY_MESSAGE;
}

function setEmptyMessage(message) {
    if (!emptyStateMessage) {
        return;
    }

    emptyStateMessage.textContent = message;
}

function renderNews(news) {
    const cards = news.map(createCardMarkup).join('');
    newsList.innerHTML = cards;
}

function createCardMarkup(item) {
    const imageMarkup = item.image
        ? `<img class="news-card__image" src="${utils.escapeAttribute(item.image)}" alt="${utils.escapeAttribute(item.title)}" loading="lazy">`
        : `<div class="news-card__image news-card__image--placeholder" aria-hidden="true">Sin imagen</div>`;

    const detailUrl = buildDetailUrl(item);
    const shortSummary = truncateText(utils.normalizeArticleText(item.summary || 'Sin resumen disponible.'), 280);

    return `
        <article class="news-card">
            <div class="news-card__media">
                ${imageMarkup}
            </div>
            <div class="news-card__body">
                <div class="news-card__meta">
                    <span>${utils.escapeHtml(item.source || 'ABI')}</span>
                    <span>${utils.formatDate(item.published_at)}</span>
                </div>
                <h3 class="news-card__title">${utils.escapeHtml(item.title || 'Sin titulo')}</h3>
                <p class="news-card__summary">${utils.escapeHtml(shortSummary)}</p>
                <a class="news-card__link" href="${utils.escapeAttribute(detailUrl)}">
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

    lastUpdated.textContent = `Actualizado: ${utils.formatDate(dateString, true)}`;
}

function appendAllNewsLimit(endpoint) {
    const separator = endpoint.indexOf('?') === -1 ? '?' : '&';

    return endpoint + separator + 'limit=0';
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
    utils.setupBackToTop(backToTopButton);
}

