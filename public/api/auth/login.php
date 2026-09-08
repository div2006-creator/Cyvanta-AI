<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cg_json_error('Method not allowed.', 405);
}

$input = cg_input();
$username = trim($input['username'] ?? '');
$password = (string) ($input['password'] ?? '');

if ($username === '' || $password === '') {
    cg_json_error('Username and password are required.', 422);
}

$result = cg_attempt_login($username, $password);

if (!$result['success']) {
    cg_json_error($result['message'], 401);
}

cg_json_success($result['message'], ['must_change_password' => $result['must_change_password']]);
