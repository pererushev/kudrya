# Ядро магазина цифровых товаров

REST API выдачи цифровых кодов (ниша вроде GGSel): заказ → вебхук оплаты → однократная доставка от поставщика. Реального эквайринга нет — оплату эмулирует `scripts/race_webhook.php`.

Стек: PHP 8.3, Laravel 11, PostgreSQL 16.

## Ключевые решения

1. **Вебхук не вызывает поставщика.** Он только пишет `payment_events`, переводит заказ в `paid`, ставит `FulfillOrder`. Иначе таймаут эквайера смешивается с выдачей.
2. **Exactly-once = уникальный `fulfillments.order_id` + стабильный `request_id` (`req_{orderId}-{a|b}`).** Победитель INSERT вызывает поставщика; повтор вебхука не создаёт вторую выдачу. Ключ из пула уникален (`digital_keys.code`).
3. **Таймаут ≠ отказ.** Заглушка сначала сохраняет код, потом «отваливается». Повтор — тот же `request_id` / `fetchStatus`. Fallback на B — только если A явно отказал (5xx / `out_of_stock`). Неразрешённый таймаут не идёт на B.
4. **Деньги и товар — разные журналы.** Ledger (double-entry, `SUM(amount_cents) = 0` по заказу) и `fulfillments` сверяются отдельно: «оплачен, не выдан» / «выдан, но не оплачен».

Статусы: `created` → `paid` → `delivering` → `delivered`. Ветки: `payment_failed`; `out_of_stock` и `delivery_failed` восстановимы фоновой сверкой.

## API

| Метод | Путь | Назначение |
|---|---|---|
| `POST` | `/api/orders` | `{ "sku": "STEAM-TOPUP-500" }` → заказ `created` |
| `GET` | `/api/orders/{id}` | Статус; `code` только после `delivered` |
| `POST` | `/api/webhooks/payment` | Вебхук оплаты (контракт ТЗ) |
| `GET` | `/api/storefront?category=steam` | Горячая витрина остатков |
| `GET` | `/api/admin/reconciliation` | Сверка |

Контракт вебхука:

```json
{
  "event_id": "evt_a1b2c3",
  "order_id": "<id заказа>",
  "status": "paid",
  "amount": 500,
  "currency": "RUB",
  "created_at": "2025-01-01T12:00:00Z"
}
```

`status`: `paid` | `failed`. Ответ: `{ "ok": true, "duplicate": false }`. Повтор того же `event_id` — `duplicate: true`, HTTP 200. Заказ ещё не создан — HTTP 503 (платежка ретраит). Алиас `amount_cents` принимается. Подпись не требуется.

## Запуск

```bash
composer install
bash scripts/dev-postgres.sh          # локальный Postgres на :5433
cp .env.example .env && php artisan key:generate
php artisan migrate --seed            # каталог ТЗ + 5000 SKU + пул ключей
php artisan serve
```

Либо Docker: `docker compose up -d` и в `.env` `DB_PORT=5432`, `DB_PASSWORD=kudrya`.

Очередь по умолчанию `sync` (выдача в том же запросе вебхука — удобно для гонок). Для параллельного `scripts/race_webhook.php` у `php artisan serve` нужны воркеры: `PHP_CLI_SERVER_WORKERS=8 php artisan serve`.

Для Redis: `QUEUE_CONNECTION=redis` и `php artisan queue:work`. Планировщик раз в минуту дожимает зависшие (`paid` / `delivering` / `out_of_stock` / `delivery_failed`): `php artisan schedule:work`.

Хаос поставщиков (случайные отказы и таймауты): `PROVIDER_CHAOS=true`. Доли: `PROVIDER_A_FAIL_RATE`, `PROVIDER_A_TIMEOUT_RATE`, то же для B.

## Гонки (этап 2, критерий 1–2)

```bash
PHP_CLI_SERVER_WORKERS=8 php artisan serve
php scripts/race_webhook.php
php scripts/race_webhook.php --mode=distinct-events --concurrency=50
```

Ожидание: статус `delivered`, один код из пула, ledger сходится.

## Отказ и fallback поставщика (этап 3)

Детерминированно в тестах: `php artisan test --filter=FulfillmentResilience`.

Вручную: `PROVIDER_CHAOS=true` (A чаще 5xx/timeout, B резервный). Таймаут A не должен дать второй код: повтор с тем же `request_id`. Явный отказ A → выдача B ровно один раз.

```bash
php artisan test
php artisan orders:reconcile
php artisan catalog:explain steam
```

Структурные логи: `storage/logs/commerce.log` (JSON).

## Как масштабировали бы под нагрузку

Вебхук остаётся тонким ACK (запись события + `paid` + enqueue). Выдача — воркеры очереди, горизонтально. Идемпотентность держит БД: unique `event_id`, unique `fulfillments.order_id`, unique `digital_keys.code` / `request_id`, `FOR UPDATE SKIP LOCKED` на пуле. Витрина — index-only scan по partial index `products_storefront_idx`. При росте каталога — партиции ledger по времени, отдельный read-replica для сверки, Redis unique lock на `FulfillOrder`.

## Время

Оценка: ~2 рабочих дня на ядро (этапы 1–5) и доведение контрактов/критериев из полного ТЗ.

## Каталог (этап 5)

Горячий запрос витрины — лента доступных SKU категории:

```sql
SELECT sku, title, price_cents, stock_qty, sort_rank, id
FROM products
WHERE category_id = $1 AND is_available = true
ORDER BY sort_rank DESC, id
LIMIT 50;
```

```sql
CREATE INDEX products_storefront_idx
ON products (category_id, sort_rank DESC, id)
INCLUDE (sku, title, price_cents, stock_qty)
WHERE is_available;
```

План: `php artisan catalog:explain steam` — Index Only Scan, без Seq Scan.
