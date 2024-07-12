CREATE TABLE IF NOT EXISTS "proxies" (
  "id" INTEGER,
  "proxy" TEXT NOT NULL UNIQUE,
  "latency" TEXT,
  "last_check" TEXT,
  "type" TEXT,
  "region" TEXT,
  "city" TEXT,
  "country" TEXT,
  "timezone" TEXT,
  "latitude" TEXT,
  "longitude" TEXT,
  "anonymity" TEXT,
  "https" TEXT,
  "status" TEXT,
  "private" TEXT,
  "lang" TEXT,
  "useragent" TEXT,
  "webgl_vendor" TEXT,
  "webgl_renderer" TEXT,
  "browser_vendor" TEXT,
  "username" TEXT,
  "password" TEXT,
  PRIMARY KEY ("id" AUTOINCREMENT)
);

CREATE TABLE IF NOT EXISTS "processed_proxies" ("proxy" TEXT NOT NULL UNIQUE);

CREATE TABLE IF NOT EXISTS "added_proxies" ("proxy" TEXT NOT NULL UNIQUE);

CREATE TABLE IF NOT EXISTS "meta" (key TEXT PRIMARY KEY, value TEXT);
