<?php
/**
 * CYVANTA - Core configuration
 * Loads .env (simple parser, no composer dependency) and defines constants.
 */

if (!function_exists('cg_load_env')) {
    function cg_load_env(string $path): array {
        $vars = [];
        if (!file_exists($path)) {
            return $vars;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");
            $vars[$key] = $value;
            putenv("$key=$value");
        }
        return $vars;
    }
}

$rootPath = dirname(__DIR__);
$envFile = file_exists($rootPath . '/.env') ? $rootPath . '/.env' : $rootPath . '/.env.example';
cg_load_env($envFile);

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

define('APP_ROOT', $rootPath);
define('APP_NAME', env('APP_NAME', 'CYVANTA'));
define('APP_ENV', env('APP_ENV', 'development'));
define('APP_URL', rtrim(env('APP_URL', 'http://localhost:8000'), '/'));

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_DATABASE', env('DB_DATABASE', 'crimegraph_ai'));
define('DB_USERNAME', env('DB_USERNAME', 'root'));
define('DB_PASSWORD', env('DB_PASSWORD', ''));

define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 1800));

define('WEBSOCKET_URL', env('WEBSOCKET_URL', 'ws://localhost:8081'));
define('WEBSOCKET_HOST', env('WEBSOCKET_HOST', '127.0.0.1'));
define('WEBSOCKET_PORT', (int) env('WEBSOCKET_PORT', 8081));

define('AI_SERVICE_URL', env('AI_SERVICE_URL', ''));
define('AI_SERVICE_ENABLED', filter_var(env('AI_SERVICE_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN));

define('UPLOAD_DIR', APP_ROOT . '/storage/uploads');
define('UPLOAD_MAX_SIZE', ((int) env('UPLOAD_MAX_SIZE_MB', 50)) * 1024 * 1024);
define('ALLOWED_UPLOAD_EXTENSIONS', ['pdf', 'doc', 'docx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'webp', 'tiff', 'bmp', 'mp4', 'avi', 'mov', 'mkv', 'webm']);

if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}

error_reporting(APP_ENV === 'development' ? E_ALL : 0);
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
date_default_timezone_set('Asia/Kolkata');
