<?php
/**
 * Configuration & Environment Bootstrap
 * Loads all environment variables from .env to prevent hard-coding.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to safely fetch environment variables with fallback
function env($key, $default = null) {
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

// Parse .env file if available
$envFile = file_exists(dirname(__DIR__) . '/.env') ? dirname(__DIR__) . '/.env' : __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }

        // Support both KEY=VALUE and $key = 'value';
        if (preg_match('/^(?:export\s+)?\$?([a-zA-Z0-9_]+)\s*=\s*(.*?);?$/', $line, $matches)) {
            $name = trim($matches[1]);
            $value = trim($matches[2]);

            // Strip surrounding quotes
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            putenv("{$name}={$value}");
            
            // Normalize case for common DB keys
            $upper = strtoupper($name);
            $_ENV[$upper] = $value;
            putenv("{$upper}={$value}");
        }
    }
}

// Set default timezone
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Makassar'));

// Database Connection Helper
function get_db_connection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = env('DB_HOST');
    $port = env('DB_PORT', '3308');
    $db   = env('DB_DATABASE', env('DB_NAME'));
    $user = env('DB_USERNAME', env('DB_USER'));
    $pass = env('DB_PASSWORD', env('DB_PASS'));

    if (empty($host) || empty($db) || empty($user)) {
        throw new Exception("Database configuration is missing in .env file.");
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);

    return $pdo;
}
