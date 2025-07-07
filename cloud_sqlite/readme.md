# 📡 PHP SQLite Sync API

A lightweight PHP + SQLite backend for syncing structured data across devices. Supports insert/update with timestamps, fetch-all, fetch-since, and optional authentication.

## 🔐 Authentication

All API endpoints require an `Authorization` header:

```
Authorization: Bearer <your-secret-token>
```

The secret token is stored in your `.env` file as `CLOUD_SQLITE_SECRET`:

```ini
CLOUD_SQLITE_SECRET=your-secret-token
```

The application loads this value automatically via `config.php` using [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv).

---

## 🏗️ Setup Instructions

1. Place the project files in a PHP-enabled server (e.g. Apache, Nginx).
2. Copy `.env-sample` to `.env` and set your secrets:

```bash
cp .env-sample .env
# Edit .env and set CLOUD_SQLITE_SECRET
```

3. Run `init.php` once to create the database schema:

```bash
php init.php
```

4. Start using the API from any device.

---

## 📤 Endpoints

### `POST /sync.php`
Insert or update a row by ID.

**Request:**

```json
{
  "id": 1,
  "name": "Device A",
  "value": "Hello World"
}
```

**cURL:**
```bash
curl -X POST http://localhost/sync.php \
  -H "Authorization: Bearer <your-secret-token>" \
  -H "Content-Type: application/json" \
  -d '{"id":1,"name":"Device A","value":"Hello World"}'
```

---

### `GET /fetch.php`
Fetch all rows from the database.

**cURL:**
```bash
curl -H "Authorization: Bearer <your-secret-token>" http://localhost/fetch.php
```

---

### `GET /fetch-updated.php?since=TIMESTAMP`
Fetch rows updated after a specific timestamp.

**Parameters:**
- `since` – (required) ISO 8601 or SQLite-compatible timestamp

**Example:**
```bash
curl -H "Authorization: Bearer <your-secret-token>" \
  "http://localhost/fetch-updated.php?since=2025-07-07T08:00:00"
```

---

### `POST /delete.php`
Delete a row by ID.

**Request:**
```json
{
  "id": 1
}
```

**cURL:**
```bash
curl -X POST http://localhost/delete.php \
  -H "Authorization: Bearer <your-secret-token>" \
  -H "Content-Type: application/json" \
  -d '{"id":1}'
```

---

## 📝 Schema

```sql
CREATE TABLE items (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  value TEXT,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

---

## ✅ Features

- SQLite-backed, no external DB required
- Insert or update with `ON CONFLICT`
- Timestamp tracking with `updated_at`
- Simple RESTful API
- Minimal auth (Bearer token)
- Lightweight and easy to self-host

---

## 🔒 Security Notes

- Use strong, unique `CLOUD_SQLITE_SECRET` in production (set in `.env-local`)
- Add rate limiting and logging for security
- Consider HTTPS if exposed to the public internet

---

## 📦 License

MIT – Use freely for personal or commercial projects