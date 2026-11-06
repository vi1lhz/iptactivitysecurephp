<?php
// JSON API for the signed-in user's notes (issues 9, 10, 11).
declare(strict_types=1);
define('IS_API', true);
require __DIR__ . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// Issue 11: no Access-Control-Allow-* headers at all = same-origin only.

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

// Reject requests from other websites (cookie-authenticated API).
if (isset($_SERVER['HTTP_ORIGIN']) && !same_origin()) {
    respond(403, ['error' => 'Origin not allowed']);
}

// Issue 9: authentication required.
$uid = current_user_id();
if ($uid === null) {
    respond(401, ['error' => 'Authentication required']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    // Only your own notes. A different user_id is refused.
    if (isset($_GET['user_id']) && positive_int($_GET['user_id']) !== $uid) {
        respond(403, ['error' => 'Forbidden']);
    }
    respond(200, ['notes' => list_notes($uid), 'csrf' => csrf_token()]);
}

if ($method === 'POST') {
    // Issue 11: CSRF token in a custom header (forms on other sites can't set it).
    if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        respond(403, ['error' => 'Invalid CSRF token']);
    }
    // Issue 10: strict request validation.
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type !== 'application/json') {
        respond(415, ['error' => 'Content-Type must be application/json']);
    }
    $raw = file_get_contents('php://input', false, null, 0, 65536);
    try {
        $data = json_decode((string) $raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        respond(400, ['error' => 'Malformed JSON']);
    }
    if (!is_array($data) || array_is_list($data)) {
        respond(400, ['error' => 'Expected a JSON object']);
    }
    $extra = array_diff(array_keys($data), ['id', 'title', 'body']);
    if ($extra) {
        respond(400, ['error' => 'Unexpected fields']);
    }
    $id = positive_int($data['id'] ?? null);
    if ($id === null) {
        respond(422, ['error' => 'id must be a positive integer']);
    }
    $err = validate_note($data['title'] ?? null, $data['body'] ?? null);
    if ($err !== null) {
        respond(422, ['error' => $err]);
    }
    // Issue 9: per-record authorization (owner check inside update_note).
    if (!update_note($id, $uid, $data['title'], $data['body'])) {
        respond(404, ['error' => 'Note not found']);
    }
    respond(200, ['updated' => 1]);
}

header('Allow: GET, POST');
respond(405, ['error' => 'Method not supported']);
