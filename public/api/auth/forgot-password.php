<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_json_error('Method not allowed.', 405);
$input = cg_input();
$email = trim($input['email'] ?? '');
$pdo = Database::connect();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND is_active = 1');
$stmt->execute([$email]);
$userId = $stmt->fetchColumn();

// Always return a generic success message so the endpoint can't be used to enumerate accounts.
if ($userId) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())')
        ->execute([$userId, $token, $expiresAt]);
    cg_log_audit((int) $userId, 'PASSWORD_RESET_REQUESTED', 'auth', (string) $userId, 'success', 'Password reset requested.');
    // In production this link would be emailed. For this demo/offline environment
    // it is returned directly so the workflow remains fully testable without an SMTP server.
    cg_json_success('If that email exists, a reset link has been generated: reset-password.php?token=' . $token);
}

cg_json_success('If an account with that email exists, a reset link has been generated.');
