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

/**
 * Deterministically refine and validate entity classification.
 * Ensures Vehicles, Courts, Agencies, Locations, Organizations, Weapons, Cases, etc.
 * are NEVER classified as Person simply because of title capitalization or graph connections.
 */
function cg_determine_entity_type(string $name, ?string $description = '', ?string $suggestedType = null): string
{
    $n = trim($name);
    $nLower = strtolower($n);

    // 1. VEHICLE PATTERNS
    if (preg_match('/\b(tata|safari|maruti|suzuki|toyota|fortuner|innova|honda|city|civic|hyundai|creta|verna|mahindra|scorpio|bolero|thar|bmw|audi|mercedes|benz|ford|chevrolet|nissan|volkswagen|skoda|car|cars|vehicle|vehicles|suv|sedan|truck|trucks|van|vans|motorcycle|motorcycles|bike|bikes|scooter|jeep|coupe|hatchback|auto|rickshaw|bus|cab|taxi)\b/i', $nLower)) {
        return 'Vehicle';
    }
    if ($suggestedType === 'Vehicle' || $suggestedType === 'License Plate OCR') {
        return 'Vehicle';
    }

    // 2. COURT PATTERNS
    if (preg_match('/\b(high court|supreme court|sessions court|district court|magistrate court|trial court|family court|apex court|constitutional court|tribunal|jmic court)\b/i', $nLower)) {
        return 'Court';
    }

    // 3. AGENCY PATTERNS
    if (preg_match('/\b(delhi police|mumbai police|chandigarh police|police|crime branch|cbi|central bureau of investigation|nia|national investigation agency|interpol|special cell|cid|enforcement directorate|raw|research and analysis wing|intelligence bureau|police station|cyber cell|cyber crime unit|cyber crime police)\b/i', $nLower)) {
        if (!preg_match('/\b(court)\b/i', $nLower)) {
            return 'Agency';
        }
    }

    // 4. LOCATION PATTERNS
    if (preg_match('/\b(tamarind court|food court|courtyard)\b/i', $nLower)) {
        return 'Location';
    }
    if (preg_match('/\b(chandigarh|delhi|new delhi|mumbai|purulia|kolkata|calcutta|bangalore|bengaluru|jaipur|rajasthan|punjab|haryana|west bengal|bihar|uttar pradesh|london|sofia|bulgaria|latvia|india|pakistan|united kingdom|chennai|hyderabad|ahmedabad|surat|pune)\b/i', $nLower)) {
        if (!preg_match('/\b(police|court|cbi|high court|supreme court)\b/i', $nLower)) {
            return 'Location';
        }
    }
    if (preg_match('/\b(road|street|marg|nagar|colony|sector|village|town|city|district|state|country|airport|station|port|hotel|resort|restaurant|bar|pub|club|park|complex|building|house|plaza)\b/i', $nLower)) {
        if (!preg_match('/\b(police|cbi|court|high court|supreme court|ltd|limited|inc|corp|industries)\b/i', $nLower)) {
            return 'Location';
        }
    }

    // 5. ORGANIZATION PATTERNS
    if (preg_match('/\b(piccadilly|agro|industries|corp|corporation|ltd|limited|inc|pvt|private limited|company|syndicate|trust|foundation|bank|group|association|society|hospital|university|college|school|institute)\b/i', $nLower)) {
        if (!preg_match('/\b(police|court|cbi|high court|supreme court)\b/i', $nLower)) {
            return 'Organization';
        }
    }

    // 6. WEAPON & AMMUNITION PATTERNS
    if (preg_match('/\b(ak-47|rifle|rifles|pistol|pistols|revolver|gun|guns|firearm|firearms|cartridge|cartridges|bullet|bullets|grenade|grenades|explosive|explosives|knife|dagger|blade|ammunition|arms)\b/i', $nLower)) {
        return (stripos($nLower, 'ammunition') !== false || stripos($nLower, 'bullet') !== false || stripos($nLower, 'cartridge') !== false) ? 'Ammunition' : 'Weapon';
    }

    // 7. CASE PATTERNS
    if (preg_match('/\b(murder case|criminal case|case study|case overview|fir no|neutral citation|bail petition|question no|starred question)\b/i', $nLower)) {
        return 'Case';
    }

    // 8. NON-PERSON SYSTEM TOKENS IN NAME
    if (preg_match('/\b(case|court|police|bureau|branch|department|ministry|unit|library|council|commission|report|overview|study|citation|section|offences|record|statement|summary|filename|format|tokens|intelligence|evidence|metadata|analysis|size|video|photo|image|stream|feed|tag|ocr|gps|file|type|description|resolution|detected)\b/i', $nLower)) {
        if (preg_match('/\b(court|police|bureau|department|ministry|branch|unit)\b/i', $nLower)) return 'Agency';
        if (preg_match('/\b(photo|image|video|footage|ocr|gps|media|file)\b/i', $nLower)) return 'Document';
        return 'Organization';
    }

    // 9. GENUINE PERSON NAME CHECK
    if (preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?$/', $n)) {
        return 'Person';
    }

    // Respect valid technical suggested types if provided
    if (!empty($suggestedType) && in_array($suggestedType, ['Phone Number', 'Email', 'Bank Account', 'Transaction', 'Money', 'Aircraft', 'Date', 'Document', 'Legal Notice', 'Event', 'Social Media Account', 'Photo / Image', 'Video Footage', 'Face / Suspect Tag', 'License Plate OCR', 'GPS Location Tag', 'Evidence Object'], true)) {
        return $suggestedType;
    }

    return 'Person';
}

/** Check if an entity is eligible for a numerical risk score (Person Accused/Suspect/Involved only) */
function cg_is_entity_risk_eligible(string $typeName, string $name = '', ?string $description = ''): bool
{
    // Verify true semantic type
    $actualType = cg_determine_entity_type($name, $description, $typeName);
    if ($actualType !== 'Person') {
        return false;
    }

    // Non-person risk score prevention double-check
    $text = strtolower($name . ' ' . ($description ?? ''));
    if (preg_match('/\b(victim|deceased|witness|eyewitness|judge|justice|advocate|lawyer|counsel|prosecutor|investigator|officer)\b/i', $text)) {
        if (!preg_match('/\b(accused|suspect|prime suspect|involved|co-accused|conspirator|mastermind)\b/i', $text)) {
            return false;
        }
    }

    return true;
}

/** Get formatted risk score, level, and badge CSS class */
function cg_calculate_risk_level($riskScore, string $typeName = 'Person', string $name = '', ?string $description = ''): array
{
    $isEligible = cg_is_entity_risk_eligible($typeName, $name, $description) && $riskScore !== null && $riskScore !== '' && ((int)$riskScore >= 0);

    if (!$isEligible) {
        return [
            'is_eligible' => false,
            'score' => null,
            'score_display' => 'N/A',
            'level_display' => 'Not Applicable',
            'level_class' => 'priority-low bg-secondary text-white'
        ];
    }

    $score = (int) $riskScore;
    if ($score > 80) {
        $level = 'CRITICAL';
        $class = 'priority-critical';
    } elseif ($score > 60) {
        $level = 'HIGH';
        $class = 'priority-high';
    } elseif ($score > 30) {
        $level = 'MEDIUM';
        $class = 'priority-medium';
    } else {
        $level = 'LOW';
        $class = 'priority-low';
    }

    return [
        'is_eligible' => true,
        'score' => $score,
        'score_display' => $score . '%',
        'level_display' => $level,
        'level_class' => $class
    ];
}

