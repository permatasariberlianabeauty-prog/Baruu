<?php
/**
 * NOXARA - Application Constants
 */

// ── Transaction Types ─────────────────────────────────────
const TRX_DEPOSIT             = 'deposit';
const TRX_WITHDRAW            = 'withdraw';
const TRX_BUY_PACKAGE         = 'buy_package';
const TRX_MINING_PROFIT       = 'mining_profit';
const TRX_MINING_RETURN       = 'mining_return';
const TRX_REFERRAL_COMMISSION = 'referral_commission';
const TRX_BONUS_REGISTER      = 'bonus_register';
const TRX_DAILY_REWARD        = 'daily_reward';
const TRX_MISSION_REWARD      = 'mission_reward';
const TRX_AD_REWARD           = 'ad_reward';
const TRX_VOUCHER_BONUS       = 'voucher_bonus';
const TRX_ADMIN_CREDIT        = 'admin_credit';
const TRX_ADMIN_DEBIT         = 'admin_debit';
const TRX_WITHDRAW_RETURN     = 'withdraw_return';

// ── Wallet Types ──────────────────────────────────────────
const WALLET_MAIN     = 'main';
const WALLET_PROFIT   = 'profit';
const WALLET_BONUS    = 'bonus';
const WALLET_REFERRAL = 'referral';

// ── Deposit Statuses ──────────────────────────────────────
const DEPOSIT_PENDING   = 'pending';
const DEPOSIT_CONFIRMED = 'confirmed';
const DEPOSIT_REJECTED  = 'rejected';
const DEPOSIT_EXPIRED   = 'expired';

// ── Withdraw Statuses ─────────────────────────────────────
const WITHDRAW_PENDING    = 'pending';
const WITHDRAW_PROCESSING = 'processing';
const WITHDRAW_APPROVED   = 'approved';
const WITHDRAW_REJECTED   = 'rejected';

// ── Commission Types ──────────────────────────────────────
const COMMISSION_DEPOSIT = 'deposit';
const COMMISSION_PRODUCT = 'product';

// ── Mining Status ─────────────────────────────────────────
const MINING_COOLDOWN_HOURS = 3;  // Jam cooldown setelah mining
const MINING_CREDIT_DELAY   = 3;  // Jam delay profit masuk

// ── User Package Status ───────────────────────────────────
const PACKAGE_ACTIVE    = 'active';
const PACKAGE_COMPLETED = 'completed';
const PACKAGE_CANCELLED = 'cancelled';

// ── Mission Types ─────────────────────────────────────────
const MISSION_DAILY     = 'daily';
const MISSION_WEEKLY    = 'weekly';
const MISSION_MILESTONE = 'milestone';

// ── Mission Action Types ──────────────────────────────────
const ACTION_LOGIN     = 'login';
const ACTION_MINING    = 'mining';
const ACTION_WATCH_AD  = 'watch_ad';
const ACTION_REFERRAL  = 'referral';
const ACTION_DEPOSIT   = 'deposit';

// ── Notification Types ────────────────────────────────────
const NOTIF_INFO    = 'info';
const NOTIF_SUCCESS = 'success';
const NOTIF_WARNING = 'warning';
const NOTIF_ERROR   = 'error';
const NOTIF_DEPOSIT = 'deposit';
const NOTIF_WITHDRAW= 'withdraw';
const NOTIF_MINING  = 'mining';
const NOTIF_VIP     = 'vip';
const NOTIF_SYSTEM  = 'system';

// ── Admin Roles ───────────────────────────────────────────
const ROLE_SUPERADMIN = 'superadmin';
const ROLE_CS         = 'cs';
const ROLE_FINANCE    = 'finance';

// ── VIP Levels range ──────────────────────────────────────
const VIP_MIN = 0;
const VIP_MAX = 5;

// ── Pagination ────────────────────────────────────────────
const PER_PAGE         = 20;
const ADMIN_PER_PAGE   = 25;

// ── Max bank accounts per user ───────────────────────────
const MAX_BANK_ACCOUNTS = 3;

// ── Referral Levels ───────────────────────────────────────
const REFERRAL_MAX_LEVEL = 3;

// ── Chat polling interval (seconds) ──────────────────────
const CHAT_POLL_INTERVAL = 3;

// ── Leaderboard size ──────────────────────────────────────
const LEADERBOARD_TOP = 10;

// ── Date formats ──────────────────────────────────────────
const DATE_FORMAT      = 'd M Y';
const DATETIME_FORMAT  = 'd M Y H:i';
const TIME_FORMAT      = 'H:i';
const DB_DATE_FORMAT   = 'Y-m-d';
const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';
