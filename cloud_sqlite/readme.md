# 📡 PHP SQLite Sync API

A lightweight PHP + SQLite backend for syncing structured data across devices. Uses PDO for SQLite access, strict types, and PSR-12 coding standards. Supports insert/update with timestamps, fetch-all, fetch-since, and optional authentication.

## 🔐 Authentication

All API endpoints require authentication. The recommended method is the HTTP `Authorization` header:

```
Authorization: Bearer <your-secret-token>
```

The secret token is stored in your `.env` file as `CLOUD_SQLITE_SECRET`:

```ini
CLOUD_SQLITE_SECRET=your-secret-token
```

The application loads this value automatically via `config.php` using [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv).

**Supported authentication methods:**

- **Production:**
  - HTTP `Authorization` header (recommended)
  - `auth` field in POST/JSON body
- **Development only (when `is_debug()` is true):**
  - `?auth=...` query parameter (for quick testing)

---

## 🏗️ Setup Instructions

### PHP Requirements

- PHP 7.4 or newer (with PDO and pdo_sqlite extensions enabled)
- Composer (for dependency management)

### Enable PDO SQLite Extension

Ensure the PDO SQLite extension is enabled in your `php.ini` file:

- Open your `php.ini` (location varies by system, e.g., `C:/xampp/php/php.ini`)
- Make sure the following lines are present and not commented out (remove the leading `;` if present):
  ```ini
  extension=pdo_sqlite
  ```
- Restart your web server (Apache, Nginx, etc.) after making changes.

### Install Dependencies

Run Composer to install dependencies:

```bash
composer install
```

### Deploy Project Files

Place the project files in a PHP-enabled server (e.g. Apache, Nginx).

### Configure Environment Variables

Copy `.env-sample` to `.env` and set your secrets:

```bash
cp .env-sample .env
# Edit .env and set CLOUD_SQLITE_SECRET
```

### Initialize Database Schema

Run `init.php` once to create the database schema:

```bash
php init.php
```

### Start Using the API

You can now use the API from any device.

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

**cURL (with Authorization header):**
```bash
curl -X POST http://localhost/sync.php \
  -H "Authorization: Bearer <your-secret-token>" \
  -H "Content-Type: application/json" \
  -d '{"id":1,"name":"Device A","value":"Hello World"}'
```

**cURL (with auth field in body):**
```bash
curl -X POST http://localhost/sync.php \
  -H "Content-Type: application/json" \
  -d '{"id":1,"name":"Device A","value":"Hello World","auth":"<your-secret-token>"}'
```

**cURL (with ?auth param, development only):**
```bash
curl -X POST "http://localhost/sync.php?auth=<your-secret-token>" \
  -H "Content-Type: application/json" \
  -d '{"id":1,"name":"Device A","value":"Hello World"}'
```

---

### `GET /fetch.php`
Fetch all rows from the database.

**cURL (with Authorization header):**
```bash
curl -H "Authorization: Bearer <your-secret-token>" http://localhost/fetch.php
```

**cURL (with ?auth param, development only):**
```bash
curl http://localhost/fetch.php?auth=<your-secret-token>
```

---

### `GET /fetch-updated.php?since=TIMESTAMP`
Fetch rows updated after a specific timestamp.

**Parameters:**
- `since` – (required) ISO 8601 or SQLite-compatible timestamp

**Example (with Authorization header):**
```bash
curl -H "Authorization: Bearer <your-secret-token>" \
  "http://localhost/fetch-updated.php?since=2025-07-07T08:00:00"
```

**Example (with ?auth param, development only):**
```bash
curl "http://localhost/fetch-updated.php?since=2025-07-07T08:00:00&auth=<your-secret-token>"
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

**cURL (with Authorization header):**
```bash
curl -X POST http://localhost/delete.php \
  -H "Authorization: Bearer <your-secret-token>" \
  -H "Content-Type: application/json" \
  -d '{"id":1}'
```

**cURL (with auth field in body):**
```bash
curl -X POST http://localhost/delete.php \
  -H "Content-Type: application/json" \
  -d '{"id":1,"auth":"<your-secret-token>"}'
```

**cURL (with ?auth param, development only):**
```bash
curl -X POST "http://localhost/delete.php?auth=<your-secret-token>" \
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
- Uses PDO for database access
- Insert or update with `ON CONFLICT`
- Timestamp tracking with `updated_at`
- Simple RESTful API
- Minimal auth (Bearer token)
- Strict types and PSR-12 coding standards
- Lightweight and easy to self-host

---

## 🔒 Security Notes

- Use strong, unique `CLOUD_SQLITE_SECRET` in production (set in `.env-local`)
- Add rate limiting and logging for security
- Consider HTTPS if exposed to the public internet

---

## 📦 License

MIT – Use freely for personal or commercial projects