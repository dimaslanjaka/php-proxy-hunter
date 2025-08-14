-- re-order id auto increment
CREATE TABLE IF NOT EXISTS "proxies_new" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
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
  "password" TEXT
);

INSERT INTO
  proxies_new (
    "proxy",
    "latency",
    "last_check",
    "type",
    "region",
    "city",
    "country",
    "timezone",
    "latitude",
    "longitude",
    "anonymity",
    "https",
    "status",
    "private",
    "lang",
    "useragent",
    "webgl_vendor",
    "webgl_renderer",
    "browser_vendor",
    "username",
    "password"
  )
SELECT
  "proxy",
  "latency",
  "last_check",
  "type",
  "region",
  "city",
  "country",
  "timezone",
  "latitude",
  "longitude",
  "anonymity",
  "https",
  "status",
  "private",
  "lang",
  "useragent",
  "webgl_vendor",
  "webgl_renderer",
  "browser_vendor",
  "username",
  "password"
FROM
  proxies;

DROP TABLE proxies;

ALTER TABLE proxies_new
RENAME TO proxies;
