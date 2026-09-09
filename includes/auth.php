<?php
/**
 * CYVANTA - Authentication & RBAC helpers
 */

require_once __DIR__ . '/../config/database.php';

function cg_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('crimegraph_session');
        session_start();
    }

    // Enforce idle session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        cg_logout();
        header('Location: ' . cg_base_url('/session-expired.php'));
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function cg_base_url(string $path = ''): string
{
    // Works whether the app lives at web root (e.g. `php -S localhost:8000 -t public`)
    // or in a subfolder (e.g. http://localhost/crimegraph-ai/public/...).
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.*?)(?:/api(?:/.*)?|/admin(?:/.*)?)$#', $scriptDir, $matches)) {
        $scriptDir = $matches[1];
    }
    if (str_ends_with($scriptDir, '/public')) {
        $scriptDir = substr($scriptDir, 0, -strlen('/public'));
    }
    // Normalize the web-root case to an empty string. Without this, callers
    // that do `$base . '/assets/css/style.css'` would produce "//assets/..."
    // when $scriptDir is "/" — and a leading "//" makes browsers treat the
    // URL as protocol-relative (i.e. "load this from a server named
    // 'assets'"), which silently breaks every stylesheet, script, and link.
    if ($scriptDir === '/' || $scriptDir === '') {
        $scriptDir = '';
    } else {
        $scriptDir = rtrim($scriptDir, '/');
    }

    if ($path === '') {
        return $scriptDir;
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return $scriptDir . $path;
}

function cg_current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function cg_is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function cg_require_login(): void
{
    if (!cg_is_logged_in()) {
        header('Location: ' . cg_base_url('/login.php'));
        exit;
    }
}

function cg_require_role(array $allowedRoles): void
{
    cg_require_login();
    $user = cg_current_user();
    if (!in_array($user['role'], $allowedRoles, true)) {
        header('Location: ' . cg_base_url('/unauthorized.php'));
        exit;
    }
}

function cg_require_admin(): void
{
    cg_require_role(['super_admin', 'administrator']);
}

/** Attempt to authenticate a user. Returns array with success/message. */
function cg_attempt_login(string $username, string $password): array
{
    $pdo = Database::connect();

    // Rate-limit by username + IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $since = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND success = 0 AND attempted_at > ?');
    $stmt->execute([$username, $since]);
    if ((int) $stmt->fetchColumn() >= 5) {
        return ['success' => false, 'message' => 'Too many failed attempts. Please try again in 15 minutes.'];
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    $recordAttempt = function (bool $success) use ($pdo, $username, $ip) {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (username, ip_address, success, attempted_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$username, $ip, $success ? 1 : 0]);
    };

    if (!$user) {
        $recordAttempt(false);
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    if ((int) $user['is_active'] === 0) {
        $recordAttempt(false);
        return ['success' => false, 'message' => 'This account has been disabled. Contact an administrator.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $recordAttempt(false);
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    $recordAttempt(true);

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['full_name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'department' => $user['department'],
    ];
    $_SESSION['last_activity'] = time();
    session_regenerate_id(true);

    $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $stmt->execute([$user['id']]);

    cg_log_audit((int) $user['id'], 'LOGIN', 'auth', (string) $user['id'], 'success', 'User logged in.');
    cg_create_notification((int) $user['id'], 'system', 'Welcome back', 'You logged in successfully.');

    return ['success' => true, 'message' => 'Login successful.', 'must_change_password' => (int) $user['must_change_password'] === 1];
}

function cg_logout(): void
{
    $user = cg_current_user();
    if ($user) {
        cg_log_audit($user['id'], 'LOGOUT', 'auth', (string) $user['id'], 'success', 'User logged out.');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/** CSRF token helpers */
function cg_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function cg_verify_csrf(?string $token): bool
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    if (is_string($token) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return cg_is_logged_in();
}
