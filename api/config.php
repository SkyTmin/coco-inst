<?php
/**
 * Configuration file for Coco Instruments API
 * 
 * IMPORTANT: In production, store sensitive data in environment variables
 * or in a file outside the web root
 */

// Load environment variables from .env
function loadEnv() {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, "\"' ");

            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}
loadEnv();

// Environment
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'production');
define('DEBUG', ENVIRONMENT === 'development');

// Database configuration - поменял DB_TYPE на mysql по умолчанию
define('DB_TYPE', getenv('DB_TYPE') ?: 'mysql'); // 'pgsql' or 'mysql'
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: (DB_TYPE === 'pgsql' ? '5432' : '3306'));
define('DB_NAME', getenv('DB_NAME') ?: 'coco_instruments');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');

// JWT Configuration
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-super-secret-jwt-key-change-this-in-production');
define('JWT_ACCESS_TOKEN_EXPIRY', 15 * 60); // 15 minutes
define('JWT_REFRESH_TOKEN_EXPIRY', 30 * 24 * 60 * 60); // 30 days
define('JWT_ALGORITHM', 'HS256');

// Security
define('BCRYPT_COST', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 15 * 60); // 15 minutes

// API Configuration
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', getenv('API_RATE_LIMIT') ?: 100); // requests per minute
define('MAX_REQUEST_SIZE', 1024 * 1024); // 1MB

// Allowed origins for CORS (comma-separated)
define('ALLOWED_ORIGINS', getenv('ALLOWED_ORIGINS') ?: '*');

// Error logging
if (!DEBUG) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Set error log path
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Create logs directory if it doesn't exist
$logsDir = dirname(__DIR__) . '/logs';
if (!file_exists($logsDir)) {
    mkdir($logsDir, 0755, true);
}

/**
 * Get database DSN string
 */
function getDatabaseDSN() {
    if (DB_TYPE === 'pgsql') {
        return sprintf(
            "pgsql:host=%s;port=%s;dbname=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME
        );
    } else {
        // MySQL DSN
        return sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            DB_HOST,
            DB_PORT,
            DB_NAME
        );
    }
}
