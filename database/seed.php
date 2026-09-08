<?php
/**
 * CYVANTA - Database seeder
 * Run: php database/seed.php
 *
 * Uses PHP's password_hash() so credentials are never stored in plaintext
 * or committed as a precomputed hash in version control.
 */

require_once __DIR__ . '/../config/database.php';

$pdo = Database::connect();
$isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
echo "Connected to database `" . DB_DATABASE . "`.\n";

function upsertUser(PDO $pdo, array $u): int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$u['username']]);
    if ($existing = $stmt->fetch()) {
        return (int) $existing['id'];
    }
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, username, email, phone, department, password_hash, role, is_active, must_change_password, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())'
    );
    $stmt->execute([
        $u['full_name'], $u['username'], $u['email'], $u['phone'] ?? null, $u['department'] ?? null,
        password_hash($u['password'], PASSWORD_BCRYPT), $u['role'], $u['must_change_password'] ?? 0,
    ]);
    return (int) $pdo->lastInsertId();
}

// --- Default administrator (per spec section 8 / 62) ---
$adminId = upsertUser($pdo, [
    'full_name' => 'Sumit Gupta',
    'username' => 'adminsumitgu',
    'email' => 'adminsumitgu@crimegraph.local',
    'department' => 'System Administration',
    'password' => 'sumitgu',
    'role' => 'super_admin',
    'must_change_password' => 1,
]);
echo "Admin user ready (id=$adminId).\n";

// --- Demo users covering each role ---
$investigatorId = upsertUser($pdo, ['full_name' => 'Priya Nair', 'username' => 'priya.investigator', 'email' => 'priya@crimegraph.local', 'department' => 'Field Investigation', 'password' => 'Investigator@123', 'role' => 'investigator']);
$analystId      = upsertUser($pdo, ['full_name' => 'Arjun Rao', 'username' => 'arjun.analyst', 'email' => 'arjun@crimegraph.local', 'department' => 'Intelligence Analysis', 'password' => 'Analyst@123', 'role' => 'analyst']);
$adminUserId    = upsertUser($pdo, ['full_name' => 'Neha Kapoor', 'username' => 'neha.admin', 'email' => 'neha@crimegraph.local', 'department' => 'Operations', 'password' => 'Admin@123', 'role' => 'administrator']);
$viewerId       = upsertUser($pdo, ['full_name' => 'Vikram Joshi', 'username' => 'vikram.viewer', 'email' => 'vikram@crimegraph.local', 'department' => 'Oversight', 'password' => 'Viewer@123', 'role' => 'viewer']);

// --- Entity types ---
$entityTypes = [
    ['Person', 'fa-user', '#38bdf8'],
    ['Organization', 'fa-building', '#a78bfa'],
    ['Location', 'fa-location-dot', '#34d399'],
    ['Vehicle', 'fa-car', '#fbbf24'],
    ['Phone Number', 'fa-phone', '#f472b6'],
    ['Bank Account', 'fa-building-columns', '#f87171'],
    ['Transaction', 'fa-money-bill-transfer', '#fb923c'],
    ['Event', 'fa-calendar-days', '#c084fc'],
    ['Social Media Account', 'fa-hashtag', '#60a5fa'],
    ['Document', 'fa-file-lines', '#94a3b8'],
    ['Photo / Image', 'fa-image', '#38bdf8'],
    ['Video Footage', 'fa-film', '#e879f9'],
    ['Face / Suspect Tag', 'fa-user-gear', '#ef4444'],
    ['License Plate OCR', 'fa-id-card', '#f59e0b'],
    ['GPS Location Tag', 'fa-location-crosshairs', '#10b981'],
    ['Evidence Object', 'fa-shield-halved', '#6366f1'],
];
$stmt = $isSqlite
    ? $pdo->prepare('INSERT OR IGNORE INTO entity_types (name, icon, color) VALUES (?, ?, ?)')
    : $pdo->prepare('INSERT INTO entity_types (name, icon, color) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE icon=VALUES(icon), color=VALUES(color)');
foreach ($entityTypes as $t) $stmt->execute($t);
$typeIds = [];
foreach ($pdo->query('SELECT id, name FROM entity_types') as $row) $typeIds[$row['name']] = (int) $row['id'];
echo "Entity types seeded.\n";

// --- Relationship types ---
$relTypes = ['ASSOCIATED_WITH', 'CALLS', 'OWNS', 'VISITED', 'WORKS_FOR', 'TRANSFERRED_MONEY_TO', 'MENTIONED_IN', 'FAMILY_OF', 'MET_WITH', 'FEATURED_IN_FRAME', 'SPOTTED_AT', 'IDENTIFIED_WITH'];
$stmt = $isSqlite
    ? $pdo->prepare('INSERT OR IGNORE INTO relationship_types (name) VALUES (?)')
    : $pdo->prepare('INSERT IGNORE INTO relationship_types (name) VALUES (?)');
foreach ($relTypes as $t) $stmt->execute([$t]);
$relIds = [];
foreach ($pdo->query('SELECT id, name FROM relationship_types') as $row) $relIds[$row['name']] = (int) $row['id'];
echo "Relationship types seeded.\n";

// --- Demo cases (fictional data only) ---
function upsertCase(PDO $pdo, array $c): int
{
    $stmt = $pdo->prepare('SELECT id FROM cases WHERE case_number = ?');
    $stmt->execute([$c['case_number']]);
    if ($existing = $stmt->fetch()) return (int) $existing['id'];
    $stmt = $pdo->prepare(
        'INSERT INTO cases (case_number, title, description, category, location, incident_date, priority, status, tags, created_by, lead_investigator_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $c['case_number'], $c['title'], $c['description'], $c['category'], $c['location'], $c['incident_date'],
        $c['priority'], $c['status'], $c['tags'], $c['created_by'], $c['lead_investigator_id'],
    ]);
    return (int) $pdo->lastInsertId();
}

$case1 = upsertCase($pdo, [
    'case_number' => 'CASE-2026-001', 'title' => 'Operation Nexus',
    'description' => 'Fictional demo case investigating a suspected smuggling network operating across three districts.',
    'category' => 'Organized Crime', 'location' => 'Mumbai, Maharashtra', 'incident_date' => '2026-07-02',
    'priority' => 'Critical', 'status' => 'Under Investigation', 'tags' => 'smuggling,network,demo',
    'created_by' => $adminId, 'lead_investigator_id' => $investigatorId,
]);
$case2 = upsertCase($pdo, [
    'case_number' => 'CASE-2026-002', 'title' => 'Project Shadowline',
    'description' => 'Fictional demo case examining a suspected financial fraud ring using shell companies.',
    'category' => 'Financial Fraud', 'location' => 'Pune, Maharashtra', 'incident_date' => '2026-06-14',
    'priority' => 'High', 'status' => 'Intelligence Review', 'tags' => 'fraud,shell-company,demo',
    'created_by' => $adminId, 'lead_investigator_id' => $analystId,
]);
$case3 = upsertCase($pdo, [
    'case_number' => 'CASE-2026-003', 'title' => 'Operation Crosslink',
    'description' => 'Fictional demo case tracking a cross-border communication network of interest.',
    'category' => 'Cybercrime', 'location' => 'Delhi NCR', 'incident_date' => '2026-05-20',
    'priority' => 'Medium', 'status' => 'New', 'tags' => 'cyber,communications,demo',
    'created_by' => $adminId, 'lead_investigator_id' => $investigatorId,
]);
echo "Demo cases seeded.\n";

$isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

foreach ([$case1 => $investigatorId, $case2 => $analystId, $case3 => $investigatorId] as $caseId => $userId) {
    $sql = $isSqlite 
        ? 'INSERT OR IGNORE INTO case_assignments (case_id, user_id, assigned_by) VALUES (?, ?, ?)'
        : 'INSERT IGNORE INTO case_assignments (case_id, user_id, assigned_by) VALUES (?, ?, ?)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$caseId, $userId, $adminId]);
}

// --- Demo entities for Operation Nexus ---
function addEntity(PDO $pdo, int $caseId, int $typeId, string $name, string $desc, int $risk): int
{
    $stmt = $pdo->prepare('SELECT id FROM entities WHERE case_id = ? AND name = ?');
    $stmt->execute([$caseId, $name]);
    if ($existing = $stmt->fetch()) return (int) $existing['id'];
    $stmt = $pdo->prepare('INSERT INTO entities (case_id, entity_type_id, name, description, risk_score, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
    $stmt->execute([$caseId, $typeId, $name, $desc, $risk]);
    return (int) $pdo->lastInsertId();
}

function addRelationship(PDO $pdo, int $caseId, int $sourceId, int $targetId, int $typeId, int $strength = 1): void
{
    $stmt = $pdo->prepare('SELECT id FROM relationships WHERE case_id = ? AND source_entity_id = ? AND target_entity_id = ? AND relationship_type_id = ?');
    $stmt->execute([$caseId, $sourceId, $targetId, $typeId]);
    if ($stmt->fetch()) return;
    $stmt = $pdo->prepare('INSERT INTO relationships (case_id, source_entity_id, target_entity_id, relationship_type_id, strength, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
    $stmt->execute([$caseId, $sourceId, $targetId, $typeId, $strength]);
}

$aarav = addEntity($pdo, $case1, $typeIds['Person'], 'Aarav Mehta', 'Fictional demo entity: person of interest, frequently mentioned across documents.', 82);
$rohan = addEntity($pdo, $case1, $typeIds['Person'], 'Rohan Verma', 'Fictional demo entity: associate of Aarav Mehta.', 61);
$kabir = addEntity($pdo, $case1, $typeIds['Person'], 'Kabir Malhotra', 'Fictional demo entity: secondary contact.', 44);
$org1  = addEntity($pdo, $case1, $typeIds['Organization'], 'Nexus Freight Pvt Ltd', 'Fictional demo shell logistics company.', 70);
$loc1  = addEntity($pdo, $case1, $typeIds['Location'], 'Andheri Warehouse District', 'Fictional demo location referenced in surveillance notes.', 30);
$veh1  = addEntity($pdo, $case1, $typeIds['Vehicle'], 'MH-04-AB-1234', 'Fictional demo vehicle linked to warehouse visits.', 25);
$phone1 = addEntity($pdo, $case1, $typeIds['Phone Number'], '+91-98XXXXXX10', 'Fictional demo phone number, frequent contact pattern detected.', 55);

addRelationship($pdo, $case1, $aarav, $rohan, $relIds['ASSOCIATED_WITH'], 8);
addRelationship($pdo, $case1, $aarav, $kabir, $relIds['ASSOCIATED_WITH'], 3);
addRelationship($pdo, $case1, $aarav, $org1, $relIds['WORKS_FOR'], 5);
addRelationship($pdo, $case1, $rohan, $org1, $relIds['WORKS_FOR'], 4);
addRelationship($pdo, $case1, $aarav, $veh1, $relIds['OWNS'], 1);
addRelationship($pdo, $case1, $veh1, $loc1, $relIds['VISITED'], 6);
addRelationship($pdo, $case1, $aarav, $phone1, $relIds['CALLS'], 12);
addRelationship($pdo, $case1, $rohan, $phone1, $relIds['CALLS'], 9);
addRelationship($pdo, $case1, $kabir, $loc1, $relIds['VISITED'], 2);
echo "Demo entities & relationships seeded for Operation Nexus.\n";

// A lighter set for Project Shadowline
$vendor = addEntity($pdo, $case2, $typeIds['Organization'], 'Shadowline Holdings', 'Fictional demo shell company.', 66);
$acct1  = addEntity($pdo, $case2, $typeIds['Bank Account'], 'ACC-XXXX-7742', 'Fictional demo account with repeated transfers.', 58);
$person1 = addEntity($pdo, $case2, $typeIds['Person'], 'Devika Rao', 'Fictional demo entity: signatory of interest.', 47);
addRelationship($pdo, $case2, $person1, $vendor, $relIds['WORKS_FOR'], 3);
addRelationship($pdo, $case2, $vendor, $acct1, $relIds['ASSOCIATED_WITH'], 5);
echo "Demo entities & relationships seeded for Project Shadowline.\n";

// --- Demo audit logs & notifications ---
$auditSql = $isSqlite 
    ? 'INSERT INTO audit_logs (user_id, action, module, target, status, description, created_at) VALUES (?, ?, ?, ?, ?, ?, DATETIME(\'now\', \'-\' || ? || \' minute\'))'
    : 'INSERT INTO audit_logs (user_id, action, module, target, status, description, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? MINUTE)';
$stmt = $pdo->prepare($auditSql);
$demoAudit = [
    [$adminId, 'LOGIN', 'auth', (string)$adminId, 'success', 'Administrator logged in.', 120],
    [$investigatorId, 'CASE_CREATED', 'cases', 'CASE-2026-001', 'success', 'Created case Operation Nexus.', 110],
    [$investigatorId, 'DOCUMENT_UPLOADED', 'documents', 'CASE-2026-001', 'success', 'Uploaded FIR report.', 90],
    [$investigatorId, 'DOCUMENT_PROCESSED', 'documents', 'CASE-2026-001', 'success', 'AI/NLP demo pipeline processed document.', 88],
    [$analystId, 'AI_ANALYSIS_PERFORMED', 'analysis', 'CASE-2026-001', 'success', 'Ran suspicious pattern detection.', 60],
];
foreach ($demoAudit as $row) $stmt->execute($row);

$notifSql = $isSqlite
    ? 'INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, DATETIME(\'now\', \'-\' || ? || \' minute\'))'
    : 'INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW() - INTERVAL ? MINUTE)';
$stmt = $pdo->prepare($notifSql);
$stmt->execute([$investigatorId, 'case', 'Case Assigned', 'You were assigned to Operation Nexus.', 'case-details.php?id=' . $case1, 100]);
$stmt->execute([$investigatorId, 'analysis', 'AI Analysis Completed', 'Analysis completed for Operation Nexus.', 'analysis.php?case_id=' . $case1, 55]);
$stmt->execute([null, 'system', 'Welcome to CYVANTA', 'The investigation platform is ready for use.', 'dashboard.php', 200]);
echo "Demo audit logs & notifications seeded.\n";

// --- System settings ---
$settings = [
    'app_name' => 'CYVANTA',
    'session_timeout_minutes' => '30',
    'max_login_attempts' => '5',
    'account_lock_minutes' => '15',
    'file_upload_limit_mb' => '15',
    'allowed_file_extensions' => 'pdf,doc,docx,txt,csv,jpg,jpeg,png',
    'ai_service_url' => '',
    'ai_service_enabled' => '0',
    'entity_extraction_enabled' => '1',
    'relationship_extraction_enabled' => '1',
    'analysis_confidence_threshold' => '60',
];
$settingsSql = $isSqlite
    ? 'INSERT OR REPLACE INTO system_settings (setting_key, setting_value) VALUES (?, ?)'
    : 'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
$stmt = $pdo->prepare($settingsSql);
foreach ($settings as $k => $v) $stmt->execute([$k, $v]);
echo "System settings seeded.\n";

echo "\nSeed complete.\n";
echo "Default admin login -> username: adminsumitgu / password: sumitgu (must change on first login)\n";
