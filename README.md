# Ядро магазина цифровых товаров

REST API выдачи цифровых кодов (ниша вроде GGSel): заказ → вебхук оплаты → однократная доставка от поставщика. Реального эквайринга нет — оплату эмулирует `scripts/race_webhook.php`.

Стек: PHP 8.3, Laravel 11, PostgreSQL 16.

## Ключевые решения

1. **Вебхук не вызывает поставщика.** Он только пишет `payment_events`, переводит заказ в `paid`, ставит `FulfillOrder`. Иначе таймаут эквайера смешивается с выдачей.
2. **Exactly-once = уникальный `fulfillments.order_id` + стабильный idempotency-ключ `{provider}:{order_id}`.** Победитель INSERT вызывает поставщика; повтор вебхука не создаёт вторую выдачу.
3. **Таймаут ≠ отказ.** Заглушка сначала сохраняет код, потом «отваливается». После таймаута идёт `fetchStatus` по тому же ключу. Fallback на B — только если A явно отказал или сверка подтвердила, что кода нет. Новый ключ после таймаута запрещён.
4. **Деньги и товар — разные журналы.** Ledger (double-entry, `SUM(amount_cents) = 0` по заказу) и `fulfillments` сверяются отдельно: «оплачен, не выдан» / «выдан, не оплачен».

Статусы заказа: `pending_payment` → `paid` → `fulfilling` → `delivered` | `failed`.

## API

| Метод | Путь | Назначение |
|---|---|---|
| `POST` | `/api/orders` | `{ "sku": "STEAM-CS2-KEY" }` → заказ `pending_payment` |
| `GET` | `/api/orders/{id}` | Статус; `code` только после `delivered` |
| `POST` | `/api/webhooks/payment` | Вебхук оплаты |
| `GET` | `/api/storefront?category=steam` | Горячая витрина остатков |
| `GET` | `/api/admin/reconciliation` | Сверка |

Контракт вебхука:

```json
{
  "event_id": "evt-123",
  "order_id": "uuid",
  "amount_cents": 1499,
  "status": "paid"
}
```

Ответ: `{ "ok": true, "duplicate": false }`. Повтор того же `event_id` — `duplicate: true`, HTTP 200, без второй выдачи. Опционально `X-Signature: HMAC-SHA256(body, PAYMENT_WEBHOOK_SECRET)`.

## Запуск

```bash
composer install
bash scripts/dev-postgres.sh          # локальный Postgres на :5433
cp .env.example .env && php artisan key:generate
php artisan migrate --seed            # 5000+ SKU и STEAM-CS2-KEY
php artisan serve
```

Либо Docker: `docker compose up -d` и в `.env` `DB_PORT=5432`, `DB_PASSWORD=kudrya`.

Очередь по умолчанию `sync` (выдача в том же запросе вебхука — удобно для гонок). Для параллельного `scripts/race_webhook.php` у `php artisan serve` нужны воркеры: `PHP_CLI_SERVER_WORKERS=8 php artisan serve`.

Для Redis: `QUEUE_CONNECTION=redis` и `php artisan queue:work`. Планировщик раз в минуту дожимает зависшие: `php artisan schedule:work`.

Хаос поставщиков (случайные отказы и таймауты): `PROVIDER_CHAOS=true`.

## Гонки (этап 2)

Тот же скрипт эмулирует оплату и бьёт вебхук параллельно:

```bash
php artisan serve
php scripts/race_webhook.php
php scripts/race_webhook.php --mode=distinct-events --concurrency=20
```

Ожидание: статус `delivered`, один код, ledger сходится.

```bash
php artisan test
php artisan orders:reconcile
php artisan catalog:explain steam
```

Структурные логи выдачи и платежей: `storage/logs/commerce.log` (JSON).

## Каталог (этап 5)

Горячий запрос витрины — лента доступных SKU категории, не карточка:

```sql
SELECT sku, title, price_cents, stock_qty, sort_rank, id
FROM products
WHERE category_id = $1 AND is_available = true
ORDER BY sort_rank DESC, id
LIMIT 50;
```

Частичный индекс с `INCLUDE`, чтобы не фильтровать `stock_qty > 0` seq scan'ом:

```sql
CREATE INDEX products_storefront_idx
ON products (category_id, sort_rank DESC, id)
INCLUDE (sku, title, price_cents, stock_qty)
WHERE is_available;
```

`is_available` обновляется вместе со `stock_qty` (observer модели). План на сиде 5k+ SKU:

```
Limit  (cost=0.28..4.77 rows=50 width=48) (actual time=0.018..0.024 rows=50 loops=1)
  Buffers: shared hit=3
  ->  Index Only Scan using products_storefront_idx on products
        Index Cond: (category_id = '1'::bigint)
        Heap Fetches: 0
        Buffers: shared hit=3
Planning Time: 0.167 ms
Execution Time: 0.033 ms
```

Index Only Scan, без Seq Scan и без heap fetches. Снять план: `php artisan catalog:explain steam`.
