<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN, ROLE_BURSAR]);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method Not Allowed']));
}

$sessionId = filter_var($_GET['session_id'] ?? null, FILTER_VALIDATE_INT);
if (!$sessionId) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid session ID']));
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, name FROM terms WHERE academic_session_id = ? ORDER BY id ASC");
    $stmt->execute([$sessionId]);
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode(['terms' => $terms]);
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database error']));
}
