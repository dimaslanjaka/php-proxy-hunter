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

CREATE TABLE IF NOT EXISTS "processed_proxies" ("updated" TEXT, "proxy" TEXT NOT NULL UNIQUE);

CREATE TABLE IF NOT EXISTS "added_proxies" ("updated" TEXT, "proxy" TEXT NOT NULL UNIQUE);

CREATE TABLE IF NOT EXISTS "meta" (key TEXT PRIMARY KEY, value TEXT);

-- based on django authentication database
CREATE TABLE IF NOT EXISTS "auth_user" (
  "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
  "password" VARCHAR(128) NOT NULL,
  "last_login" DATETIME NULL,
  "is_superuser" BOOL NOT NULL,
  "username" VARCHAR(150) NOT NULL UNIQUE,
  "last_name" VARCHAR(150) NOT NULL,
  "email" VARCHAR(254) NOT NULL,
  "is_staff" BOOL NOT NULL,
  "is_active" BOOL NOT NULL,
  "date_joined" DATETIME NOT NULL,
  "first_name" VARCHAR(150) NOT NULL
);
-- is_superuser, is_staff, is_active value only 0 (false) or 1 (true)
-- add admin
-- INSERT INTO "auth_user" ("password", "is_superuser", "username", "last_name", "email", "is_staff", "is_active", "first_name") VALUES ('pbkdf2_sha256$720000$m80dHthUAXmM7pr2SKnuEd$GwjT+iseriwh9KNM/R1kIgL/GHfbKf2htsVMDLHzKNE=', '1', 'admin', '', 'admin@example.com', '1', '1', '');
-- add user
-- INSERT INTO "auth_user" ("password", "is_superuser", "username", "last_name", "email", "is_staff", "is_active", "first_name") VALUES ('pbkdf2_sha256$720000$m80dHthUAXmM7pr2SKnuEd$GwjT+iseriwh9KNM/R1kIgL/GHfbKf2htsVMDLHzKNE=', '0', 'user', '', 'user@example.com', '0', '1', '');
-- add staff
-- INSERT INTO "auth_user" ("password", "is_superuser", "username", "last_name", "email", "is_staff", "is_active", "first_name") VALUES ('pbkdf2_sha256$720000$m80dHthUAXmM7pr2SKnuEd$GwjT+iseriwh9KNM/R1kIgL/GHfbKf2htsVMDLHzKNE=', '0', 'staff', '', 'staff@example.com', '1', '1', '');

-- create user fields from django
CREATE TABLE IF NOT EXISTS "user_fields" (
  "user_id" integer NOT NULL PRIMARY KEY REFERENCES "auth_user" ("id") DEFERRABLE INITIALLY DEFERRED,
  "saldo" decimal NOT NULL,
  "phone" varchar(128) NULL UNIQUE
)
