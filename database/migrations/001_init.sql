-- Базовая схема. Суммы хранятся целыми числами в минимальных единицах (копейках),
-- а не в float: так нет ошибок округления.

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    rate_micro BIGINT UNSIGNED NOT NULL,
    amount_rub_minor BIGINT UNSIGNED NOT NULL,
    occurred_on DATE NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Кэш курсов валют: чтобы не ходить за ними на каждый запрос.
CREATE TABLE exchange_rates (
    rate_date DATE NOT NULL,
    currency CHAR(3) NOT NULL,
    rub_per_unit_micro BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (rate_date, currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
