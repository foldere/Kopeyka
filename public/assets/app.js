'use strict';

/* Интерфейс «Копейки»: обычный JavaScript без сборки и фреймворков.
   Работает поверх REST API (/api/...). Все данные из API выводятся через textContent
   (функция el), а не через innerHTML: так «заметка» с HTML-кодом не сможет ничего сломать (XSS). */

const CURRENCIES = { RUB: '₽', USD: '$', EUR: '€', CNY: '¥', GBP: '£', KZT: '₸' };
const PER_PAGE = 15;

// ---------- вспомогательные функции ----------

const $ = (selector, root = document) => root.querySelector(selector);

/** Создаёт DOM-элемент: el('div', {class: 'x', onclick: fn}, 'текст', дочерний_элемент) */
function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
        if (value === null || value === undefined || value === false) continue;
        if (key === 'class') node.className = value;
        else if (key === 'text') node.textContent = value;
        else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
        else node.setAttribute(key, value === true ? '' : value);
    }
    for (const child of children.flat()) {
        if (child === null || child === undefined || child === false) continue;
        node.append(child);
    }
    return node;
}

const pad = (n) => String(n).padStart(2, '0');
const isoDate = (d = new Date()) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const fmtDate = (iso) => iso.split('-').reverse().join('.');
const monthLabel = (ym) => {
    const [y, m] = ym.split('-');
    return new Date(Number(y), Number(m) - 1, 1).toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' }).replace(/\s*г\.$/, '');
};

/** «1250.5» → «1 250,50 ₽». Работает со строкой, без float: так не теряются копейки. */
function money(value, currency = 'RUB') {
    const negative = value.startsWith('-');
    const [whole, fraction = '00'] = value.replace('-', '').split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');

    return `${negative ? '−' : ''}${grouped},${fraction.padEnd(2, '0')}\u00a0${CURRENCIES[currency] ?? currency}`;
}

/** «95.000000» → «95,00» */
function rate(value) {
    const [whole, fraction = ''] = value.split('.');
    const trimmed = fraction.replace(/0+$/, '').padEnd(2, '0');

    return `${whole},${trimmed}`;
}

// ---------- работа с API ----------

class ApiError extends Error {
    constructor(status, error) {
        super(error?.message ?? `Ошибка ${status}`);
        this.status = status;
        this.details = error?.details ?? {};
    }
}

async function api(path, { method = 'GET', body, query } = {}) {
    const url = new URL(path, window.location.origin);
    for (const [key, value] of Object.entries(query ?? {})) {
        if (value !== '' && value !== null && value !== undefined) url.searchParams.set(key, value);
    }

    let response;
    try {
        response = await fetch(url, {
            method,
            headers: body ? { 'Content-Type': 'application/json' } : {},
            body: body ? JSON.stringify(body) : undefined,
        });
    } catch {
        throw new Error('Нет связи с сервером. Проверьте, что контейнеры запущены.');
    }

    if (response.status === 204) return null;

    let payload = null;
    try { payload = await response.json(); } catch { /* тело не JSON */ }
    if (!response.ok) throw new ApiError(response.status, payload?.error);

    return payload;
}

// ---------- состояние ----------

const state = {
    categories: [],
    filters: { from: '', to: '', category_id: '', type: '' },
    page: 1,
    report: { from: isoDate(new Date(new Date().getFullYear(), 0, 1)), to: isoDate(), type: 'expense' },
    ratesDate: isoDate(),
};

const categoryName = (id) => state.categories.find((c) => c.id === id)?.name ?? '—';
const app = $('#app');

let toastTimer;
function toast(message, kind = 'ok') {
    const node = $('#toast');
    node.textContent = message;
    node.className = `toast toast--${kind} is-visible`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => node.classList.remove('is-visible'), 3500);
}

function errorBox(error) {
    return el('div', { class: 'notice', role: 'alert' },
        el('strong', { text: 'Не удалось загрузить данные' }),
        el('p', { text: error.message }),
        el('button', { class: 'btn btn--small', type: 'button', onclick: () => render(), text: 'Повторить' }),
    );
}

const pageHead = (title, ...actions) => el('div', { class: 'pagehead' }, el('h1', { text: title }), ...actions);

// ---------- раздел «Операции» ----------

async function viewTransactions() {
    const now = new Date();
    const monthStart = isoDate(new Date(now.getFullYear(), now.getMonth(), 1));

    const [list, summary] = await Promise.all([
        api('/api/transactions', { query: { ...state.filters, page: state.page, per_page: PER_PAGE } }),
        api('/api/reports/monthly', { query: { from: monthStart, to: isoDate(now) } }),
    ]);

    const month = summary.data[0] ?? { income: '0.00', expense: '0.00', balance: '0.00' };
    const cards = el('section', { class: 'cards', 'aria-label': 'Итоги текущего месяца' },
        card('Доходы за месяц', money(month.income), 'is-income'),
        card('Расходы за месяц', money(month.expense), 'is-expense'),
        card('Баланс', money(month.balance), month.balance.startsWith('-') ? 'is-expense' : ''),
    );

    return el('div', {},
        pageHead('Операции', el('button', { class: 'btn', type: 'button', onclick: () => openDialog(), text: '+ Добавить операцию' })),
        cards,
        filtersForm(),
        list.data.length === 0 ? emptyState() : ledger(list.data),
        pager(list.meta),
    );
}

const card = (label, value, cls) => el('div', { class: 'card' },
    el('div', { class: 'card__label', text: label }),
    el('div', { class: `card__value ${cls}`, text: value }),
);

function emptyState() {
    const filtered = Object.values(state.filters).some(Boolean);

    return el('div', { class: 'empty' },
        el('p', { text: filtered ? 'По этим условиям операций нет.' : 'Операций пока нет.' }),
        el('button', { class: 'btn', type: 'button', onclick: () => openDialog(), text: '+ Добавить операцию' }),
    );
}

function filtersForm() {
    const f = state.filters;
    const form = el('form', { class: 'filters', 'aria-label': 'Фильтры' },
        el('label', { class: 'field' }, 'С', el('input', { type: 'date', name: 'from', value: f.from })),
        el('label', { class: 'field' }, 'По', el('input', { type: 'date', name: 'to', value: f.to })),
        el('label', { class: 'field' }, 'Категория',
            el('select', { name: 'category_id' },
                el('option', { value: '', text: 'Все' }),
                state.categories.map((c) => el('option', { value: c.id, text: c.name, selected: String(c.id) === f.category_id })),
            )),
        el('label', { class: 'field' }, 'Тип',
            el('select', { name: 'type' },
                el('option', { value: '', text: 'Все' }),
                el('option', { value: 'expense', text: 'Расходы', selected: f.type === 'expense' }),
                el('option', { value: 'income', text: 'Доходы', selected: f.type === 'income' }),
            )),
        el('button', { class: 'btn btn--small', type: 'submit', text: 'Показать' }),
        el('button', { class: 'btn btn--small btn--ghost', type: 'button', text: 'Сбросить', onclick: () => {
            state.filters = { from: '', to: '', category_id: '', type: '' };
            state.page = 1;
            render();
        } }),
    );

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const data = new FormData(form);
        const next = { from: data.get('from'), to: data.get('to'), category_id: data.get('category_id'), type: data.get('type') };
        if (next.from && next.to && next.to < next.from) {
            toast('Конечная дата раньше начальной.', 'error');
            return;
        }
        state.filters = next;
        state.page = 1;
        render();
    });

    return form;
}

function ledger(items) {
    return el('ul', { class: 'ledger' }, items.map((t) => {
        const income = t.type === 'income';

        return el('li', { class: 'tx' },
            el('time', { class: 'tx__date', datetime: t.occurred_on, text: fmtDate(t.occurred_on) }),
            el('div', { class: 'tx__main' },
                el('span', { class: 'tx__cat', text: categoryName(t.category_id) }),
                t.note && el('span', { class: 'tx__note', text: t.note }),
            ),
            el('div', { class: `tx__amount ${income ? 'is-income' : 'is-expense'}` },
                el('span', { text: `${income ? '+' : '−'}${money(t.amount, t.currency)}` }),
                t.currency !== 'RUB' && el('small', { text: `≈ ${money(t.amount_rub)} по курсу ${rate(t.rate)}` }),
            ),
            el('div', { class: 'tx__actions' },
                el('button', { class: 'icon', type: 'button', title: 'Изменить', 'aria-label': 'Изменить операцию', text: '✎', onclick: () => openDialog(t) }),
                el('button', { class: 'icon', type: 'button', title: 'Удалить', 'aria-label': 'Удалить операцию', text: '✕', onclick: () => removeTransaction(t) }),
            ),
        );
    }));
}

function pager(meta) {
    if (meta.total === 0) return null;
    const go = (page) => { state.page = page; render(); };

    return el('nav', { class: 'pager', 'aria-label': 'Страницы' },
        el('span', { text: `Страница ${meta.page} из ${meta.total_pages} · всего ${meta.total}` }),
        el('div', { class: 'pager__btns' },
            el('button', { class: 'btn btn--small btn--ghost', type: 'button', text: '← Назад', disabled: meta.page <= 1, onclick: () => go(meta.page - 1) }),
            el('button', { class: 'btn btn--small btn--ghost', type: 'button', text: 'Вперёд →', disabled: meta.page >= meta.total_pages, onclick: () => go(meta.page + 1) }),
        ),
    );
}

async function removeTransaction(t) {
    if (!window.confirm(`Удалить операцию «${categoryName(t.category_id)}» от ${fmtDate(t.occurred_on)}?`)) return;
    try {
        await api(`/api/transactions/${t.id}`, { method: 'DELETE' });
        toast('Операция удалена');
        // если удалили последнюю запись на странице — вернёмся на предыдущую
        render();
    } catch (error) {
        toast(error.message, 'error');
    }
}

// ---------- диалог добавления и редактирования ----------

const dialog = $('#tx-dialog');
const txForm = $('#tx-form');
let editingId = null;

function clearErrors() {
    txForm.querySelectorAll('.err').forEach((node) => { node.textContent = ''; });
}

function openDialog(tx = null) {
    if (state.categories.length === 0) {
        toast('Сначала создайте хотя бы одну категорию.', 'error');
        location.hash = '#/categories';
        return;
    }

    editingId = tx?.id ?? null;
    $('#tx-title').textContent = tx ? 'Изменить операцию' : 'Новая операция';
    clearErrors();

    const { currency, category_id: category } = txForm.elements;
    currency.replaceChildren(...Object.keys(CURRENCIES).map((code) => el('option', { value: code, text: `${code} ${CURRENCIES[code]}` })));
    category.replaceChildren(...state.categories.map((c) => el('option', { value: c.id, text: c.name })));

    txForm.querySelector(`input[name="type"][value="${tx?.type ?? 'expense'}"]`).checked = true;
    txForm.elements.amount.value = tx ? tx.amount : '';
    currency.value = tx?.currency ?? 'RUB';
    category.value = tx ? String(tx.category_id) : String(state.categories[0].id);
    txForm.elements.occurred_on.value = tx?.occurred_on ?? isoDate();
    txForm.elements.occurred_on.max = isoDate();
    txForm.elements.note.value = tx?.note ?? '';

    dialog.showModal();
    txForm.elements.amount.focus();
}

$('#tx-cancel').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });

txForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();

    const data = new FormData(txForm);
    const body = {
        category_id: Number(data.get('category_id')),
        type: data.get('type'),
        amount: String(data.get('amount')).trim().replace(',', '.'),
        currency: data.get('currency'),
        occurred_on: data.get('occurred_on'),
        note: data.get('note'),
    };

    try {
        await api(editingId ? `/api/transactions/${editingId}` : '/api/transactions', {
            method: editingId ? 'PUT' : 'POST',
            body,
        });
        dialog.close();
        toast(editingId ? 'Изменения сохранены' : 'Операция добавлена');
        render();
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            showFieldErrors(error.details);
        } else {
            $('#tx-error').textContent = error.message;
        }
    }
});

/** Ошибки проверки от сервера показываем под полями, к которым они относятся. */
function showFieldErrors(details) {
    for (const [field, message] of Object.entries(details)) {
        const target = txForm.querySelector(`[data-err="${field}"]`) ?? $('#tx-error');
        target.textContent = message;
    }
}

// ---------- раздел «Отчёты» ----------

async function viewReports() {
    const r = state.report;
    const [monthly, byCategory] = await Promise.all([
        api('/api/reports/monthly', { query: { from: r.from, to: r.to } }),
        api('/api/reports/by-category', { query: { from: r.from, to: r.to, type: r.type } }),
    ]);

    const form = el('form', { class: 'filters', 'aria-label': 'Период отчёта' },
        el('label', { class: 'field' }, 'С', el('input', { type: 'date', name: 'from', value: r.from, required: true })),
        el('label', { class: 'field' }, 'По', el('input', { type: 'date', name: 'to', value: r.to, required: true })),
        el('label', { class: 'field' }, 'По категориям',
            el('select', { name: 'type' },
                el('option', { value: 'expense', text: 'Расходы', selected: r.type === 'expense' }),
                el('option', { value: 'income', text: 'Доходы', selected: r.type === 'income' }),
            )),
        el('button', { class: 'btn btn--small', type: 'submit', text: 'Показать' }),
    );
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const data = new FormData(form);
        if (data.get('to') < data.get('from')) {
            toast('Конечная дата раньше начальной.', 'error');
            return;
        }
        state.report = { from: data.get('from'), to: data.get('to'), type: data.get('type') };
        render();
    });

    return el('div', {},
        pageHead('Отчёты'),
        form,
        el('h2', { text: 'По месяцам' }),
        monthsChart(monthly.data),
        el('h2', { text: r.type === 'expense' ? 'Расходы по категориям' : 'Доходы по категориям' }),
        categoriesChart(byCategory.data, r.type),
    );
}

const percent = (value, max) => (max > 0 && value > 0 ? Math.max((value / max) * 100, 2) : 0);
const track = (kind, pct) => el('div', { class: 'bar-track' }, el('div', { class: `bar-fill bar-fill--${kind}`, style: `width:${pct}%` }));

function monthsChart(rows) {
    if (rows.length === 0) return el('div', { class: 'empty' }, el('p', { text: 'За этот период операций нет.' }));
    // суммы нужны только для ширины полос; точные значения выводятся строками из API
    const max = Math.max(...rows.flatMap((m) => [parseFloat(m.income), parseFloat(m.expense)]));

    return el('ul', { class: 'months' }, rows.map((m) => el('li', { class: 'month' },
        el('div', { class: 'month__label', text: monthLabel(m.month) }),
        el('div', { class: 'month__bars', 'aria-hidden': 'true' },
            track('income', percent(parseFloat(m.income), max)),
            track('expense', percent(parseFloat(m.expense), max)),
        ),
        el('div', { class: 'month__nums' },
            el('span', { class: 'is-income', text: `+${money(m.income)}` }),
            el('span', { class: 'is-expense', text: `−${money(m.expense)}` }),
            el('strong', { class: m.balance.startsWith('-') ? 'is-expense' : '', text: `= ${money(m.balance)}` }),
        ),
    )));
}

function categoriesChart(rows, type) {
    if (rows.length === 0) return el('div', { class: 'empty' }, el('p', { text: 'За этот период операций нет.' }));
    const max = Math.max(...rows.map((c) => parseFloat(c.total)));

    return el('ul', { class: 'cats' }, rows.map((c) => el('li', { class: 'cat' },
        el('span', { text: c.category }),
        el('div', { 'aria-hidden': 'true' }, track(type === 'income' ? 'income' : 'neutral', percent(parseFloat(c.total), max))),
        el('div', { class: 'cat__sum' }, money(c.total), el('small', { text: `операций: ${c.count}` })),
    )));
}

// ---------- раздел «Категории» ----------

async function viewCategories() {
    const error = el('small', { class: 'err', role: 'alert' });
    const form = el('form', { class: 'inline-form' },
        el('label', { class: 'field' }, 'Новая категория',
            el('input', { name: 'name', maxlength: '80', placeholder: 'Например, Подарки', required: true, autocomplete: 'off' })),
        el('button', { class: 'btn', type: 'submit', text: 'Добавить' }),
        error,
    );

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.textContent = '';
        try {
            await api('/api/categories', { method: 'POST', body: { name: new FormData(form).get('name') } });
            toast('Категория добавлена');
            render();
        } catch (e) {
            error.textContent = e instanceof ApiError ? (e.details.name ?? e.message) : e.message;
        }
    });

    return el('div', {},
        pageHead('Категории'),
        form,
        state.categories.length === 0
            ? el('div', { class: 'empty' }, el('p', { text: 'Категорий пока нет. Добавьте первую, чтобы вести операции.' }))
            : el('ul', { class: 'chips' }, state.categories.map((c) => el('li', { text: c.name }))),
    );
}

// ---------- раздел «Курсы» ----------

async function viewRates() {
    const { data } = await api('/api/rates', { query: { date: state.ratesDate } });

    const input = el('input', { type: 'date', name: 'date', value: state.ratesDate, max: isoDate() });
    const form = el('form', { class: 'inline-form' },
        el('label', { class: 'field' }, 'Дата', input),
        el('button', { class: 'btn btn--small', type: 'submit', text: 'Показать' }),
    );
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (input.value) state.ratesDate = input.value;
        render();
    });

    return el('div', {},
        pageHead('Курсы валют'),
        form,
        el('table', { class: 'table' },
            el('thead', {}, el('tr', {}, el('th', { text: 'Валюта' }), el('th', { class: 'num', text: 'Рублей за 1 единицу' }))),
            el('tbody', {}, Object.entries(data.rates).map(([code, value]) =>
                el('tr', {}, el('td', { text: `${code} ${CURRENCIES[code] ?? ''}` }), el('td', { class: 'num', text: `${rate(value)} ₽` })))),
        ),
        el('p', { class: 'muted', text: 'Курс на выбранную дату берётся из источника, заданного в настройках сервера (RATE_PROVIDER).' }),
    );
}

// ---------- маршрутизация ----------

const routes = { transactions: viewTransactions, reports: viewReports, categories: viewCategories, rates: viewRates };

async function render() {
    const name = location.hash.replace('#/', '') || 'transactions';
    const view = routes[name] ?? routes.transactions;
    const active = name in routes ? name : 'transactions';

    document.querySelectorAll('#nav a').forEach((link) => {
        const isActive = link.dataset.route === active;
        link.classList.toggle('is-active', isActive);
        if (isActive) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
    });

    try {
        state.categories = (await api('/api/categories')).data;
        app.replaceChildren(await view());
    } catch (error) {
        app.replaceChildren(errorBox(error));
    }
}

window.addEventListener('hashchange', () => { state.page = 1; render(); });
render();
