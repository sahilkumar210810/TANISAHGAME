<?php
/**
 * CONFIGURATION FILE
 * Bot settings and configurations
 */

// Set timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

// Bot Configuration
define('BOT_NAME', 'Tanisah Gaming Bot');
define('DEFAULT_COINS', 500);  // सभी users के लिए default coins
define('WELCOME_BONUS', 500);  // Welcome bonus अब optional है
define('MAX_COINS', 10000000); // 10 million
define('MAX_STEAL_AMOUNT', 100000);

// Game Settings
define('MAX_DAILY_SPINS', 3);
define('SPIN_COOLDOWN', 10); // seconds
define('STEAL_COOLDOWN', 10); // seconds
define('STEAL_SUCCESS_RATE', 75); // percentage
define('STEAL_FINE', 100); // coins

// Safe Zone Prices
define('SAFE_1_DAY_PRICE', 200);
define('SAFE_2_DAY_PRICE', 500);
define('SAFE_3_DAY_PRICE', 1000);

// NEW: Auto-create user settings
define('AUTO_CREATE_USERS', true); // बिना start किए auto create users
define('AUTO_COINS', 500); // Auto created users के coins

// NEW: Chat Settings
define('REPLY_TO_MESSAGES', true); // Always reply to user messages

// NEW: Backup/Restore Settings
define('BACKUP_CHANNEL_ID', -1001904949193); // अपना backup channel ID यहाँ डालें
define('BOTCAST_COOLDOWN', 300); // 5 minutes between botcasts

// NEW: GitHub Backup Settings
define('GITHUB_BACKUP_URL', 'https://raw.githubusercontent.com/sahilkumar210810/TANISAHGAME/blob/main/tanu_bot_backup.json');
define('GITHUB_TOKEN', ''); // Optional, only for private repos

// Spin Wheel Prizes
$SPIN_PRIZES = [
    ['amount' => 50, 'chance' => 35, 'text' => '💰 50 Tanu Coins', 'emoji' => '🟢'],
    ['amount' => 100, 'chance' => 25, 'text' => '🎯 100 Tanu Coins', 'emoji' => '🔵'],
    ['amount' => 250, 'chance' => 20, 'text' => '🎁 250 Tanu Coins', 'emoji' => '🟡'],
    ['amount' => 500, 'chance' => 12, 'text' => '🏆 500 Tanu Coins', 'emoji' => '🟠'],
    ['amount' => 1000, 'chance' => 5, 'text' => '🎊 1000 Tanu Coins', 'emoji' => '🔴'],
    ['amount' => 0, 'chance' => 3, 'text' => '😢 Better Luck Next Time', 'emoji' => '⚫']
];

// Environment Variables
$botToken = getenv('BOT_TOKEN') ?: '';
$secretToken = getenv('SECRET_TOKEN') ?: 'default_secret_token';
$adminId = getenv('ADMIN_ID') ?: '';
$botUsername = getenv('BOT_USERNAME') ?: '';
$dataGroupId = getenv('DATA_GROUP_ID') ?: '';

// Set constants
define('BOT_TOKEN', $botToken);
define('SECRET_TOKEN', $secretToken);
define('ADMIN_ID', $adminId);
define('BOT_USERNAME', $botUsername);
define('DATA_GROUP_ID', $dataGroupId);

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Start session if not started
if (!isset($_SESSION)) {
    session_start();
}
?>
