-- NOXARA Investment Platform
-- Database SQL - Import via phpMyAdmin
-- NO CREATE DATABASE / USE statements
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

-- --------------------------------------------------------
-- Table: admin_users
-- --------------------------------------------------------
CREATE TABLE `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('superadmin','cs','finance') NOT NULL DEFAULT 'cs',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `last_ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `pin` VARCHAR(255) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `referral_code` VARCHAR(20) NOT NULL,
  `referred_by` INT UNSIGNED DEFAULT NULL,
  `vip_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `total_deposit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_frozen` TINYINT(1) NOT NULL DEFAULT 0,
  `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
  `email_verified_at` DATETIME DEFAULT NULL,
  `theme` ENUM('dark','light') NOT NULL DEFAULT 'dark',
  `withdraw_pin_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `withdraw_pin_locked_until` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `referral_code` (`referral_code`),
  KEY `referred_by` (`referred_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: user_wallets
-- --------------------------------------------------------
CREATE TABLE `user_wallets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `main_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `profit_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `bonus_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `referral_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_sessions
-- --------------------------------------------------------
CREATE TABLE `user_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `session_token` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `last_activity` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `session_token` (`session_token`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_login_logs
-- --------------------------------------------------------
CREATE TABLE `user_login_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `username_attempt` VARCHAR(100) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `status` ENUM('success','failed','blocked') NOT NULL DEFAULT 'failed',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: vip_levels
-- --------------------------------------------------------
CREATE TABLE `vip_levels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level` TINYINT UNSIGNED NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `min_deposit_required` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `min_deposit_per_tx` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `min_withdraw` DECIMAL(15,2) NOT NULL DEFAULT 100000.00,
  `withdraw_fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  `max_withdraw_per_day` INT UNSIGNED NOT NULL DEFAULT 1,
  `daily_withdraw_limit` DECIMAL(15,2) NOT NULL DEFAULT 1000000.00,
  `color` VARCHAR(20) NOT NULL DEFAULT '#888888',
  `badge_icon` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: vip_codes
-- --------------------------------------------------------
CREATE TABLE `vip_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vip_level` TINYINT UNSIGNED NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vip_level` (`vip_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: bank_accounts (user)
-- --------------------------------------------------------
CREATE TABLE `bank_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `bank_name` VARCHAR(100) NOT NULL,
  `account_number` VARCHAR(50) NOT NULL,
  `account_name` VARCHAR(100) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_bank_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: admin_bank_accounts
-- --------------------------------------------------------
CREATE TABLE `admin_bank_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_name` VARCHAR(100) NOT NULL,
  `account_number` VARCHAR(50) NOT NULL,
  `account_name` VARCHAR(100) NOT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: deposits
-- --------------------------------------------------------
CREATE TABLE `deposits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `admin_bank_id` INT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `unique_code` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(15,2) NOT NULL,
  `voucher_id` INT UNSIGNED DEFAULT NULL,
  `voucher_discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `proof_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','confirmed','rejected','expired') NOT NULL DEFAULT 'pending',
  `confirmed_by` INT UNSIGNED DEFAULT NULL,
  `confirmed_at` DATETIME DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_deposit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: withdrawals
-- --------------------------------------------------------
CREATE TABLE `withdrawals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `bank_account_id` INT UNSIGNED NOT NULL,
  `wallet_type` ENUM('main','profit','referral') NOT NULL DEFAULT 'main',
  `amount` DECIMAL(15,2) NOT NULL,
  `fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `net_amount` DECIMAL(15,2) NOT NULL,
  `status` ENUM('pending','processing','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: transactions
-- --------------------------------------------------------
CREATE TABLE `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `wallet_type` ENUM('main','profit','bonus','referral') NOT NULL DEFAULT 'main',
  `amount` DECIMAL(15,2) NOT NULL,
  `balance_before` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `balance_after` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: product_categories
-- --------------------------------------------------------
CREATE TABLE `product_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(255) DEFAULT NULL,
  `color` VARCHAR(20) DEFAULT '#00D4FF',
  `description` TEXT DEFAULT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: products
-- --------------------------------------------------------
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `price` DECIMAL(15,2) NOT NULL,
  `profit_per_day` DECIMAL(15,2) NOT NULL,
  `duration_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `total_profit` DECIMAL(15,2) GENERATED ALWAYS AS (`profit_per_day` * `duration_days`) STORED,
  `roi_percent` DECIMAL(8,2) GENERATED ALWAYS AS ((`profit_per_day` * `duration_days` / `price`) * 100) STORED,
  `min_vip_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `stock` INT DEFAULT NULL COMMENT 'NULL = unlimited',
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_products
-- --------------------------------------------------------
CREATE TABLE `user_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `purchase_price` DECIMAL(15,2) NOT NULL,
  `profit_per_day` DECIMAL(15,2) NOT NULL,
  `duration_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `wallet_used` ENUM('main','bonus') NOT NULL DEFAULT 'main',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `last_mined_at` DATETIME DEFAULT NULL,
  `mine_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_earned` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `notified_3days` TINYINT(1) NOT NULL DEFAULT 0,
  `notified_1day` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: mining_logs
-- --------------------------------------------------------
CREATE TABLE `mining_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_product_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `profit_amount` DECIMAL(15,2) NOT NULL,
  `mined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `credited_at` DATETIME DEFAULT NULL,
  `status` ENUM('pending','credited') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `user_product_id` (`user_product_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: referrals
-- --------------------------------------------------------
CREATE TABLE `referrals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id` INT UNSIGNED NOT NULL,
  `referred_id` INT UNSIGNED NOT NULL,
  `level` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_id` (`referred_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: commissions
-- --------------------------------------------------------
CREATE TABLE `commissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `from_user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('deposit','product') NOT NULL,
  `level` TINYINT UNSIGNED NOT NULL,
  `base_amount` DECIMAL(15,2) NOT NULL,
  `percent` DECIMAL(5,2) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','credited','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: commission_settings
-- --------------------------------------------------------
CREATE TABLE `commission_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('deposit','product') NOT NULL,
  `level` TINYINT UNSIGNED NOT NULL,
  `percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_level` (`type`,`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: vouchers
-- --------------------------------------------------------
CREATE TABLE `vouchers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL,
  `type` ENUM('deposit','product') NOT NULL DEFAULT 'deposit',
  `discount_type` ENUM('percent','nominal') NOT NULL DEFAULT 'percent',
  `discount_value` DECIMAL(15,2) NOT NULL,
  `min_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `max_discount` DECIMAL(15,2) DEFAULT NULL,
  `min_vip_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `usage_limit` INT UNSIGNED DEFAULT NULL,
  `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `valid_from` DATETIME DEFAULT NULL,
  `valid_until` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_vouchers
-- --------------------------------------------------------
CREATE TABLE `user_vouchers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `voucher_id` INT UNSIGNED NOT NULL,
  `used_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `voucher_id` (`voucher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: ads
-- --------------------------------------------------------
CREATE TABLE `ads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `url` VARCHAR(500) DEFAULT NULL,
  `reward_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `reward_wallet` ENUM('profit','bonus') NOT NULL DEFAULT 'bonus',
  `watch_duration` INT UNSIGNED NOT NULL DEFAULT 30,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `total_views` INT UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: ad_watches
-- --------------------------------------------------------
CREATE TABLE `ad_watches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `ad_id` INT UNSIGNED NOT NULL,
  `reward_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `watched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id_date` (`user_id`,`watched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: ad_settings
-- --------------------------------------------------------
CREATE TABLE `ad_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `max_per_day` INT UNSIGNED NOT NULL DEFAULT 5,
  `cooldown_minutes` INT UNSIGNED NOT NULL DEFAULT 10,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: daily_reward_settings
-- --------------------------------------------------------
CREATE TABLE `daily_reward_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `reset_hour` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: daily_reward_items
-- --------------------------------------------------------
CREATE TABLE `daily_reward_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('balance_bonus','ad_quota','profit_boost') NOT NULL DEFAULT 'balance_bonus',
  `value` DECIMAL(15,2) NOT NULL,
  `wallet_type` ENUM('bonus','profit') NOT NULL DEFAULT 'bonus',
  `probability` DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Percentage chance',
  `is_jackpot` TINYINT(1) NOT NULL DEFAULT 0,
  `icon` VARCHAR(100) DEFAULT NULL,
  `color` VARCHAR(20) DEFAULT '#00D4FF',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_daily_claims
-- --------------------------------------------------------
CREATE TABLE `user_daily_claims` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `reward_item_id` INT UNSIGNED NOT NULL,
  `reward_value` DECIMAL(15,2) NOT NULL,
  `claimed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_date` (`user_id`,`claimed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: missions
-- --------------------------------------------------------
CREATE TABLE `missions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `type` ENUM('daily','weekly','milestone') NOT NULL DEFAULT 'daily',
  `action_type` VARCHAR(50) NOT NULL COMMENT 'login,mining,watch_ad,referral,deposit',
  `target_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `reward_type` ENUM('balance_bonus','voucher') NOT NULL DEFAULT 'balance_bonus',
  `reward_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `reward_voucher_id` INT UNSIGNED DEFAULT NULL,
  `icon` VARCHAR(100) DEFAULT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_missions
-- --------------------------------------------------------
CREATE TABLE `user_missions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `mission_id` INT UNSIGNED NOT NULL,
  `progress` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `is_claimed` TINYINT(1) NOT NULL DEFAULT 0,
  `completed_at` DATETIME DEFAULT NULL,
  `claimed_at` DATETIME DEFAULT NULL,
  `period_key` VARCHAR(20) DEFAULT NULL COMMENT 'YYYY-MM-DD or YYYY-WW or NULL for milestone',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_mission` (`user_id`,`mission_id`,`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: chat_rooms
-- --------------------------------------------------------
CREATE TABLE `chat_rooms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `unread_user` INT UNSIGNED NOT NULL DEFAULT 0,
  `unread_admin` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_message_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: chat_messages
-- --------------------------------------------------------
CREATE TABLE `chat_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` INT UNSIGNED NOT NULL,
  `sender_type` ENUM('user','admin') NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `message` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: chat_templates
-- --------------------------------------------------------
CREATE TABLE `chat_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: notifications
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'info',
  `icon` VARCHAR(100) DEFAULT NULL,
  `action_url` VARCHAR(500) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: notification_settings
-- --------------------------------------------------------
CREATE TABLE `notification_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `whatsapp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `whatsapp_api_url` VARCHAR(500) DEFAULT NULL,
  `whatsapp_token` VARCHAR(255) DEFAULT NULL,
  `whatsapp_sender` VARCHAR(20) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: banners
-- --------------------------------------------------------
CREATE TABLE `banners` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) DEFAULT NULL,
  `image` VARCHAR(255) NOT NULL,
  `url` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: announcements
-- --------------------------------------------------------
CREATE TABLE `announcements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: platform_info
-- --------------------------------------------------------
CREATE TABLE `platform_info` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `section` (`section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: contact_settings
-- --------------------------------------------------------
CREATE TABLE `contact_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `whatsapp` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `telegram` VARCHAR(100) DEFAULT NULL,
  `instagram` VARCHAR(100) DEFAULT NULL,
  `facebook` VARCHAR(100) DEFAULT NULL,
  `tiktok` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: popup_settings
-- --------------------------------------------------------
CREATE TABLE `popup_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_key` VARCHAR(50) NOT NULL,
  `event_name` VARCHAR(100) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `icon` VARCHAR(100) DEFAULT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_key` (`event_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: settings
-- --------------------------------------------------------
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `type` VARCHAR(20) NOT NULL DEFAULT 'string',
  `label` VARCHAR(200) DEFAULT NULL,
  `group` VARCHAR(50) DEFAULT 'general',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: marquee_settings
-- --------------------------------------------------------
CREATE TABLE `marquee_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `speed` INT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'pixels per second',
  `color` VARCHAR(20) NOT NULL DEFAULT '#00D4FF',
  `show_deposits` TINYINT(1) NOT NULL DEFAULT 1,
  `show_purchases` TINYINT(1) NOT NULL DEFAULT 1,
  `show_vip_upgrades` TINYINT(1) NOT NULL DEFAULT 1,
  `custom_messages` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: leaderboard_cache
-- --------------------------------------------------------
CREATE TABLE `leaderboard_cache` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('deposit','referral','profit') NOT NULL,
  `period` VARCHAR(10) NOT NULL COMMENT 'YYYY-MM',
  `user_id` INT UNSIGNED NOT NULL,
  `rank` INT UNSIGNED NOT NULL,
  `value` DECIMAL(15,2) NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `type_period` (`type`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: admin_logs
-- --------------------------------------------------------
CREATE TABLE `admin_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50) DEFAULT NULL,
  `target_id` INT UNSIGNED DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: cron_logs
-- --------------------------------------------------------
CREATE TABLE `cron_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job` VARCHAR(100) NOT NULL,
  `status` ENUM('success','error') NOT NULL DEFAULT 'success',
  `message` TEXT DEFAULT NULL,
  `duration_ms` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: password_resets
-- --------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(100) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- ========================================================
-- SEED DATA
-- ========================================================

-- Admin users (password: Admin@123)
INSERT INTO `admin_users` (`username`,`email`,`password`,`full_name`,`role`) VALUES
('superadmin','admin@noxara.id','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutLh0JW','Super Admin','superadmin'),
('cs_noxara','cs@noxara.id','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutLh0JW','Customer Service','cs'),
('finance_noxara','finance@noxara.id','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutLh0JW','Tim Keuangan','finance');

-- VIP Levels
INSERT INTO `vip_levels` (`level`,`name`,`min_deposit_required`,`min_deposit_per_tx`,`min_withdraw`,`withdraw_fee_percent`,`max_withdraw_per_day`,`daily_withdraw_limit`,`color`,`description`) VALUES
(0,'Pemula',0,50000,100000,15.00,1,2000000,'#888888','Level dasar untuk member baru'),
(1,'Bronze',500000,50000,50000,10.00,2,5000000,'#CD7F32','Level Bronze - fee lebih rendah'),
(2,'Silver',2000000,50000,30000,7.00,3,10000000,'#C0C0C0','Level Silver - privilege lebih banyak'),
(3,'Gold',5000000,50000,20000,5.00,5,20000000,'#FFD700','Level Gold - akses produk premium'),
(4,'Platinum',15000000,50000,15000,3.00,7,50000000,'#00D4FF','Level Platinum - eksklusif'),
(5,'Diamond',50000000,50000,10000,1.00,10,100000000,'#7B2FFF','Level tertinggi - diamond member');

-- VIP Codes
INSERT INTO `vip_codes` (`vip_level`,`code`,`description`) VALUES
(0,'NXR-VIP0','Kode member aktif'),
(1,'NXR-BRONZE2024','Kode VIP Bronze'),
(2,'NXR-SILVER2024','Kode VIP Silver'),
(3,'NXR-GOLD2024','Kode VIP Gold'),
(4,'NXR-PLAT2024','Kode VIP Platinum'),
(5,'NXR-DIAM2024','Kode VIP Diamond');

-- Commission Settings
INSERT INTO `commission_settings` (`type`,`level`,`percent`) VALUES
('deposit',1,2.00),('deposit',2,1.00),('deposit',3,0.50),
('product',1,3.00),('product',2,1.50),('product',3,0.75);

-- Product Categories
INSERT INTO `product_categories` (`name`,`slug`,`color`,`description`,`sort_order`) VALUES
('Mining Pemula','mining-pemula','#00D4FF','Paket mining untuk pemula, modal kecil untung pasti',1),
('Mining Menengah','mining-menengah','#7B2FFF','Paket mining menengah, ROI lebih tinggi',2),
('Mining Premium','mining-premium','#FFD700','Paket premium dengan profit maksimal',3);

-- Products - Mining Pemula
INSERT INTO `products` (`category_id`,`name`,`slug`,`price`,`profit_per_day`,`duration_days`,`description`,`sort_order`) VALUES
(1,'STONE I','stone-i',10000,400,30,'Paket mining pemula STONE I. Profit Rp400/hari selama 30 hari.',1),
(1,'STONE II','stone-ii',25000,1000,30,'Paket mining pemula STONE II. Profit Rp1.000/hari selama 30 hari.',2),
(1,'STONE III','stone-iii',50000,2200,30,'Paket mining pemula STONE III. Profit Rp2.200/hari selama 30 hari.',3),
(1,'STONE IV','stone-iv',100000,4500,30,'Paket mining pemula STONE IV. Profit Rp4.500/hari selama 30 hari.',4);

-- Products - Mining Menengah
INSERT INTO `products` (`category_id`,`name`,`slug`,`price`,`profit_per_day`,`duration_days`,`description`,`sort_order`) VALUES
(2,'IRON I','iron-i',200000,10000,30,'Paket mining menengah IRON I. Profit Rp10.000/hari selama 30 hari.',1),
(2,'IRON II','iron-ii',500000,27000,30,'Paket mining menengah IRON II. Profit Rp27.000/hari selama 30 hari.',2),
(2,'IRON III','iron-iii',1000000,57000,30,'Paket mining menengah IRON III. Profit Rp57.000/hari selama 30 hari.',3),
(2,'IRON IV','iron-iv',2000000,120000,30,'Paket mining menengah IRON IV. Profit Rp120.000/hari selama 30 hari.',4);

-- Products - Mining Premium
INSERT INTO `products` (`category_id`,`name`,`slug`,`price`,`profit_per_day`,`duration_days`,`description`,`sort_order`) VALUES
(3,'GOLD I','gold-i',3000000,200000,30,'Paket mining premium GOLD I. Profit Rp200.000/hari selama 30 hari.',1),
(3,'GOLD II','gold-ii',5000000,350000,30,'Paket mining premium GOLD II. Profit Rp350.000/hari selama 30 hari.',2),
(3,'GOLD III','gold-iii',7000000,510000,30,'Paket mining premium GOLD III. Profit Rp510.000/hari selama 30 hari.',3),
(3,'GOLD IV','gold-iv',10000000,750000,30,'Paket mining premium GOLD IV. Profit Rp750.000/hari selama 30 hari.',4);


-- Admin Bank Accounts
INSERT INTO `admin_bank_accounts` (`bank_name`,`account_number`,`account_name`,`is_active`,`sort_order`) VALUES
('BCA','1234567890','NOXARA INDONESIA',1,1),
('Mandiri','0987654321','NOXARA INDONESIA',1,2),
('BRI','1122334455','NOXARA INDONESIA',1,3),
('BNI','5544332211','NOXARA INDONESIA',1,4);

-- Ad Settings
INSERT INTO `ad_settings` (`max_per_day`,`cooldown_minutes`,`is_enabled`) VALUES (5,10,1);

-- Daily Reward Settings
INSERT INTO `daily_reward_settings` (`is_enabled`,`reset_hour`) VALUES (1,0);

-- Daily Reward Items
INSERT INTO `daily_reward_items` (`name`,`type`,`value`,`wallet_type`,`probability`,`is_jackpot`,`color`) VALUES
('Bonus Rp5.000','balance_bonus',5000,'bonus',30.00,0,'#00D4FF'),
('Bonus Rp10.000','balance_bonus',10000,'bonus',25.00,0,'#00D4FF'),
('Bonus Rp25.000','balance_bonus',25000,'bonus',15.00,0,'#7B2FFF'),
('Bonus Rp50.000','balance_bonus',50000,'bonus',10.00,0,'#7B2FFF'),
('Extra 2 Iklan','ad_quota',2,'bonus',10.00,0,'#FFD700'),
('Profit +50%','profit_boost',50,'profit',7.00,0,'#00FF88'),
('Jackpot Rp500.000','balance_bonus',500000,'bonus',3.00,1,'#FFD700');

-- Missions
INSERT INTO `missions` (`title`,`description`,`type`,`action_type`,`target_count`,`reward_type`,`reward_value`,`sort_order`) VALUES
('Login Harian','Login ke aplikasi hari ini','daily','login',1,'balance_bonus',2000,1),
('Mining Harian','Lakukan mining hari ini','daily','mining',1,'balance_bonus',3000,2),
('Tonton 3 Iklan','Tonton 3 iklan hari ini','daily','watch_ad',3,'balance_bonus',5000,3),
('Mining 7 Hari Berturut','Mining setiap hari selama 7 hari','weekly','mining',7,'balance_bonus',25000,4),
('Ajak 5 Teman Minggu Ini','Ajak 5 teman mendaftar minggu ini','weekly','referral',5,'balance_bonus',50000,5),
('Login 30 Hari Berturut','Login selama 30 hari berturut-turut','milestone','login',30,'balance_bonus',100000,6),
('Ajak 10 Teman','Total ajak 10 teman mendaftar','milestone','referral',10,'balance_bonus',200000,7),
('Total Mining 100 Kali','Lakukan total 100 kali mining','milestone','mining',100,'balance_bonus',500000,8);

-- Marquee Settings
INSERT INTO `marquee_settings` (`is_enabled`,`speed`,`color`,`show_deposits`,`show_purchases`,`show_vip_upgrades`,`custom_messages`) VALUES
(1,50,'#00D4FF',1,1,1,'Selamat datang di NOXARA! | Platform investasi terpercaya 2024 | Join sekarang dan dapatkan bonus!');

-- Notification Settings
INSERT INTO `notification_settings` (`whatsapp_enabled`) VALUES (0);

-- Contact Settings
INSERT INTO `contact_settings` (`whatsapp`,`email`,`telegram`,`instagram`) VALUES
('6281234567890','support@noxara.id','@noxara_id','@noxara.id');

-- Chat Templates
INSERT INTO `chat_templates` (`title`,`message`,`sort_order`) VALUES
('Salam Pembuka','Halo! Selamat datang di NOXARA. Ada yang bisa kami bantu?',1),
('Konfirmasi Deposit','Deposit Anda sedang dalam proses konfirmasi. Mohon tunggu maksimal 1x24 jam ya.',2),
('Konfirmasi Withdraw','Penarikan Anda sedang diproses. Dana akan masuk dalam 1-3 hari kerja.',3),
('Terima Kasih','Terima kasih telah menghubungi kami. Semoga investasi Anda semakin berkembang!',4);

-- Popup Settings
INSERT INTO `popup_settings` (`event_key`,`event_name`,`title`,`message`,`icon`,`is_enabled`) VALUES
('login','Login','Selamat Datang Kembali!','Senang melihatmu kembali. Semoga harimu menyenangkan!','👋',1),
('register','Register','Selamat Bergabung!','Selamat bergabung di NOXARA! Mulai perjalanan investasimu sekarang.','🎉',1),
('deposit_success','Deposit Berhasil','Deposit Dikonfirmasi!','Deposit kamu berhasil dikonfirmasi. Saldo sudah masuk ke akun!','✅',1),
('deposit_pending','Deposit Pending','Deposit Sedang Diproses','Deposit kamu sedang dalam antrian konfirmasi admin. Tunggu sebentar ya!','⏳',1),
('withdraw_submit','Withdraw Diajukan','Penarikan Diajukan!','Permintaan penarikan kamu sudah masuk. Dana akan diproses segera.','💸',1),
('withdraw_rejected','Withdraw Ditolak','Penarikan Ditolak','Maaf, penarikan kamu ditolak. Lihat alasan di halaman riwayat.','❌',1),
('buy_package','Beli Paket','Paket Berhasil Dibeli!','Selamat! Paket mining kamu sudah aktif. Mulai mining sekarang!','🚀',1),
('mining_start','Mining Dimulai','Mining Dimulai!','Mining sedang berjalan. Profit akan masuk dalam 3 jam.','⛏️',1),
('profit_in','Profit Masuk','Profit Masuk!','Selamat! Hasil mining sudah masuk ke saldo profit kamu.','💰',1),
('vip_upgrade','Naik VIP','Level VIP Naik!','Selamat! Level VIP kamu berhasil naik. Nikmati privilege lebih banyak!','🏆',1),
('logout','Logout','Sampai Jumpa!','Kamu berhasil keluar. Jangan lupa mining lagi besok ya!','👋',1),
('claim_reward','Klaim Hadiah','Hadiah Berhasil Diklaim!','Selamat! Hadiahmu sudah masuk ke saldo.','🎁',1);


-- Platform Info
INSERT INTO `platform_info` (`section`,`title`,`content`) VALUES
('about','Tentang NOXARA','<h2>Tentang NOXARA</h2><p>NOXARA adalah platform investasi digital terpercaya yang berdiri sejak 2024. Kami hadir untuk memberikan solusi investasi yang mudah, aman, dan menguntungkan bagi semua kalangan masyarakat Indonesia.</p><p>Dengan teknologi mining terkini dan sistem referral yang transparan, NOXARA memungkinkan Anda untuk mengembangkan aset digital dengan cara yang sederhana namun efektif.</p><h3>Visi Kami</h3><p>Menjadi platform investasi digital terdepan di Indonesia yang memberikan akses finansial yang setara bagi semua orang.</p><h3>Misi Kami</h3><ul><li>Menyediakan produk investasi yang transparan dan menguntungkan</li><li>Membangun ekosistem keuangan digital yang inklusif</li><li>Memberikan layanan pelanggan terbaik 24/7</li></ul>'),
('tnc','Syarat & Ketentuan','<h2>Syarat & Ketentuan NOXARA</h2><p>Dengan mendaftar dan menggunakan layanan NOXARA, Anda menyetujui syarat dan ketentuan berikut:</p><h3>1. Pendaftaran Akun</h3><p>Setiap pengguna hanya diperbolehkan memiliki satu akun. Informasi yang diberikan saat pendaftaran harus akurat dan valid.</p><h3>2. Deposit & Withdraw</h3><p>Semua transaksi deposit dan withdraw dilakukan secara manual melalui transfer bank. Proses konfirmasi maksimal 1x24 jam di hari kerja.</p><h3>3. Produk Mining</h3><p>Paket mining aktif selama 30 hari. User wajib melakukan klik mining setiap hari. Profit yang tidak diklaim dalam 24 jam akan tetap diproses otomatis.</p><h3>4. Ketentuan Umum</h3><p>NOXARA berhak menangguhkan atau menghentikan akun yang terindikasi melakukan kecurangan atau pelanggaran aturan.</p>'),
('privacy','Kebijakan Privasi','<h2>Kebijakan Privasi</h2><p>NOXARA berkomitmen untuk melindungi privasi dan keamanan data pengguna. Kebijakan ini menjelaskan bagaimana kami mengumpulkan, menggunakan, dan melindungi informasi Anda.</p><h3>Data yang Dikumpulkan</h3><ul><li>Nama lengkap, email, dan nomor telepon</li><li>Informasi rekening bank untuk proses withdraw</li><li>Riwayat transaksi dan aktivitas akun</li><li>Data perangkat dan alamat IP</li></ul><h3>Keamanan Data</h3><p>Data Anda disimpan dengan enkripsi dan tidak pernah dijual kepada pihak ketiga. Akses data dibatasi hanya untuk keperluan operasional platform.</p>'),
('withdraw_policy','Kebijakan Penarikan','<h2>Kebijakan Penarikan Dana</h2><p>Ketentuan penarikan dana di NOXARA disesuaikan dengan level VIP masing-masing member.</p><h3>Ketentuan Umum</h3><ul><li>Withdraw hanya dapat dilakukan ke rekening bank yang sudah terdaftar dan diverifikasi</li><li>Nama rekening tujuan harus sama dengan nama akun NOXARA</li><li>Jam operasional withdraw: 08:00 - 21:00 WIB</li><li>Proses transfer 1-3 hari kerja</li></ul><h3>Fee Penarikan per VIP Level</h3><ul><li>VIP 0: Fee 15%, Min WD Rp100.000</li><li>VIP 1: Fee 10%, Min WD Rp50.000</li><li>VIP 2: Fee 7%, Min WD Rp30.000</li><li>VIP 3: Fee 5%, Min WD Rp20.000</li><li>VIP 4: Fee 3%, Min WD Rp15.000</li><li>VIP 5: Fee 1%, Min WD Rp10.000</li></ul>');

-- Settings
INSERT INTO `settings` (`key`,`value`,`type`,`label`,`group`) VALUES
('site_name','NOXARA','string','Nama Website','general'),
('site_tagline','Invest Smarter, Grow Faster','string','Tagline','general'),
('site_url','https://noxara.id','string','URL Website','general'),
('site_logo','','string','Logo','general'),
('maintenance_mode','0','boolean','Mode Maintenance','general'),
('maintenance_message','Website sedang dalam pemeliharaan. Silakan kembali beberapa saat lagi.','text','Pesan Maintenance','general'),
('total_members_display','284750','integer','Tampilan Total Member','statistics'),
('total_payout_display','15800000000','integer','Tampilan Total Payout','statistics'),
('platform_rating','4.9','string','Rating Platform','statistics'),
('platform_year','2024','string','Tahun Berdiri','statistics'),
('deposit_enabled','1','boolean','Deposit Aktif','features'),
('withdraw_enabled','1','boolean','Withdraw Aktif','features'),
('referral_enabled','1','boolean','Referral Aktif','features'),
('ads_enabled','1','boolean','Iklan Aktif','features'),
('chat_enabled','1','boolean','Chat Aktif','features'),
('daily_reward_enabled','1','boolean','Hadiah Harian Aktif','features'),
('missions_enabled','1','boolean','Misi Aktif','features'),
('deposit_expiry_hours','24','integer','Batas Waktu Deposit (jam)','deposit'),
('withdraw_start_hour','8','integer','Jam Mulai Withdraw','withdraw'),
('withdraw_end_hour','21','integer','Jam Selesai Withdraw','withdraw'),
('new_member_bonus','10000','integer','Bonus Member Baru (Rp)','bonus'),
('cs_status','online','string','Status CS','chat'),
('login_max_attempts','5','integer','Maks Percobaan Login','security'),
('login_lock_minutes','30','integer','Durasi Kunci Login (menit)','security');
