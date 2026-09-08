<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
cg_require_login();

$pdo = Database::connect();
$service = new IntelligenceService($pdo);

// Single Event Details if id parameter provided
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
if ($id) {
    $event = $service->getEventById($id);
    if (!$event) {
        cg_json_error('Intelligence event not found.', 404);
    }
    cg_json_success('Intelligence event retrieved.', $event);
}

// Filtered and Paginated List
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));

$filters = [
    'severity' => $_GET['severity'] ?? null,
    'event_type' => $_GET['event_type'] ?? null,
    'source_type' => $_GET['source_type'] ?? null,
    'location' => $_GET['location'] ?? null,
    'date' => $_GET['date'] ?? null
];

$res = $service->getEvents($filters, $page, $limit);
cg_json_success('Intelligence events retrieved.', $res);
