-- MySQL schema converted from SQLite schema
CREATE TABLE IF NOT EXISTS `proxies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `proxy` VARCHAR(255) NOT NULL UNIQUE,
  `latency` VARCHAR(255),
  `last_check` VARCHAR(255),
  `type` VARCHAR(255),
  `region` VARCHAR(255),
  `city` VARCHAR(255),
  `country` VARCHAR(255),
  `timezone` VARCHAR(255),
  `latitude` VARCHAR(255),
  `longitude` VARCHAR(255),
  `anonymity` VARCHAR(255),
  `https` VARCHAR(255),
  `status` VARCHAR(255),
  `private` VARCHAR(255),
  `lang` VARCHAR(255),
  `useragent` VARCHAR(255),
  `webgl_vendor` VARCHAR(255),
  `webgl_renderer` VARCHAR(255),
  `browser_vendor` VARCHAR(255),
  `username` VARCHAR(255),
  `password` VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS `processed_proxies` (
  `updated` VARCHAR(255),
  `proxy` VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS `added_proxies` (
  `updated` VARCHAR(255),
  `proxy` VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS `meta` (`key` VARCHAR(255) PRIMARY KEY, `value` TEXT);

CREATE TABLE IF NOT EXISTS `auth_user` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `password` VARCHAR(128) NOT NULL,
  `last_login` DATETIME NULL,
  `is_superuser` TINYINT (1) NOT NULL,
  `username` VARCHAR(150) NOT NULL UNIQUE,
  `last_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(254) NOT NULL,
  `is_staff` TINYINT (1) NOT NULL,
  `is_active` TINYINT (1) NOT NULL,
  `date_joined` DATETIME NOT NULL,
  `first_name` VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS `user_fields` (
  `user_id` INT NOT NULL PRIMARY KEY,
  `saldo` DECIMAL(20, 2) NOT NULL,
  `phone` VARCHAR(128) NULL UNIQUE,
  FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`)
);

CREATE TABLE IF NOT EXISTS `user_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `log_level` VARCHAR(32) NOT NULL DEFAULT 'INFO', -- INFO, WARNING, ERROR
  `message` TEXT NOT NULL, -- human-readable log
  `source` VARCHAR(255), -- module/service
  `extra_info` JSON, -- use JSON if possible
  FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`)
) ENGINE = InnoDB;
