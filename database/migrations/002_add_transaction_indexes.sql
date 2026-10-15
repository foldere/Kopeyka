-- Индексы под частые запросы: фильтр по датам и по категории + дате.
-- Сравните EXPLAIN до и после (см. LEARN.md).

ALTER TABLE transactions
    ADD INDEX idx_transactions_occurred_on (occurred_on),
    ADD INDEX idx_transactions_category_date (category_id, occurred_on);
