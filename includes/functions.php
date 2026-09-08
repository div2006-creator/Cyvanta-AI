<?php
/**
 * CYVANTA - Shared helper functions
 */

require_once __DIR__ . '/../config/database.php';

function cg_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    while (ob_get_level() > 0) { ob_end_clean(); }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"message":"Unable to generate a server response.","data":{}}';
        exit;
    }
    echo $json;
    exit;
}

function cg_json_success(string $message = '', array $data = []): void
{
    cg_json(['success' => true, 'message' => $message, 'data' => $data]);
}

function cg_json_error(string $message, int $status = 400, array $data = []): void
{
    cg_json(['success' => false, 'message' => $message, 'data' => $data], $status);
}

function cg_input(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST;
}

function cg_clean(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/** Write an audit log record. $status is 'success' or 'failure'. */
function cg_log_audit(?int $userId, string $action, string $module, ?string $target, string $status = 'success', string $description = ''): void
{
    try {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, module, target, ip_address, status, description, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId,
            $action,
            $module,
            $target,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $status,
            $description,
        ]);
        cg_push_activity("$action on $module" . ($target ? " ($target)" : ''));
    } catch (Throwable $e) {
        error_log('[CYVANTA] audit log failed: ' . $e->getMessage());
    }
}

/** Create an in-app notification for a user (or all users if $userId is null -> broadcast). */
function cg_create_notification(?int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    try {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())'
        );
        $stmt->execute([$userId, $type, $title, $message, $link]);
        cg_push_activity($title . ($userId ? '' : ' (broadcast)'));
    } catch (Throwable $e) {
        error_log('[CYVANTA] notification failed: ' . $e->getMessage());
    }
}

/** Append to the lightweight real-time activity feed consumed by websocket/poll bridge. */
function cg_push_activity(string $text): void
{
    try {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('INSERT INTO system_activity (description, created_at) VALUES (?, NOW())');
        $stmt->execute([$text]);
        // Trim table so it never grows unbounded in the demo environment
        $pdo->exec('DELETE FROM system_activity WHERE id NOT IN (SELECT id FROM (SELECT id FROM system_activity ORDER BY id DESC LIMIT 200) t)');
    } catch (Throwable $e) {
        error_log('[CYVANTA] activity feed failed: ' . $e->getMessage());
    }
}

function cg_time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

function cg_generate_case_id(): string
{
    $pdo = Database::connect();
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE case_number LIKE ?");
    $stmt->execute(["CASE-$year-%"]);
    $count = (int) $stmt->fetchColumn() + 1;
    return sprintf('CASE-%s-%03d', $year, $count);
}

function cg_paginate(int $totalRows, int $page, int $perPage): array
{
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return compact('totalRows', 'page', 'perPage', 'totalPages', 'offset');
}


function cg_user_can_access_case(int $caseId, ?array $user = null): bool
{
    $user = $user ?? cg_current_user();
    if (!$user) return false;
    if (in_array($user['role'], ['super_admin','administrator'], true)) return true;
    $pdo = Database::connect();
    $stmt = $pdo->prepare('SELECT 1 FROM cases c WHERE c.id = ? AND (c.created_by = ? OR c.lead_investigator_id = ? OR EXISTS (SELECT 1 FROM case_assignments ca WHERE ca.case_id = c.id AND ca.user_id = ?)) LIMIT 1');
    $stmt->execute([$caseId, $user['id'], $user['id'], $user['id']]);
    return (bool)$stmt->fetchColumn();
}
