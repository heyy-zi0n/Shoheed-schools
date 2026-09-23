<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Invalid ID');
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT student_id, proof_path FROM payment_submissions WHERE id = ?");
$stmt->execute([$id]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    exit('Not Found');
}

$userId = current_user_id();
$role = $_SESSION['role_name'] ?? '';

// Allow Admin, Bursar, or the Student who owns it
$allowed = false;
if (in_array($role, [ROLE_ADMIN, ROLE_BURSAR], true)) {
    $allowed = true;
} elseif ($role === ROLE_STUDENT) {
    // Check if the student user owns this submission
    $sStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $sStmt->execute([$userId]);
    $studentId = $sStmt->fetchColumn();
    if ($studentId && (int)$studentId === (int)$submission['student_id']) {
        $allowed = true;
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

$path = dirname(__DIR__, 2) . '/storage/payment_proofs/' . $submission['proof_path'];
if (!file_exists($path)) {
    http_response_code(404);
    exit('File not found on disk');
}

$mime = mime_content_type($path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
