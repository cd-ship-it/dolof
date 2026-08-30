-- ============================================================================
-- Dolos (DOLOS) — Deacons Ordination Luncheon Ordering System
-- PRODUCTION deployment script.
--
-- Assumes the target database already exists (shared host: crossp11_db1).
-- Run against that database, e.g.:
--   mysql -h <host> -u <user> -p crossp11_db1 < production.sql
-- Safe to re-run: all statements are idempotent.
-- All tables use the dolos_ prefix to coexist with other apps in the schema.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ── Lunch box catalogue ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dolos_boxes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        CHAR(1)      NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `price_cents` INT          NOT NULL DEFAULT 1500,
  `cap`         INT          NOT NULL DEFAULT 100,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dolos_box_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Orders ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dolos_orders` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name`              VARCHAR(100) NOT NULL DEFAULT '',
  `last_name`               VARCHAR(100) NOT NULL DEFAULT '',
  `email`                   VARCHAR(200) NOT NULL DEFAULT '',
  `phone`                   VARCHAR(50)  NOT NULL DEFAULT '',
  `campus`                  VARCHAR(30)  NOT NULL DEFAULT '',
  `lift_group`              VARCHAR(20)  NOT NULL DEFAULT '',
  `status`                  ENUM('pending','paid','expired','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount_cents`      INT          NOT NULL DEFAULT 0,
  `stripe_session_id`       VARCHAR(255) NOT NULL DEFAULT '',
  `payment_method`          VARCHAR(20)  NOT NULL DEFAULT 'stripe',
  `hold_expires_at`         TIMESTAMP    NULL DEFAULT NULL,
  `confirmation_email_sent` TINYINT(1)   NOT NULL DEFAULT 0,
  `capacity_flag`           TINYINT(1)   NOT NULL DEFAULT 0,
  `flag_reason`             VARCHAR(255) NULL DEFAULT NULL,
  `created_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status`        (`status`),
  KEY `idx_email`         (`email`),
  KEY `idx_stripe`        (`stripe_session_id`),
  KEY `idx_hold_expires`  (`hold_expires_at`),
  KEY `idx_capacity_flag` (`capacity_flag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Order line items (one row per box type in an order) ──────────────────────
CREATE TABLE IF NOT EXISTS `dolos_order_items` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`         INT UNSIGNED NOT NULL,
  `box_id`           INT UNSIGNED NOT NULL,
  `box_code`         CHAR(1)      NOT NULL,
  `box_name`         VARCHAR(150) NOT NULL,
  `unit_price_cents` INT          NOT NULL,
  `quantity`         INT          NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_box_id`   (`box_id`),
  CONSTRAINT `fk_dolos_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `dolos_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dolos_items_box`
    FOREIGN KEY (`box_id`) REFERENCES `dolos_boxes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Key/value settings ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dolos_settings` (
  `key`   VARCHAR(100) NOT NULL,
  `value` TEXT         NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed data (idempotent) ──────────────────────────────────────────────────
-- Placeholder box names — edit these on the admin dashboard once the caterer
-- confirms the menu. Price/cap are also editable there.
INSERT INTO `dolos_boxes` (`code`, `name`, `price_cents`, `cap`, `sort_order`) VALUES
  ('A', 'Lunch Box A', 1500, 100, 1),
  ('B', 'Lunch Box B', 1500, 100, 2),
  ('C', 'Lunch Box C', 1500, 100, 3),
  ('D', 'Lunch Box D', 1500, 100, 4),
  ('E', 'Lunch Box E', 1500, 100, 5)
ON DUPLICATE KEY UPDATE `code` = VALUES(`code`);

INSERT INTO `dolos_settings` (`key`, `value`) VALUES
  ('ordering_open',  '1'),
  ('event_title',    'Deacons Ordination Luncheon'),
  ('event_date',     ''),
  ('event_location', 'Crosspoint Church')
ON DUPLICATE KEY UPDATE `value` = `value`;
